<?php

namespace App\Services;

use App\Models\Application;
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

    public static function rulesHash(): string
    {
        $rules = json_encode([
            'formula' => 'score desc, tie by code asc, first university choice with a free seat',
            'capacities' => University::orderBy('id')->pluck('capacity', 'id'),
        ], JSON_THROW_ON_ERROR);

        return hash('sha256', $rules);
    }

    public static function preferencesMerkleRoot(): string
    {
        $leaves = Application::with('student')->get()
            ->map(fn($application) => self::commit(
                $application->student->code,
                $application->choices,
                $application->salt
            ))
            ->all();

        return self::merkleRoot($leaves);
    }

    public static function resultsHash(): string
    {
        $results = Application::with('student')->orderBy('id')->get()
            ->map(fn($application) => $application->student->code . ':' . $application->placed_university_id)
            ->implode(',');
        $cutoffs = University::orderBy('id')->get()
            ->map(fn($university) => "{$university->id}:{$university->admitted}:{$university->cutoff_score}:{$university->cutoff_code}")
            ->implode(',');

        return hash('sha256', $results . '|' . $cutoffs);
    }

    public static function recomputeCommitmentHash(string $kind): ?string
    {
        return match ($kind) {
            'rules' => self::rulesHash(),
            'merkle_root' => self::preferencesMerkleRoot(),
            'results' => self::resultsHash(),
            default => null,
        };
    }

    public static function hashscanUrls(string $network, ?string $topicId, ?string $transactionId): array
    {
        if (!in_array($network, ['testnet', 'mainnet'], true) ||
            !is_string($topicId) ||
            !preg_match('/^\d+\.\d+\.\d+$/', $topicId)) {
            return ['topic' => null, 'transaction' => null];
        }

        $baseUrl = 'https://hashscan.io/' . $network;
        $topicUrl = "{$baseUrl}/topic/{$topicId}";
        $transactionUrl = is_string($transactionId) &&
            preg_match('/^\d+\.\d+\.\d+@\d+\.\d+$/', $transactionId)
            ? "{$baseUrl}/transaction/{$transactionId}"
            : null;

        return ['topic' => $topicUrl, 'transaction' => $transactionUrl];
    }

    public static function mirrorNodeRecordUrl(?string $topicId, ?int $sequence): ?string
    {
        $mirrorNodeUrl = rtrim((string) config('services.hedera.mirror_node_url'), '/');
        if ($mirrorNodeUrl === '' ||
            !is_string($topicId) ||
            !preg_match('/^\d+\.\d+\.\d+$/', $topicId) ||
            !is_int($sequence) ||
            $sequence < 1) {
            return null;
        }

        return "{$mirrorNodeUrl}/api/v1/topics/{$topicId}/messages/{$sequence}";
    }

    // Write a message on Hedera by running our small Node script
    public static function anchor(string $message): array
    {
        $network = config('services.hedera.network');
        $topicId = config('services.hedera.topic_id');

        if (!in_array($network, ['testnet', 'mainnet'], true) ||
            !is_string($topicId) ||
            !preg_match('/^\d+\.\d+\.\d+$/', $topicId)) {
            throw new \RuntimeException('Hedera network and topic must be configured.');
        }

        $r = Process::path(base_path('hedera'))
            ->timeout(60)
            ->env([
                'SystemRoot' => getenv('SystemRoot') ?: 'C:\\Windows',
                'SYSTEMROOT' => getenv('SystemRoot') ?: 'C:\\Windows',
                'windir'     => getenv('windir') ?: 'C:\\Windows',
                'PATH'       => getenv('PATH') ?: getenv('Path'),
                'TEMP'       => getenv('TEMP') ?: 'C:\\Windows\\Temp',
                'TMP'        => getenv('TMP') ?: 'C:\\Windows\\Temp',
                'HEDERA_NETWORK' => $network,
                'TOPIC_ID' => $topicId,
            ])
            ->run(['node', 'send.js', $message]);

        if ($r->failed() || !trim($r->output())) {
            throw new \RuntimeException('Hedera anchoring process failed.');
        }

        $lines = array_filter(array_map('trim', explode("\n", $r->output())));
        $result = json_decode((string) end($lines), true);
        if (
            !is_array($result) ||
            !is_string($result['transactionId'] ?? null) ||
            !is_numeric($result['sequence'] ?? null) ||
            (int) $result['sequence'] < 1 ||
            ($result['topicId'] ?? null) !== $topicId ||
            ($result['network'] ?? null) !== $network
        ) {
            throw new \RuntimeException('Hedera anchoring returned an invalid receipt.');
        }

        return [
            'transactionId' => $result['transactionId'],
            'sequence' => (int) $result['sequence'],
            'topicId' => $topicId,
            'network' => $network,
        ];
    }

    // Read a message back from Hedera's public Mirror Node
    public static function readAnchor(int $seq): ?string
    {
        return self::readAnchorRecord($seq)['message'] ?? null;
    }

    public static function readAnchorRecord(int $seq, ?string $topicId = null): ?array
    {
        $topicId ??= config('services.hedera.topic_id');
        $mirrorNodeUrl = rtrim((string) config('services.hedera.mirror_node_url'), '/');

        if (
            $seq < 1 ||
            !is_string($topicId) ||
            !preg_match('/^\d+\.\d+\.\d+$/', $topicId) ||
            $mirrorNodeUrl === ''
        ) {
            throw new \RuntimeException('Hedera Mirror Node configuration is invalid.');
        }

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $response = Http::connectTimeout(3)
                ->timeout(8)
                ->acceptJson()
                ->get("{$mirrorNodeUrl}/api/v1/topics/{$topicId}/messages/{$seq}");

            if ($response->status() === 404) {
                if ($attempt < 2) {
                    usleep(500_000);
                }
                continue;
            }

            $response->throw();
            $record = $response->json();
            if (!is_array($record)) {
                throw new \UnexpectedValueException('Mirror Node returned invalid JSON.');
            }
            if (
                ($record['topic_id'] ?? null) !== $topicId ||
                !is_numeric($record['sequence_number'] ?? null) ||
                (int) $record['sequence_number'] !== $seq
            ) {
                throw new \UnexpectedValueException('Mirror Node returned a different topic message.');
            }

            $encodedMessage = $record['message'] ?? null;
            if (!is_string($encodedMessage) || ($message = base64_decode($encodedMessage, true)) === false) {
                throw new \UnexpectedValueException('Mirror Node returned an invalid message encoding.');
            }

            $record['message'] = $message;
            return $record;
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