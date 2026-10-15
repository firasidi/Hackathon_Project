<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\Commitment;
use App\Models\Program;
use App\Models\Student;
use App\Services\Insaf;
use Illuminate\Http\Request;

class InsafController extends Controller
{
    // PUBLIC: list of programs with seats and published cut-offs
    public function programs()
    {
        return Program::orderBy('id')->get();
    }

    // PUBLIC: everything the ministry anchored on Hedera
    public function commitments()
    {
        return Commitment::orderBy('id')->get();
    }

    // The ministry and hacker buttons only exist for the demo
    private function demoOnly(): void
    {
        abort_unless(
            filter_var(env('DEMO_MODE', false), FILTER_VALIDATE_BOOLEAN),
            403,
            'This action is only available in demo mode.'
        );
    }

    // A student can only act on their own record
    private function ownOnly(Request $r, string $code): void
    {
        abort_unless($r->user()->code === $code, 403, 'You can only access your own record.');
    }

    // Info about the logged-in student
    public function student(Request $r, $code)
    {
        $this->ownOnly($r, $code);
        $s = $r->user();
        return [
            'code' => $s->code,
            'score' => $s->score,
            'placed' => $s->application?->program?->name,
        ];
    }

    // The student submits ranked choices and receives a receipt anchored on Hedera
    public function apply(Request $r)
    {
        set_time_limit(120);

        $d = $r->validate([
            'choices' => 'required|array|min:3',
            'choices.*' => 'integer|exists:programs,id',
        ]);

        $s = $r->user(); // always the logged-in student, never a code sent by the browser
        $choices = array_map('intval', array_values(array_unique($d['choices'])));
        $salt = bin2hex(random_bytes(16));
        $hash = Insaf::commit($s->code, $choices, $salt);
        $a = Insaf::anchor('c:' . $hash);

        Application::updateOrCreate(['student_id' => $s->id], [
            'choices' => $choices,
            'salt' => $salt,
            'commitment' => $hash,
            'placed_program_id' => null,
        ]);

        return [
            'code' => $s->code,
            'score' => $s->score,
            'choices' => $choices,
            'salt' => $salt,
            'hash' => $hash,
            'tx' => $a['transactionId'],
            'sequence' => (int) $a['sequence'],
        ];
    }

    // The ministry (demo only): commit rules, commit all inputs, run placement, commit results
    public function run()
    {
        $this->demoOnly();
        set_time_limit(180);

        $rules = json_encode([
            'formula' => 'score desc, tie by code asc, first choice with a free seat',
            'capacities' => Program::orderBy('id')->pluck('capacity', 'id'),
        ]);
        $this->commit('rules', hash('sha256', $rules));

        $leaves = Application::with('student')->get()
            ->map(fn($a) => Insaf::commit($a->student->code, $a->choices, $a->salt))->all();
        $this->commit('merkle_root', Insaf::merkleRoot($leaves));

        Insaf::place();

        $res = Application::with('student')->orderBy('id')->get()
            ->map(fn($a) => $a->student->code . ':' . $a->placed_program_id)->implode(',');
        $cut = Program::orderBy('id')->get()
            ->map(fn($p) => "{$p->id}:{$p->admitted}:{$p->cutoff_score}:{$p->cutoff_code}")->implode(',');
        $this->commit('results', hash('sha256', $res . '|' . $cut));

        return Commitment::orderBy('id')->get();
    }

    private function commit(string $kind, string $hash): void
    {
        $a = Insaf::anchor($kind . ':' . $hash);
        Commitment::create([
            'kind' => $kind,
            'hash' => $hash,
            'hcs_tx_id' => $a['transactionId'],
            'hcs_sequence' => $a['sequence'],
        ]);
    }

    // The "hacker" (demo only): silently rewrites the student's own record, like the Kef incident
    public function tamper(Request $r, $code)
    {
        $this->demoOnly();
        $this->ownOnly($r, $code);

        $a = Student::where('code', $code)->firstOrFail()->application()->firstOrFail();
        $orig = $a->choices;
        $rev = array_reverse($orig);
        $a->update([
            'choices' => $rev,
            'commitment' => Insaf::commit($code, $rev, $a->salt), // the attacker even fixes the hash
            'placed_program_id' => end($orig),                     // moved to the student's last choice
        ]);
        return ['tampered' => true];
    }

    // The student's check, done on their own receipt
    public function verify(Request $r)
    {
        $d = $r->validate([
            'code' => 'required|string',
            'score' => 'required|numeric',
            'choices' => 'required|array',
            'salt' => 'required|string',
            'sequence' => 'required|integer',
        ]);
        $this->ownOnly($r, $d['code']);

        $choices = array_map('intval', $d['choices']);
        $hash = Insaf::commit($d['code'], $choices, $d['salt']);
        $checks = [];

        // 1. Is my receipt really on Hedera?
        $checks[] = [
            'name' => 'My receipt matches the hash anchored on Hedera',
            'ok' => Insaf::readAnchor($d['sequence']) === 'c:' . $hash,
        ];

        // 2. Does the platform still hold what I submitted?
        $app = Student::where('code', $d['code'])->first()?->application;
        $checks[] = [
            'name' => 'The platform record matches my receipt',
            'ok' => $app && Insaf::commit($d['code'], $app->choices, $app->salt) === $hash,
        ];

        // 3. Is the database still the one committed before the run?
        $rootC = Commitment::where('kind', 'merkle_root')->latest('id')->first();
        $ok = false;
        if ($rootC) {
            $now = Insaf::merkleRoot(Application::with('student')->get()
                ->map(fn($a) => Insaf::commit($a->student->code, $a->choices, $a->salt))->all());
            $ok = Insaf::readAnchor($rootC->hcs_sequence) === 'merkle_root:' . $now;
        }
        $checks[] = ['name' => 'The database matches the Merkle root committed before the run', 'ok' => $ok];

        // 4. Does my placement respect the published cut-offs?
        $placed = $app?->placed_program_id ? (int) $app->placed_program_id : null;
        $ok = $placed === null || in_array($placed, $choices, true);
        foreach ($choices as $pid) {
            if ($pid === $placed) break;
            $p = Program::find($pid);
            $blocked = $p->admitted >= $p->capacity && $p->cutoff_score !== null &&
                ($d['score'] < $p->cutoff_score || ($d['score'] == $p->cutoff_score && $d['code'] > $p->cutoff_code));
            if (!$blocked) $ok = false;
        }
        $checks[] = ['name' => 'My placement is justified by my score and the published cut-offs', 'ok' => $ok];

        return ['checks' => $checks, 'valid' => collect($checks)->every(fn($c) => $c['ok'])];
    }
}