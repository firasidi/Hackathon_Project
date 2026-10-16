<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\Commitment;
use App\Models\PendingPreferenceSubmission;
use App\Models\PreferenceDraft;
use App\Models\Program;
use App\Models\Student;
use App\Models\University;
use App\Mail\PreferenceSubmissionCodeMail;
use App\Services\Insaf;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class InsafController extends Controller
{
    private const DEMO_STUDENT_CODE = 'S001';

    // PUBLIC: list of programs with seats and published cut-offs
    public function programs()
    {
        return Program::with('universities')->orderBy('id')->get();
    }

    // PUBLIC: everything the ministry anchored on Hedera
    public function commitments()
    {
        $latestIds = $this->latestCommitmentIds();

        return $this->orderedCommitments($latestIds)->map(function (Commitment $commitment) use ($latestIds): array {
            $network = $commitment->hedera_network ?? config('services.hedera.network');
            $topicId = $commitment->hedera_topic_id ?? config('services.hedera.topic_id');
            $urls = Insaf::hashscanUrls($network, $topicId, $commitment->hcs_tx_id);

            return [
                ...$commitment->toArray(),
                'status' => ($latestIds[$commitment->kind] ?? null) === $commitment->id
                    ? 'current'
                    : 'superseded',
                'hashscanUrl' => $urls['transaction'] ?? $urls['topic'],
                'rawRecordUrl' => Insaf::mirrorNodeRecordUrl($topicId, (int) $commitment->hcs_sequence),
            ];
        });
    }

    public function verifyCommitments()
    {
        $latestIds = $this->latestCommitmentIds();
        $commitments = $this->orderedCommitments($latestIds)->map(function (Commitment $commitment) use ($latestIds): array {
            $network = $commitment->hedera_network ?? config('services.hedera.network');
            $topicId = $commitment->hedera_topic_id ?? config('services.hedera.topic_id');
            $urls = Insaf::hashscanUrls($network, $topicId, $commitment->hcs_tx_id);
            $rawRecordUrl = Insaf::mirrorNodeRecordUrl($topicId, (int) $commitment->hcs_sequence);
            $recomputedHash = null;
            $anchoredHash = null;

            if (($latestIds[$commitment->kind] ?? null) !== $commitment->id) {
                return [
                    'kind' => $commitment->kind,
                    'network' => $network,
                    'topicId' => $topicId,
                    'sequence' => (int) $commitment->hcs_sequence,
                    'transactionId' => $commitment->hcs_tx_id,
                    'consensusTimestamp' => null,
                    'anchoredHash' => null,
                    'storedHash' => $commitment->hash,
                    'recomputedHash' => null,
                    'hashscanUrl' => $urls['transaction'] ?? $urls['topic'],
                    'topicHashscanUrl' => $urls['topic'],
                    'rawRecordUrl' => $rawRecordUrl,
                    'status' => 'superseded',
                ];
            }

            if (!in_array($network, ['testnet', 'mainnet'], true) ||
                $network !== config('services.hedera.network')) {
                return [
                    'kind' => $commitment->kind,
                    'network' => $network,
                    'topicId' => $topicId,
                    'sequence' => (int) $commitment->hcs_sequence,
                    'transactionId' => $commitment->hcs_tx_id,
                    'consensusTimestamp' => null,
                    'anchoredHash' => null,
                    'storedHash' => $commitment->hash,
                    'recomputedHash' => Insaf::recomputeCommitmentHash($commitment->kind),
                    'hashscanUrl' => $urls['transaction'] ?? $urls['topic'],
                    'topicHashscanUrl' => $urls['topic'],
                    'rawRecordUrl' => $rawRecordUrl,
                    'status' => 'unavailable',
                ];
            }

            try {
                $recomputedHash = Insaf::recomputeCommitmentHash($commitment->kind);
                $record = Insaf::readAnchorRecord((int) $commitment->hcs_sequence, $topicId);
            } catch (ConnectionException|RequestException|\UnexpectedValueException|\RuntimeException $exception) {
                report($exception);

                return [
                    'kind' => $commitment->kind,
                    'network' => $network,
                    'topicId' => $topicId,
                    'sequence' => (int) $commitment->hcs_sequence,
                    'transactionId' => $commitment->hcs_tx_id,
                    'consensusTimestamp' => null,
                    'anchoredHash' => null,
                    'storedHash' => $commitment->hash,
                    'recomputedHash' => $recomputedHash,
                    'hashscanUrl' => $urls['transaction'] ?? $urls['topic'],
                    'topicHashscanUrl' => $urls['topic'],
                    'rawRecordUrl' => $rawRecordUrl,
                    'status' => 'unavailable',
                ];
            }

            if ($recomputedHash === null) {
                return [
                    'kind' => $commitment->kind,
                    'network' => $network,
                    'topicId' => $topicId,
                    'sequence' => (int) $commitment->hcs_sequence,
                    'transactionId' => $commitment->hcs_tx_id,
                    'consensusTimestamp' => null,
                    'anchoredHash' => null,
                    'storedHash' => $commitment->hash,
                    'recomputedHash' => null,
                    'hashscanUrl' => $urls['transaction'] ?? $urls['topic'],
                    'topicHashscanUrl' => $urls['topic'],
                    'rawRecordUrl' => $rawRecordUrl,
                    'status' => 'unavailable',
                ];
            }

            if ($record === null) {
                $status = 'pending';
            } else {
                $expectedMessage = "{$commitment->kind}:{$commitment->hash}";
                $message = $record['message'] ?? '';
                $chainMatches = is_string($message) && hash_equals($expectedMessage, $message);
                if (is_string($message) &&
                    preg_match('/^' . preg_quote($commitment->kind, '/') . ':([a-f0-9]{64})$/i', $message, $matches)) {
                    $anchoredHash = $matches[1];
                }

                $status = !$chainMatches
                    ? 'chain_mismatch'
                    : (is_string($recomputedHash) && hash_equals($commitment->hash, $recomputedHash)
                        ? 'verified'
                        : 'data_changed');
            }

            return [
                'kind' => $commitment->kind,
                'network' => $network,
                'topicId' => $topicId,
                'sequence' => (int) $commitment->hcs_sequence,
                'transactionId' => $record['transaction_id'] ?? $commitment->hcs_tx_id,
                'consensusTimestamp' => $record['consensus_timestamp'] ?? null,
                'anchoredHash' => $anchoredHash,
                'storedHash' => $commitment->hash,
                'recomputedHash' => $recomputedHash,
                'hashscanUrl' => $urls['transaction'] ?? $urls['topic'],
                'topicHashscanUrl' => $urls['topic'],
                'rawRecordUrl' => $rawRecordUrl,
                'status' => $status,
            ];
        });

        return ['commitments' => $commitments];
    }

    private function latestCommitmentIds(): array
    {
        return Commitment::query()
            ->selectRaw('kind, MAX(id) AS latest_id')
            ->groupBy('kind')
            ->pluck('latest_id', 'kind')
            ->map(fn($id) => (int) $id)
            ->all();
    }

    private function orderedCommitments(array $latestIds)
    {
        $kindOrder = ['rules' => 0, 'merkle_root' => 1, 'results' => 2];

        return Commitment::query()->get()
            ->sortBy(fn(Commitment $commitment) => [
                ($latestIds[$commitment->kind] ?? null) === $commitment->id ? 0 : 1,
                $kindOrder[$commitment->kind] ?? 99,
                -$commitment->id,
            ])
            ->values();
    }

    public function dashboard(Request $r)
    {
        $student = $r->user()->loadMissing('application.university.specialty');
        $application = $student->application;
        $pendingSubmission = PendingPreferenceSubmission::where('student_id', $student->id)->first();
        if ($pendingSubmission?->expires_at->isPast()) {
            $pendingSubmission->delete();
            $pendingSubmission = null;
        }
        $draft = PreferenceDraft::where('student_id', $student->id)->first();
        $savedChoices = $draft?->choices ?? $application?->choices ?? [];
        $university = $application?->university;
        $demoApplication = $student->is_admin && $this->demoModeEnabled()
            ? Student::where('code', self::DEMO_STUDENT_CODE)
                ->whereHas('application', fn($query) => $query
                    ->whereNotNull('receipt_sequence')
                    ->whereNotNull('receipt_choices')
                    ->whereNotNull('receipt_salt'))
                ->exists()
            : false;

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
            'choices' => array_map('intval', $savedChoices),
            'draftSaved' => $draft !== null,
            'preferenceConfirmation' => $pendingSubmission ? [
                'emailHint' => $this->maskEmail($pendingSubmission->email),
            ] : null,
            'receipt' => $this->receiptData($student, $application)['receipt'],
            'demoModeEnabled' => $student->is_admin && $this->demoModeEnabled(),
            'demoTamperAvailable' => $demoApplication,
        ];
    }

    private function demoModeEnabled(): bool
    {
        return app()->environment(['local', 'development', 'testing']) &&
            filter_var(config('services.hedera.demo_mode'), FILTER_VALIDATE_BOOLEAN);
    }

    // Administrator placement and synthetic tampering are restricted to demonstrations.
    private function demoOnly(): void
    {
        abort_unless(
            $this->demoModeEnabled(),
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
        $student = $r->user();
        $draft = PreferenceDraft::where('student_id', $student->id)->first();
        $choices = $draft?->choices ?? $student->application?->choices ?? [];

        return [
            'choices' => array_map('intval', $choices),
        ];
    }

    public function savePreferenceDraft(Request $r)
    {
        $d = $r->validate([
            'choices' => 'required|array|max:40',
            'choices.*' => 'integer|distinct|exists:universities,id',
        ]);
        $s = $r->user();
        $choices = array_map('intval', array_values($d['choices']));

        $retiredChoice = University::query()
            ->whereIn('id', $choices)
            ->where('location_fr', 'Gabès, Tunisie')
            ->whereHas('specialty', fn($query) => $query->where('name', 'Médecine'))
            ->exists();
        if ($retiredChoice) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'choices' => 'Cette université n’est plus disponible pour les nouveaux choix.',
            ]);
        }

        DB::transaction(function () use ($s, $choices): void {
            $student = Student::query()->whereKey($s->id)->lockForUpdate()->firstOrFail();
            abort_if(
                $student->application()->lockForUpdate()->value('receipt_sequence') !== null,
                409,
                'Preferences cannot be changed after submission.'
            );

            $pending = PendingPreferenceSubmission::where('student_id', $student->id)->lockForUpdate()->first();
            if ($pending?->expires_at->isPast()) {
                $pending->delete();
                $pending = null;
            }
            abort_if($pending, 409, 'Preferences are locked until email confirmation is completed.');

            PreferenceDraft::updateOrCreate(
                ['student_id' => $student->id],
                ['choices' => $choices]
            );
        });

        return ['choices' => $choices, 'saved' => true, 'submitted' => false];
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

        $network = $application->receipt_network ?? config('services.hedera.network');
        $topicId = $application->receipt_topic_id ?? config('services.hedera.topic_id');
        $hashscanUrls = Insaf::hashscanUrls(
            $network,
            $topicId,
            $application->receipt_transaction_id
        );
        $rawRecordUrl = Insaf::mirrorNodeRecordUrl($topicId, (int) $application->receipt_sequence);

        return [
            'receipt' => [
                'code' => $student->code,
                'score' => $student->score,
                'choices' => array_map('intval', $application->receipt_choices),
                'salt' => $application->receipt_salt,
                'hash' => $application->receipt_hash,
                'tx' => $application->receipt_transaction_id,
                'network' => $network,
                'topicId' => $topicId,
                'hashscanUrl' => $hashscanUrls['transaction'] ?? $hashscanUrls['topic'],
                'topicHashscanUrl' => $hashscanUrls['topic'],
                'rawRecordUrl' => $rawRecordUrl,
                'sequence' => (int) $application->receipt_sequence,
                'consensusTimestamp' => $application->receipt_consensus_timestamp,
                'submittedAt' => $application->receipt_submitted_at->toISOString(),
            ],
        ];
    }

    // The student submits ranked choices and receives a receipt anchored on Hedera
    public function apply(Request $r)
    {
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

        if (!filter_var($s->email, FILTER_VALIDATE_EMAIL)) {
            return response()->json([
                'error' => 'current_email_unavailable',
                'message' => 'A valid registered email address is required to confirm your preferences.',
            ], 422);
        }
        if (!app()->environment('testing') && in_array(config('mail.default'), ['log', 'array'], true)) {
            return response()->json([
                'error' => 'email_delivery_unconfigured',
                'message' => 'Email delivery is not configured. Your preferences were not submitted.',
            ], 503);
        }

        $key = 'preference-submit:' . $s->id;
        if (RateLimiter::tooManyAttempts($key, 3)) {
            return response()->json(['error' => 'too_many', 'message' => 'Too many requests. Try again later.'], 429);
        }
        RateLimiter::hit($key, 600);

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $pending = DB::transaction(function () use ($s, $choices, $code): PendingPreferenceSubmission {
            $student = Student::query()->whereKey($s->id)->lockForUpdate()->firstOrFail();
            $application = $student->application()->lockForUpdate()->first();
            abort_if(
                $application?->receipt_sequence !== null,
                409,
                'A Hedera receipt already exists for this submission. It cannot be overwritten.'
            );

            $draft = PreferenceDraft::where('student_id', $student->id)->lockForUpdate()->first();
            abort_if(
                $draft === null || $choices !== array_map('intval', $draft->choices),
                409,
                'Save your latest preferences before requesting email confirmation.'
            );

            $existingPending = PendingPreferenceSubmission::where('student_id', $student->id)
                ->lockForUpdate()
                ->first();
            if ($existingPending?->expires_at->isPast()) {
                $existingPending->delete();
                $existingPending = null;
            }
            abort_if(
                $existingPending,
                409,
                'Preferences are already locked while awaiting email confirmation.'
            );

            return PendingPreferenceSubmission::create([
                'student_id' => $student->id,
                'email' => $s->email,
                'choices' => $choices,
                'code_hash' => Hash::make($code),
                'expires_at' => now()->addMinutes(10),
                'attempts' => 0,
            ]);
        });

        try {
            Mail::to($pending->email)->send(new PreferenceSubmissionCodeMail($code));
        } catch (\Throwable $exception) {
            report($exception);
            PendingPreferenceSubmission::whereKey($pending->id)
                ->where('code_hash', $pending->code_hash)
                ->delete();

            return response()->json([
                'error' => 'email_delivery_failed',
                'message' => 'We could not send a confirmation code. Your preferences were not submitted.',
            ], 503);
        }

        return [
            'confirmationRequired' => true,
            'emailHint' => $this->maskEmail($pending->email),
            'expiresInMinutes' => 10,
        ];
    }

    public function confirmApply(Request $r)
    {
        $d = $r->validate(['code' => ['required', 'digits:6']]);
        $s = $r->user();

        $pending = DB::transaction(function () use ($s, $d): ?PendingPreferenceSubmission {
            $submission = PendingPreferenceSubmission::where('student_id', $s->id)->lockForUpdate()->first();
            if (!$submission || $submission->expires_at->isPast() || $submission->attempts >= 5) {
                return null;
            }
            if (!hash_equals((string) $s->email, (string) $submission->email)) {
                $submission->delete();
                return null;
            }
            if (!Hash::check($d['code'], $submission->code_hash)) {
                $submission->increment('attempts');
                return null;
            }

            return $submission;
        });

        if (!$pending) {
            return response()->json([
                'error' => 'invalid_code',
                'message' => 'The confirmation code is invalid or expired.',
            ], 422);
        }

        $receipt = $this->anchorSubmission($s, array_map('intval', $pending->choices));
        PreferenceDraft::where('student_id', $s->id)->delete();
        $pending->delete();
        RateLimiter::clear('preference-submit:' . $s->id);

        return $receipt;
    }

    public function resendApplyCode(Request $r)
    {
        $s = $r->user();
        $key = 'preference-submit-resend:' . $s->id;
        if (RateLimiter::tooManyAttempts($key, 3)) {
            return response()->json(['error' => 'too_many', 'message' => 'Too many requests. Try again later.'], 429);
        }

        $pending = PendingPreferenceSubmission::where('student_id', $s->id)->first();
        if (!$pending || $pending->expires_at->isPast() ||
            !hash_equals((string) $s->email, (string) $pending->email)) {
            $pending?->delete();
            return response()->json([
                'error' => 'confirmation_expired',
                'message' => 'The confirmation request expired. Submit your saved preferences again.',
            ], 409);
        }
        RateLimiter::hit($key, 600);

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $pending->update([
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(10),
            'attempts' => 0,
        ]);

        try {
            Mail::to($pending->email)->send(new PreferenceSubmissionCodeMail($code));
        } catch (\Throwable $exception) {
            report($exception);
            return response()->json([
                'error' => 'email_delivery_failed',
                'message' => 'We could not send a confirmation code. Your preferences remain locked.',
            ], 503);
        }

        return [
            'emailHint' => $this->maskEmail($pending->email),
            'expiresInMinutes' => 10,
        ];
    }

    private function maskEmail(string $email): string
    {
        [$localPart, $domain] = explode('@', $email, 2);

        return substr($localPart, 0, 1) .
            str_repeat('*', max(2, min(6, strlen($localPart) - 1))) .
            '@' . $domain;
    }

    private function anchorSubmission(Student $s, array $choices): array
    {
        set_time_limit(120);

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
        $hashscanUrls = Insaf::hashscanUrls(
            $a['network'],
            $a['topicId'],
            $a['transactionId']
        );
        $rawRecordUrl = Insaf::mirrorNodeRecordUrl($a['topicId'], (int) $a['sequence']);

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
            'hashscanUrl' => $hashscanUrls['transaction'] ?? $hashscanUrls['topic'],
            'rawRecordUrl' => $rawRecordUrl,
            'consensusTimestamp' => null,
            'submittedAt' => $submittedAt->toISOString(),
        ];
    }

    // The ministry (demo only): commit rules, commit all inputs, run placement, commit results
    public function run()
    {
        $this->demoOnly();
        set_time_limit(180);

        $this->commit('rules', Insaf::rulesHash());
        $this->commit('merkle_root', Insaf::preferencesMerkleRoot());

        Insaf::place();
        $this->commit('results', Insaf::resultsHash());

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
            'hedera_network' => $a['network'],
            'hedera_topic_id' => $a['topicId'],
        ]);
    }

    // The demo administrator can alter only the seeded synthetic student record.
    public function tamper(Request $r, $code)
    {
        $this->demoOnly();

        abort_unless($code === self::DEMO_STUDENT_CODE, 404);
        $demoStudent = Student::where('code', self::DEMO_STUDENT_CODE)->firstOrFail();
        $a = $demoStudent->application()->firstOrFail();
        abort_unless(
            $a->receipt_sequence !== null &&
            is_array($a->receipt_choices) &&
            count($a->receipt_choices) >= 2 &&
            is_string($a->receipt_salt) &&
            $a->receipt_salt !== '',
            409,
            'The demo student must have a saved Hedera receipt before tampering can be demonstrated.'
        );

        $before = array_map('intval', $a->choices);
        $originalChoices = array_map('intval', $a->receipt_choices);
        $after = array_merge(array_slice($originalChoices, 1), [$originalChoices[0]]);
        $tamperedPlacement = collect($after)
            ->first(fn($universityId) => (int) $universityId !== (int) $a->placed_university_id)
            ?? $after[0];
        $a->update([
            'choices' => $after,
            'commitment' => Insaf::commit($demoStudent->code, $after, $a->salt),
            'placed_university_id' => $tamperedPlacement,
            'placed_program_id' => University::find($tamperedPlacement)?->specialty_id,
        ]);

        return [
            'tampered' => true,
            'code' => $demoStudent->code,
            'beforeChoices' => $before,
            'afterChoices' => $after,
            'receipt' => [
                'code' => $demoStudent->code,
                'score' => $demoStudent->score,
                'choices' => $originalChoices,
                'salt' => $a->receipt_salt,
                'sequence' => (int) $a->receipt_sequence,
                'tx' => $a->receipt_transaction_id,
                'network' => $a->receipt_network ?? config('services.hedera.network'),
                'topicId' => $a->receipt_topic_id ?? config('services.hedera.topic_id'),
                'rawRecordUrl' => Insaf::mirrorNodeRecordUrl(
                    $a->receipt_topic_id ?? config('services.hedera.topic_id'),
                    (int) $a->receipt_sequence
                ),
            ],
        ];
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
        $isDemoAdministrator = $r->user()->is_admin &&
            $this->demoModeEnabled() &&
            $d['code'] === self::DEMO_STUDENT_CODE;
        if (!$isDemoAdministrator) {
            $this->ownOnly($r, $d['code']);
        }

        $student = $isDemoAdministrator
            ? Student::where('code', self::DEMO_STUDENT_CODE)->firstOrFail()
            : $r->user();
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