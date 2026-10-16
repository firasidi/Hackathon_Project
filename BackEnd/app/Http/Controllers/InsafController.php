<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\Commitment;
use App\Models\Program;
use App\Models\Student;
use App\Models\University;
use App\Services\Insaf;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InsafController extends Controller
{
    // PUBLIC: list of programs with seats and published cut-offs
    public function programs()
    {
        return Program::with('universities')->orderBy('id')->get();
    }

    // PUBLIC: everything the ministry anchored on Hedera
    public function commitments()
    {
        return Commitment::orderBy('id')->get();
    }

    public function dashboard(Request $r)
    {
        $student = $r->user()->loadMissing('application.university.specialty');
        $application = $student->application;
        $university = $application?->university;

        return [
            'profile' => [
                'code' => $student->code,
                'score' => $student->score,
                'name_fr' => $student->name_fr,
                'name_ar' => $student->name_ar,
                'institution_fr' => $student->institution_fr,
                'institution_ar' => $student->institution_ar,
                'email' => $student->email,
                'phone' => $student->phone,
                'city' => $student->city,
                'is_admin' => $student->is_admin,
            ],
            'programs' => Program::with('universities')->orderBy('id')->get(),
            'student' => [
                'code' => $student->code,
                'score' => $student->score,
                'placed' => $university?->name_fr,
                'placed_en' => $university?->name_en,
                'placed_specialty' => $university?->specialty?->name,
            ],
            'choices' => array_map('intval', $application?->choices ?? []),
            'receipt' => $this->receiptData($student, $application)['receipt'],
        ];
    }

    // The ministry and hacker buttons only exist for the demo
    private function demoOnly(): void
    {
        abort_unless(
            app()->environment(['local', 'development', 'testing']) &&
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
        $placedUniversity = $s->application?->university;

        return [
            'code' => $s->code,
            'score' => $s->score,
            'placed' => $placedUniversity?->name_fr,
            'placed_en' => $placedUniversity?->name_en,
            'placed_specialty' => $placedUniversity?->specialty?->name,
        ];
    }

    public function application(Request $r)
    {
        $choices = $r->user()->application?->choices ?? [];

        return [
            'choices' => array_map('intval', $choices),
        ];
    }

    public function receipt(Request $r)
    {
        return $this->receiptData($r->user(), $r->user()->application);
    }

    private function receiptData(Student $student, ?Application $application): array
    {
        if (
            !$application ||
            $application->receipt_sequence === null ||
            $application->receipt_choices === null ||
            !$application->receipt_salt ||
            !$application->receipt_hash ||
            !$application->receipt_submitted_at
        ) {
            return ['receipt' => null];
        }

        return [
            'receipt' => [
                'code' => $student->code,
                'score' => $student->score,
                'choices' => array_map('intval', $application->receipt_choices),
                'salt' => $application->receipt_salt,
                'hash' => $application->receipt_hash,
                'tx' => $application->receipt_transaction_id,
                'network' => $application->receipt_network ?? config('services.hedera.network'),
                'topicId' => $application->receipt_topic_id ?? config('services.hedera.topic_id'),
                'sequence' => (int) $application->receipt_sequence,
                'consensusTimestamp' => $application->receipt_consensus_timestamp,
                'submittedAt' => $application->receipt_submitted_at->toISOString(),
            ],
        ];
    }

    // The student submits ranked choices and receives a receipt anchored on Hedera
    public function apply(Request $r)
    {
        set_time_limit(120);

        $d = $r->validate([
            'choices' => 'required|array|min:3',
            'choices.*' => 'integer|distinct|exists:universities,id',
        ]);

        $s = $r->user(); // always the logged-in student, never a code sent by the browser
        $choices = array_map('intval', array_values(array_unique($d['choices'])));

        $selectsRetiredMedicineUniversity = University::query()
            ->whereIn('id', $choices)
            ->where('location_fr', 'Gabès, Tunisie')
            ->whereHas('specialty', fn($query) => $query->where('name', 'Médecine'))
            ->exists();

        if ($selectsRetiredMedicineUniversity) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'choices' => 'Cette université n’est plus disponible pour les nouveaux choix.',
            ]);
        }

        [$salt, $hash, $a, $submittedAt] = DB::transaction(function () use ($s, $choices): array {
            $student = Student::query()->whereKey($s->id)->lockForUpdate()->firstOrFail();
            $application = $student->application()->lockForUpdate()->first();

            abort_if(
                $application?->receipt_sequence !== null,
                409,
                'A Hedera receipt already exists for this submission. It cannot be overwritten.'
            );

            $salt = bin2hex(random_bytes(16));
            $hash = Insaf::commit($student->code, $choices, $salt);
            $anchor = Insaf::anchor('c:' . $hash);
            $submittedAt = now();

            Application::updateOrCreate(['student_id' => $student->id], [
                'choices' => $choices,
                'salt' => $salt,
                'commitment' => $hash,
                'receipt_choices' => $choices,
                'receipt_salt' => $salt,
                'receipt_hash' => $hash,
                'receipt_transaction_id' => $anchor['transactionId'],
                'receipt_network' => $anchor['network'],
                'receipt_topic_id' => $anchor['topicId'],
                'receipt_consensus_timestamp' => null,
                'receipt_sequence' => (int) $anchor['sequence'],
                'receipt_submitted_at' => $submittedAt,
                'placed_program_id' => null,
                'placed_university_id' => null,
            ]);

            return [$salt, $hash, $anchor, $submittedAt];
        });

        return [
            'code' => $s->code,
            'score' => $s->score,
            'choices' => $choices,
            'salt' => $salt,
            'hash' => $hash,
            'tx' => $a['transactionId'],
            'network' => $a['network'],
            'topicId' => $a['topicId'],
            'sequence' => (int) $a['sequence'],
            'consensusTimestamp' => null,
            'submittedAt' => $submittedAt->toISOString(),
        ];
    }

    // The ministry (demo only): commit rules, commit all inputs, run placement, commit results
    public function run()
    {
        $this->demoOnly();
        set_time_limit(180);

        $rules = json_encode([
            'formula' => 'score desc, tie by code asc, first university choice with a free seat',
            'capacities' => University::orderBy('id')->pluck('capacity', 'id'),
        ]);
        $this->commit('rules', hash('sha256', $rules));

        $leaves = Application::with('student')->get()
            ->map(fn($a) => Insaf::commit($a->student->code, $a->choices, $a->salt))->all();
        $this->commit('merkle_root', Insaf::merkleRoot($leaves));

        Insaf::place();

        $res = Application::with('student')->orderBy('id')->get()
            ->map(fn($a) => $a->student->code . ':' . $a->placed_university_id)->implode(',');
        $cut = University::orderBy('id')->get()
            ->map(fn($u) => "{$u->id}:{$u->admitted}:{$u->cutoff_score}:{$u->cutoff_code}")->implode(',');
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
            'placed_university_id' => $rev[0],
            'placed_program_id' => University::find($rev[0])?->specialty_id,
        ]);
        return ['tampered' => true];
    }

    // The student's check, done on their own receipt
    public function verify(Request $r)
    {
        $d = $r->validate([
            'code' => 'required|string',
            'score' => 'required|numeric',
            'choices' => 'required|array|min:3',
            'choices.*' => 'integer|distinct|exists:universities,id',
            'salt' => 'required|string',
            'sequence' => 'required|integer|min:1',
            'tx' => 'nullable|string|max:100',
        ]);
        $this->ownOnly($r, $d['code']);

        $student = $r->user();
        $app = $student->application;
        if (
            !$app ||
            $app->receipt_sequence === null ||
            $app->receipt_choices === null ||
            !$app->receipt_salt
        ) {
            return response()->json([
                'error' => 'receipt_not_found',
                'message' => 'No saved receipt is available to verify.',
            ], 409);
        }

        $choices = array_map('intval', $d['choices']);
        if (
            (int) $d['sequence'] !== (int) $app->receipt_sequence ||
            $choices !== array_map('intval', $app->receipt_choices) ||
            !hash_equals((string) $app->receipt_salt, $d['salt']) ||
            (($d['tx'] ?? null) !== null &&
                $app->receipt_transaction_id !== null &&
                $d['tx'] !== $app->receipt_transaction_id)
        ) {
            return response()->json([
                'error' => 'receipt_mismatch',
                'message' => 'The supplied receipt does not match the saved submission.',
            ], 422);
        }

        $topicId = $app->receipt_topic_id ?? config('services.hedera.topic_id');
        $configuredNetwork = config('services.hedera.network');
        if (
            $app->receipt_network !== null &&
            $app->receipt_network !== $configuredNetwork
        ) {
            return response()->json([
                'error' => 'receipt_network_mismatch',
                'message' => 'The receipt belongs to a different Hedera network.',
            ], 409);
        }

        $hash = Insaf::commit($d['code'], $choices, $d['salt']);
        try {
            $anchorRecord = Insaf::readAnchorRecord($d['sequence'], $topicId);
        } catch (ConnectionException|RequestException $e) {
            report($e);
            return response()->json([
                'error' => 'mirror_node_unavailable',
                'message' => 'The Hedera Mirror Node is temporarily unavailable. Please retry verification.',
            ], 503);
        } catch (\UnexpectedValueException $e) {
            report($e);
            return response()->json([
                'error' => 'invalid_mirror_node_record',
                'message' => 'The Mirror Node returned an invalid record. Verification was not completed.',
            ], 502);
        } catch (\RuntimeException $e) {
            report($e);
            return response()->json([
                'error' => 'mirror_node_misconfigured',
                'message' => 'The Hedera Mirror Node configuration is invalid. Verification was not completed.',
            ], 503);
        }

        if ($anchorRecord === null) {
            return response()->json([
                'error' => 'verification_pending',
                'pending' => true,
                'message' => 'The Hedera record is not available yet. Please retry verification shortly.',
            ], 202);
        }

        $anchorMessage = $anchorRecord['message'] ?? null;
        if (!is_string($anchorMessage) || !preg_match('/^c:([a-f0-9]{64})$/i', $anchorMessage, $matches)) {
            return response()->json([
                'error' => 'invalid_receipt_record',
                'message' => 'The Hedera record does not contain a valid submission fingerprint.',
            ], 422);
        }
        $anchoredHash = $matches[1];
        if (!empty($anchorRecord['consensus_timestamp'])) {
            $app->update([
                'receipt_consensus_timestamp' => $anchorRecord['consensus_timestamp'],
            ]);
        }

        $checks = [];

        // 1. Is my receipt really on Hedera?
        $checks[] = [
            'name' => 'My receipt matches the hash anchored on Hedera',
            'ok' => hash_equals('c:' . $hash, $anchorMessage),
        ];

        // 2. Does the platform still hold what I submitted?
        $currentHash = $app
            ? Insaf::commit($d['code'], $app->choices, $app->salt)
            : null;
        $checks[] = [
            'name' => 'The platform record matches my receipt',
            'ok' => $currentHash === $hash,
        ];

        $response = [
            'checks' => $checks,
            'valid' => collect($checks)->every(fn($c) => $c['ok']),
        ];

        if ($app && $anchoredHash !== null && $currentHash !== $anchoredHash) {
            $originalChoices = array_map(
                'intval',
                $app->receipt_choices ?? $choices
            );
            $currentChoices = array_map('intval', $app->choices);
            $rankDifferences = [];
            foreach (array_unique(array_merge(
                array_keys($originalChoices),
                array_keys($currentChoices)
            )) as $rank) {
                $originalId = $originalChoices[$rank] ?? null;
                $currentId = $currentChoices[$rank] ?? null;
                if ($originalId !== $currentId) {
                    $rankDifferences[] = [
                        'rank' => $rank + 1,
                        'original' => $originalId,
                        'current' => $currentId,
                    ];
                }
            }

            $response['incident'] = [
                'id' => (string) Str::uuid(),
                'studentReference' => $d['code'],
                'submissionReference' => 'Hedera sequence ' . $d['sequence'],
                'originalChoices' => $originalChoices,
                'originalHash' => $anchoredHash,
                'originalHashSource' => 'Hedera record',
                'currentChoices' => $currentChoices,
                'currentHash' => $currentHash,
                'differences' => [
                    'byRank' => $rankDifferences,
                    'removed' => array_values(array_diff($originalChoices, $currentChoices)),
                    'added' => array_values(array_diff($currentChoices, $originalChoices)),
                ],
                'hedera' => [
                    'network' => $app->receipt_network ?? $configuredNetwork,
                    'topicId' => $anchorRecord['topic_id'],
                    'sequence' => $anchorRecord['sequence_number'] ?? $d['sequence'],
                    'consensusTimestamp' => $anchorRecord['consensus_timestamp'] ?? null,
                    'recordReference' => $anchorRecord['transaction_id']
                        ?? $anchorRecord['initial_transaction_id']
                        ?? null,
                ],
                'verificationTimestamp' => now()->toISOString(),
                'status' => 'Integrity mismatch — pending investigation.',
                'originalReceiptReference' => [
                    'transactionId' => $d['tx'] ?? $app->receipt_transaction_id,
                    'sequence' => $app->receipt_sequence ?? $d['sequence'],
                ],
            ];
        }

        return $response;
    }
}