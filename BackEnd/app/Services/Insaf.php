<?php

namespace App\Services;

use App\Models\Application;
use App\Models\Program;
use App\Models\University;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;

class Insaf
{
    // Fingerprint of a student's ranked choices, mixed with their secret salt
    public static function commit(string $code, array $choices, string $salt): string
    {
        return hash('sha256', $salt . '|' . $code . '|' . implode(',', array_map('intval', $choices)));
    }

    // One single hash that represents ALL applications (a Merkle root)
    public static function merkleRoot(array $leaves): string
    {
        if (!$leaves) return hash('sha256', '');
        sort($leaves);
        while (count($leaves) > 1) {
            if (count($leaves) % 2) $leaves[] = end($leaves);
            $next = [];
            for ($i = 0; $i < count($leaves); $i += 2) {
                $next[] = hash('sha256', $leaves[$i] . $leaves[$i + 1]);
            }
            $leaves = $next;
        }
        return $leaves[0];
    }

    // Write a message on Hedera by running our small Node script
    public static function anchor(string $message): array
{
    $r = Process::path(base_path('hedera'))
        ->timeout(60)
        ->env([
            'SystemRoot' => getenv('SystemRoot') ?: 'C:\\Windows',
            'SYSTEMROOT' => getenv('SystemRoot') ?: 'C:\\Windows',
            'windir'     => getenv('windir') ?: 'C:\\Windows',
            'PATH'       => getenv('PATH') ?: getenv('Path'),
            'TEMP'       => getenv('TEMP') ?: 'C:\\Windows\\Temp',
            'TMP'        => getenv('TMP') ?: 'C:\\Windows\\Temp',
        ])
        ->run(['node', 'send.js', $message]);

    if ($r->failed() || !trim($r->output())) {
        throw new \RuntimeException('send.js failed. exit=' . $r->exitCode() . ' err=' . $r->errorOutput() . ' out=' . $r->output());
    }
    $lines = array_filter(array_map('trim', explode("\n", $r->output())));
    return json_decode(end($lines), true);
}

    // Read a message back from Hedera's public Mirror Node
    public static function readAnchor(int $seq): ?string
    {
        return self::readAnchorRecord($seq)['message'] ?? null;
    }

    public static function readAnchorRecord(int $seq): ?array
    {
        $topic = env('HEDERA_TOPIC_ID');
        for ($i = 0; $i < 4; $i++) {
            $res = Http::get("https://testnet.mirrornode.hedera.com/api/v1/topics/{$topic}/messages/{$seq}");
            if ($res->ok()) {
                $record = $res->json();
                $record['message'] = base64_decode($record['message'] ?? '', true) ?: '';
                return $record;
            }
            sleep(2); // the Mirror Node can lag a few seconds
        }
        return null;
    }

    // Placement: highest score first, each student gets their best choice with a free seat
    public static function place(): void
    {
        $universities = University::all();
        $left = $universities->pluck('capacity', 'id')->all();
        $last = [];

        $apps = Application::with('student')->get()->all();
        usort($apps, fn($a, $b) =>
            [$b->student->score, $a->student->code] <=> [$a->student->score, $b->student->code]);

        foreach ($apps as $a) {
            $placed = null;
            foreach ($a->choices as $universityId) {
                if (($left[$universityId] ?? 0) > 0) {
                    $left[$universityId]--;
                    $placed = $universityId;
                    $last[$universityId] = $a->student; // the last admitted defines the cut-off
                    break;
                }
            }
            $university = $placed ? $universities->firstWhere('id', $placed) : null;
            $a->update([
                'placed_program_id' => $university?->specialty_id,
                'placed_university_id' => $placed,
            ]);
        }

        foreach ($universities as $university) {
            $s = $last[$university->id] ?? null;
            $university->update([
                'admitted'     => $university->capacity - $left[$university->id],
                'cutoff_score' => $s?->score,
                'cutoff_code'  => $s?->code,
            ]);
        }
    }
}