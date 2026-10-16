<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Commitment;
use App\Models\IncidentReport;
use App\Models\PendingPreferenceSubmission;
use App\Models\PreferenceDraft;
use App\Models\ProfileChangeCode;
use App\Models\Program;
use App\Models\ResetCode;
use App\Models\Student;
use App\Models\University;
use App\Mail\ProfileChangeCodeMail;
use App\Mail\PreferenceSubmissionCodeMail;
use App\Services\Insaf;
use Illuminate\Console\Command as ConsoleCommand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class UniversitySelectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.hedera.network' => 'testnet',
            'services.hedera.topic_id' => '0.0.123',
            'services.hedera.mirror_node_url' => 'https://testnet.mirrornode.hedera.com',
            'services.hedera.demo_mode' => false,
        ]);
    }

    public function test_each_specialty_lists_five_universities_and_placement_uses_selected_universities(): void
    {
        $this->seed();

        $response = $this->getJson('/api/programs')->assertOk();
        $specialties = $response->json();

        $this->assertCount(8, $specialties);
        $specialtyNamesEn = [
            'Médecine' => 'Medicine',
            'Informatique' => 'Computer Science',
            'Génie civil' => 'Civil Engineering',
            'Pharmacie' => 'Pharmacy',
            'Architecture' => 'Architecture',
            'Droit' => 'Law',
            'Économie' => 'Economics',
            'Lettres' => 'Literature',
        ];
        foreach ($specialties as $specialty) {
            $this->assertCount(5, $specialty['universities']);
            foreach ($specialty['universities'] as $university) {
                $this->assertStringContainsString($specialtyNamesEn[$specialty['name']], $university['name_en']);
                $this->assertStringNotContainsString('Demo', $university['name_en']);
                $this->assertStringNotContainsString('démo', $university['name_fr']);
            }
        }

        Insaf::place();

        foreach (Application::all() as $application) {
            if ($application->placed_university_id === null) {
                continue;
            }

            $this->assertContains(
                (int) $application->placed_university_id,
                array_map('intval', $application->choices)
            );
            $university = University::findOrFail($application->placed_university_id);
            $this->assertSame($university->specialty_id, $application->placed_program_id);
            $this->assertLessThanOrEqual($university->capacity, $university->admitted);
        }
    }

    public function test_authenticated_student_can_reload_their_persisted_ranked_choices(): void
    {
        $this->seed();
        $application = Application::with('student')->firstOrFail();
        Sanctum::actingAs($application->student);

        $this->getJson('/api/application')
            ->assertOk()
            ->assertExactJson([
                'choices' => array_map('intval', $application->choices),
            ]);
    }

    public function test_student_can_save_and_reload_unsubmitted_preference_draft(): void
    {
        $this->seed();
        $student = Student::where('code', 'S001')->firstOrFail();
        Sanctum::actingAs($student);
        $choices = University::query()->orderBy('id')->limit(4)->pluck('id')->map(fn($id) => (int) $id)->all();

        $this->putJson('/api/application/draft', ['choices' => $choices])
            ->assertOk()
            ->assertJsonPath('choices', $choices)
            ->assertJsonPath('saved', true)
            ->assertJsonPath('submitted', false);

        $this->assertSame($choices, array_map('intval', PreferenceDraft::where('student_id', $student->id)->firstOrFail()->choices));
        $this->assertNull($student->application?->receipt_sequence);
        $this->getJson('/api/application')->assertOk()->assertJsonPath('choices', $choices);
        $this->getJson('/api/dashboard')->assertOk()
            ->assertJsonPath('choices', $choices)
            ->assertJsonPath('draftSaved', true)
            ->assertJsonPath('receipt', null);
    }

    public function test_dashboard_bootstrap_returns_all_initial_student_data_in_one_request(): void
    {
        $this->seed();
        $application = Application::with('student')->firstOrFail();
        $university = University::where('location_fr', 'Tunis, Tunisie')
            ->whereHas('specialty', fn($query) => $query->where('name', 'Informatique'))
            ->firstOrFail();
        $application->update([
            'placed_university_id' => $university->id,
            'placed_program_id' => $university->specialty_id,
            'receipt_transaction_id' => '0.0.123@1700000000.000000001',
            'receipt_sequence' => 42,
            'receipt_submitted_at' => now(),
            'receipt_choices' => $application->choices,
            'receipt_salt' => $application->salt,
            'receipt_hash' => $application->commitment,
        ]);
        Sanctum::actingAs($application->student);

        $this->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('profile.code', $application->student->code)
            ->assertJsonPath('student.placed', $university->name_fr)
            ->assertJsonPath('student.placed_en', $university->name_en)
            ->assertJsonPath('student.placed_specialty', 'Informatique')
            ->assertJsonPath('choices', array_map('intval', $application->choices))
            ->assertJsonPath('receipt.sequence', 42)
            ->assertJsonCount(8, 'programs');
    }

    public function test_dashboard_bootstrap_requires_authentication(): void
    {
        $this->getJson('/api/dashboard')->assertUnauthorized();
    }

    public function test_profile_contact_changes_require_email_code_and_commit_only_after_confirmation(): void
    {
        $this->seed();
        $student = Student::firstOrFail();
        $currentEmail = 'student.current@example.test';
        $student->update(['email' => $currentEmail, 'phone' => '11112222', 'city' => 'Tunis']);
        Sanctum::actingAs($student);
        Mail::fake();

        $this->postJson('/api/profile/contact-change', [
            'email' => 'student.new@example.test',
            'phone' => '33334444',
            'city' => 'Sfax',
        ])
            ->assertOk()
            ->assertJsonPath('emailHint', 's******@example.test')
            ->assertJsonMissingPath('code');

        $pending = ProfileChangeCode::where('student_id', $student->id)->firstOrFail();
        $this->assertTrue(Hash::check(
            Mail::sent(ProfileChangeCodeMail::class)->first()->code,
            $pending->code_hash
        ));
        $this->assertSame($currentEmail, $pending->original_email);
        $this->assertSame($currentEmail, $student->fresh()->email);
        $this->assertSame('11112222', $student->fresh()->phone);
        Mail::assertSent(ProfileChangeCodeMail::class, fn($mail) => $mail->hasTo($currentEmail));

        $code = Mail::sent(ProfileChangeCodeMail::class)->first()->code;
        $this->postJson('/api/profile/contact-change/confirm', ['code' => $code])
            ->assertOk()
            ->assertJsonPath('email', 'student.new@example.test')
            ->assertJsonPath('phone', '33334444')
            ->assertJsonPath('city', 'Sfax');

        $this->assertSame('student.new@example.test', $student->fresh()->email);
        $this->assertSame('33334444', $student->fresh()->phone);
        $this->assertDatabaseMissing('profile_change_codes', ['student_id' => $student->id]);
    }

    public function test_phone_only_change_sends_code_to_current_email_and_keeps_email(): void
    {
        $this->seed();
        $student = Student::firstOrFail();
        $student->update(['email' => 'student.current@example.test', 'phone' => '11112222', 'city' => 'Tunis']);
        Sanctum::actingAs($student);
        Mail::fake();

        $this->postJson('/api/profile/contact-change', [
            'phone' => '99998888',
        ])->assertOk();

        Mail::assertSent(
            ProfileChangeCodeMail::class,
            fn($mail) => $mail->hasTo('student.current@example.test')
        );
        $this->assertSame('student.current@example.test', $student->fresh()->email);
        $this->assertSame('11112222', $student->fresh()->phone);
        $this->assertSame('Tunis', $student->fresh()->city);

        $code = Mail::sent(ProfileChangeCodeMail::class)->first()->code;
        $this->postJson('/api/profile/contact-change/confirm', ['code' => $code])
            ->assertOk()
            ->assertJsonPath('email', 'student.current@example.test')
            ->assertJsonPath('phone', '99998888')
            ->assertJsonPath('city', 'Tunis');
    }

    public function test_email_only_change_preserves_phone_and_city_when_omitted(): void
    {
        $this->seed();
        $student = Student::firstOrFail();
        $student->update([
            'email' => 'student.current@example.test',
            'phone' => '11112222',
            'city' => 'Tunis',
        ]);
        Sanctum::actingAs($student);
        Mail::fake();

        $this->postJson('/api/profile/contact-change', [
            'email' => 'student.new@example.test',
        ])->assertOk();

        $code = Mail::sent(ProfileChangeCodeMail::class)->first()->code;
        $this->postJson('/api/profile/contact-change/confirm', ['code' => $code])
            ->assertOk()
            ->assertJsonPath('email', 'student.new@example.test')
            ->assertJsonPath('phone', '11112222')
            ->assertJsonPath('city', 'Tunis');
    }

    public function test_contact_change_reports_an_in_use_email_without_mislabeling_it_as_invalid(): void
    {
        $this->seed();
        $students = Student::take(2)->get();
        $student = $students[0];
        $otherStudent = $students[1];
        $student->update(['email' => 'student.current@example.test']);
        $otherStudent->update(['email' => 'student.taken@example.test']);
        Sanctum::actingAs($student);
        Mail::fake();

        $this->postJson('/api/profile/contact-change', [
            'email' => 'student.taken@example.test',
            'phone' => null,
            'city' => null,
        ])->assertConflict()
            ->assertJsonPath('error', 'email_unavailable');

        $this->assertDatabaseMissing('profile_change_codes', ['student_id' => $student->id]);
        Mail::assertNothingSent();
    }

    public function test_contact_change_trims_surrounding_email_whitespace_before_validation(): void
    {
        $this->seed();
        $student = Student::firstOrFail();
        $student->update(['email' => 'student.current@example.test']);
        Sanctum::actingAs($student);
        Mail::fake();

        $this->postJson('/api/profile/contact-change', [
            'email' => ' student.new@example.test ',
            'phone' => null,
            'city' => null,
        ])->assertOk();

        $this->assertDatabaseHas('profile_change_codes', [
            'student_id' => $student->id,
            'pending_email' => 'student.new@example.test',
        ]);
        Mail::assertSent(ProfileChangeCodeMail::class);
    }

    public function test_direct_profile_update_cannot_bypass_contact_change_verification(): void
    {
        $this->seed();
        $student = Student::firstOrFail();
        $student->update(['email' => 'student.current@example.test', 'phone' => '11112222']);
        Sanctum::actingAs($student);

        $this->putJson('/api/profile', [
            'email' => 'student.new@example.test',
            'phone' => '33334444',
            'city' => 'Sfax',
        ])->assertUnprocessable()
            ->assertJsonPath('error', 'verification_required');

        $this->assertSame('student.current@example.test', $student->fresh()->email);
        $this->assertSame('11112222', $student->fresh()->phone);
    }

    public function test_profile_change_codes_are_separate_from_password_reset_codes(): void
    {
        $this->seed();
        $student = Student::firstOrFail();
        $student->update(['email' => 'student.current@example.test']);
        ResetCode::create([
            'student_id' => $student->id,
            'code_hash' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(10),
            'attempts' => 0,
        ]);
        Sanctum::actingAs($student);
        Mail::fake();

        $this->postJson('/api/profile/contact-change', [
            'email' => 'student.new@example.test',
            'phone' => null,
            'city' => null,
        ])->assertOk();

        $this->assertDatabaseHas('reset_codes', ['student_id' => $student->id]);
        $this->assertDatabaseHas('profile_change_codes', ['student_id' => $student->id]);
    }

    public function test_contact_change_is_not_accepted_when_mailer_would_only_log_the_code(): void
    {
        $this->seed();
        $student = Student::firstOrFail();
        $student->update(['email' => 'student.current@example.test']);
        Sanctum::actingAs($student);
        config(['mail.default' => 'log']);
        $this->app->detectEnvironment(fn() => 'local');

        $this->postJson('/api/profile/contact-change', [
            'email' => 'student.new@example.test',
            'phone' => null,
            'city' => null,
        ])->assertServiceUnavailable()
            ->assertJsonPath('error', 'email_delivery_unconfigured');

        $this->assertDatabaseMissing('profile_change_codes', ['student_id' => $student->id]);
        $this->assertSame('student.current@example.test', $student->fresh()->email);
    }

    public function test_profile_change_code_is_single_use_and_failed_codes_are_limited(): void
    {
        $this->seed();
        $student = Student::firstOrFail();
        $student->update(['email' => 'student.current@example.test']);
        Sanctum::actingAs($student);
        Mail::fake();

        $this->postJson('/api/profile/contact-change', [
            'email' => 'student.new@example.test',
            'phone' => '55556666',
            'city' => null,
        ])->assertOk();

        $actualCode = Mail::sent(ProfileChangeCodeMail::class)->first()->code;
        $wrongCode = str_pad((string) (((int) $actualCode + 1) % 1000000), 6, '0', STR_PAD_LEFT);
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/profile/contact-change/confirm', ['code' => $wrongCode])
                ->assertUnprocessable()
                ->assertJsonPath('error', 'invalid_code');
        }
        $this->postJson('/api/profile/contact-change/confirm', ['code' => $actualCode])
            ->assertUnprocessable()
            ->assertJsonPath('error', 'invalid_code');
        $this->assertSame('student.current@example.test', $student->fresh()->email);
    }

    public function test_cancelling_profile_change_invalidates_the_pending_code(): void
    {
        $this->seed();
        $student = Student::firstOrFail();
        $student->update(['email' => 'student.current@example.test']);
        Sanctum::actingAs($student);
        Mail::fake();

        $this->postJson('/api/profile/contact-change', [
            'email' => 'student.new@example.test',
            'phone' => null,
            'city' => null,
        ])->assertOk();
        $this->deleteJson('/api/profile/contact-change')->assertOk();

        $this->assertDatabaseMissing('profile_change_codes', ['student_id' => $student->id]);
        $this->postJson('/api/profile/contact-change/confirm', ['code' => '123456'])
            ->assertUnprocessable()
            ->assertJsonPath('error', 'invalid_code');
    }

    public function test_student_endpoint_returns_placement_name_in_both_languages(): void
    {
        $this->seed();
        $application = Application::with('student')->firstOrFail();
        $university = University::where('location_fr', 'Gabès, Tunisie')
            ->whereHas('specialty', fn($query) => $query->where('name', 'Lettres'))
            ->firstOrFail();
        $application->update([
            'placed_university_id' => $university->id,
            'placed_program_id' => $university->specialty_id,
        ]);
        Sanctum::actingAs($application->student);

        $this->getJson("/api/students/{$application->student->code}")
            ->assertOk()
            ->assertJsonPath('placed', 'Université de Lettres de Gabès')
            ->assertJsonPath('placed_en', 'Gabès Literature University');
    }

    public function test_authenticated_student_can_reload_their_saved_receipt_details(): void
    {
        $this->seed();
        $application = Application::with('student')->firstOrFail();
        $application->update([
            'receipt_transaction_id' => '0.0.123@1700000000.000000001',
            'receipt_network' => 'testnet',
            'receipt_topic_id' => '0.0.123',
            'receipt_consensus_timestamp' => '1700000000.000000001',
            'receipt_sequence' => 42,
            'receipt_submitted_at' => now(),
            'receipt_choices' => $application->choices,
            'receipt_salt' => $application->salt,
            'receipt_hash' => $application->commitment,
        ]);
        $receiptChoices = array_map('intval', $application->choices);
        $application->update([
            'choices' => array_reverse($application->choices),
            'commitment' => 'changed-application-commitment',
        ]);
        Sanctum::actingAs($application->student);

        $this->getJson('/api/receipt')
            ->assertOk()
            ->assertJsonPath('receipt.code', $application->student->code)
            ->assertJsonPath('receipt.choices', $receiptChoices)
            ->assertJsonPath('receipt.salt', $application->salt)
            ->assertJsonPath('receipt.hash', $application->getOriginal('receipt_hash'))
            ->assertJsonPath('receipt.tx', '0.0.123@1700000000.000000001')
            ->assertJsonPath('receipt.network', 'testnet')
            ->assertJsonPath('receipt.topicId', '0.0.123')
            ->assertJsonPath('receipt.sequence', 42)
            ->assertJsonPath('receipt.consensusTimestamp', '1700000000.000000001')
            ->assertJsonStructure(['receipt' => ['submittedAt']]);
    }

    public function test_receipt_endpoint_returns_null_until_a_hedera_receipt_has_been_saved(): void
    {
        $this->seed();
        $application = Application::with('student')->firstOrFail();
        Sanctum::actingAs($application->student);

        $this->getJson('/api/receipt')
            ->assertOk()
            ->assertExactJson(['receipt' => null]);
    }

    public function test_gabes_medicine_university_cannot_be_added_to_new_choices(): void
    {
        $this->seed();
        $application = Application::with('student')->firstOrFail();
        $gabesMedicine = University::where('location_fr', 'Gabès, Tunisie')
            ->whereHas('specialty', fn($query) => $query->where('name', 'Médecine'))
            ->firstOrFail();
        $choices = University::where('id', '!=', $gabesMedicine->id)
            ->limit(3)
            ->pluck('id')
            ->map(fn($id) => (int) $id)
            ->all();
        $choices[] = $gabesMedicine->id;
        Sanctum::actingAs($application->student);

        $this->postJson('/api/apply', ['choices' => $choices])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('choices');
    }

    public function test_student_submission_stores_the_hedera_receipt_metadata(): void
    {
        $this->seed();
        $student = \App\Models\Student::where('code', 'S001')->firstOrFail();
        $student->update(['email' => 'student.current@example.test']);
        $choices = University::query()
            ->orderBy('id')
            ->limit(3)
            ->pluck('id')
            ->map(fn($id) => (int) $id)
            ->all();
        Sanctum::actingAs($student);
        $this->putJson('/api/application/draft', ['choices' => $choices])->assertOk();
        Mail::fake();
        Process::fake([
            '*' => Process::result(json_encode([
                'transactionId' => '0.0.123@1700000000.000000001',
                'sequence' => '42',
                'topicId' => '0.0.123',
                'network' => 'testnet',
            ], JSON_THROW_ON_ERROR)),
        ]);

        $this->postJson('/api/apply', ['choices' => $choices])
            ->assertOk()
            ->assertJsonPath('confirmationRequired', true)
            ->assertJsonMissingPath('hash')
            ->assertJsonMissingPath('code');

        Mail::assertSent(PreferenceSubmissionCodeMail::class, fn($mail) => $mail->hasTo($student->email));
        $pending = PendingPreferenceSubmission::where('student_id', $student->id)->firstOrFail();
        $code = Mail::sent(PreferenceSubmissionCodeMail::class)->first()->code;
        $this->assertTrue(Hash::check($code, $pending->code_hash));
        $this->assertNull($student->application()->first()?->receipt_sequence);
        $this->assertDatabaseMissing('applications', ['student_id' => $student->id]);
        $this->assertSame($choices, array_map('intval', PreferenceDraft::where('student_id', $student->id)->firstOrFail()->choices));

        $response = $this->postJson('/api/apply/confirm', ['code' => $code])
            ->assertOk()
            ->assertJsonPath('network', 'testnet')
            ->assertJsonPath('topicId', '0.0.123')
            ->assertJsonPath('sequence', 42)
            ->assertJsonPath('consensusTimestamp', null);

        $application = Application::where('student_id', $student->id)->firstOrFail();
        $this->assertSame($choices, array_map('intval', $application->receipt_choices));
        $this->assertSame($response->json('hash'), $application->receipt_hash);
        $this->assertSame('testnet', $application->receipt_network);
        $this->assertSame('0.0.123', $application->receipt_topic_id);
        $this->assertSame(42, (int) $application->receipt_sequence);
        $this->assertNull($application->receipt_consensus_timestamp);
        Process::assertRan(fn($process) =>
            in_array('send.js', (array) $process->command, true) &&
            ($process->environment['TOPIC_ID'] ?? null) === '0.0.123'
        );
        $this->assertDatabaseMissing('pending_preference_submissions', ['student_id' => $student->id]);
    }

    public function test_preferences_are_not_anchored_until_email_confirmation_and_invalid_codes_do_not_submit(): void
    {
        $this->seed();
        $student = Student::where('code', 'S001')->firstOrFail();
        $student->update(['email' => 'student.current@example.test']);
        $choices = University::query()->orderBy('id')->limit(3)->pluck('id')->map(fn($id) => (int) $id)->all();
        Sanctum::actingAs($student);
        $this->putJson('/api/application/draft', ['choices' => $choices])->assertOk();
        Mail::fake();
        Process::fake();

        $this->postJson('/api/apply', ['choices' => $choices])
            ->assertOk()
            ->assertJsonPath('confirmationRequired', true);
        $actualCode = Mail::sent(PreferenceSubmissionCodeMail::class)->first()->code;
        $wrongCode = str_pad((string) (((int) $actualCode + 1) % 1000000), 6, '0', STR_PAD_LEFT);
        $this->postJson('/api/apply/confirm', ['code' => $wrongCode])
            ->assertUnprocessable()
            ->assertJsonPath('error', 'invalid_code');

        $this->assertNull($student->application()->first()?->receipt_sequence);
        $this->assertDatabaseMissing('applications', ['student_id' => $student->id]);
        $this->assertDatabaseHas('pending_preference_submissions', ['student_id' => $student->id]);
        $this->putJson('/api/application/draft', ['choices' => array_reverse($choices)])
            ->assertConflict()
            ->assertJsonPath('message', 'Preferences are locked until email confirmation is completed.');
        Process::assertNotRan(fn() => true);
    }

    public function test_pending_preference_submission_can_resend_code_without_changing_locked_draft(): void
    {
        $this->seed();
        $student = Student::where('code', 'S001')->firstOrFail();
        $student->update(['email' => 'student.current@example.test']);
        $choices = University::query()->orderBy('id')->limit(3)->pluck('id')->map(fn($id) => (int) $id)->all();
        Sanctum::actingAs($student);
        Mail::fake();
        Process::fake();

        $this->putJson('/api/application/draft', ['choices' => $choices])->assertOk();
        $this->postJson('/api/apply', ['choices' => $choices])->assertOk();
        $firstCode = Mail::sent(PreferenceSubmissionCodeMail::class)->first()->code;
        $this->postJson('/api/apply/resend')
            ->assertOk()
            ->assertJsonPath('emailHint', 's******@example.test');

        $this->assertDatabaseHas('pending_preference_submissions', ['student_id' => $student->id]);
        $this->assertDatabaseMissing('applications', ['student_id' => $student->id]);
        $this->assertSame($choices, array_map('intval', PreferenceDraft::where('student_id', $student->id)->firstOrFail()->choices));
        Mail::assertSent(PreferenceSubmissionCodeMail::class, 2);
        $secondCode = Mail::sent(PreferenceSubmissionCodeMail::class)->last()->code;
        $this->assertNotSame($firstCode, $secondCode);
        $this->postJson('/api/apply/confirm', ['code' => $firstCode])
            ->assertUnprocessable()
            ->assertJsonPath('error', 'invalid_code');
        Process::assertNotRan(fn() => true);
    }

    public function test_expired_preference_confirmation_can_be_restarted_from_the_saved_draft(): void
    {
        $this->seed();
        $student = Student::where('code', 'S001')->firstOrFail();
        $student->update(['email' => 'student.current@example.test']);
        $choices = University::query()->orderBy('id')->limit(3)->pluck('id')->map(fn($id) => (int) $id)->all();
        Sanctum::actingAs($student);
        Mail::fake();
        Process::fake();

        $this->putJson('/api/application/draft', ['choices' => $choices])->assertOk();
        $this->postJson('/api/apply', ['choices' => $choices])->assertOk();
        $expiredSubmission = PendingPreferenceSubmission::where('student_id', $student->id)->firstOrFail();
        $expiredSubmission->update(['expires_at' => now()->subMinute()]);

        $this->postJson('/api/apply', ['choices' => $choices])
            ->assertOk()
            ->assertJsonPath('confirmationRequired', true);

        $replacement = PendingPreferenceSubmission::where('student_id', $student->id)->firstOrFail();
        $this->assertNotSame($expiredSubmission->id, $replacement->id);
        $this->assertTrue($replacement->expires_at->isFuture());
        $this->assertSame($choices, array_map('intval', $replacement->choices));
        $this->assertSame($choices, array_map('intval', PreferenceDraft::where('student_id', $student->id)->firstOrFail()->choices));
        $this->assertDatabaseMissing('applications', ['student_id' => $student->id]);
        Mail::assertSent(PreferenceSubmissionCodeMail::class, 2);
        Process::assertNotRan(fn() => true);
    }

    public function test_preference_submission_is_not_accepted_when_mailer_only_logs(): void
    {
        $this->seed();
        $student = Student::where('code', 'S001')->firstOrFail();
        $student->update(['email' => 'student.current@example.test']);
        Sanctum::actingAs($student);
        config(['mail.default' => 'log']);
        $this->app->detectEnvironment(fn() => 'local');
        Mail::fake();

        $choices = University::query()->orderBy('id')->limit(3)->pluck('id')->map(fn($id) => (int) $id)->all();
        $this->putJson('/api/application/draft', ['choices' => $choices])->assertOk();
        $this->postJson('/api/apply', ['choices' => $choices])
            ->assertServiceUnavailable()
            ->assertJsonPath('error', 'email_delivery_unconfigured');

        $this->assertDatabaseMissing('pending_preference_submissions', ['student_id' => $student->id]);
        Mail::assertNothingSent();
    }

    public function test_resubmission_preserves_the_existing_hedera_receipt(): void
    {
        $this->seed();
        $application = Application::with('student')->firstOrFail();
        $originalReceipt = [
            'choices' => $application->choices,
            'salt' => $application->salt,
            'commitment' => $application->commitment,
            'receipt_choices' => $application->choices,
            'receipt_salt' => $application->salt,
            'receipt_hash' => $application->commitment,
            'receipt_transaction_id' => '0.0.123@1700000000.000000001',
            'receipt_network' => 'testnet',
            'receipt_topic_id' => '0.0.123',
            'receipt_sequence' => 42,
            'receipt_submitted_at' => now(),
        ];
        $application->update($originalReceipt);
        Sanctum::actingAs($application->student);

        $choices = University::query()
            ->whereNotIn('id', $application->choices)
            ->orderBy('id')
            ->limit(3)
            ->pluck('id')
            ->map(fn($id) => (int) $id)
            ->all();
        Process::fake();

        $this->postJson('/api/apply', ['choices' => $choices])
            ->assertStatus(409)
            ->assertJsonPath('message', 'A Hedera receipt already exists for this submission. It cannot be overwritten.');

        $application->refresh();
        $this->assertSame(array_map('intval', $originalReceipt['choices']), array_map('intval', $application->choices));
        $this->assertSame($originalReceipt['salt'], $application->salt);
        $this->assertSame($originalReceipt['commitment'], $application->commitment);
        $this->assertSame($originalReceipt['receipt_transaction_id'], $application->receipt_transaction_id);
        $this->assertSame($originalReceipt['receipt_network'], $application->receipt_network);
        $this->assertSame($originalReceipt['receipt_topic_id'], $application->receipt_topic_id);
        $this->assertSame(42, (int) $application->receipt_sequence);
        Process::assertNotRan(fn() => true);
    }

    public function test_verification_does_not_require_a_published_merkle_root(): void
    {
        $this->seed();
        $application = Application::with('student')->firstOrFail();
        $choices = array_map('intval', $application->choices);
        $this->prepareReceipt($application, $choices);
        $hash = Insaf::commit($application->student->code, $choices, $application->salt);
        Http::fake([
            '*' => Http::response([
                'message' => base64_encode('c:' . $hash),
                'topic_id' => '0.0.123',
                'sequence_number' => 42,
                'consensus_timestamp' => '1700000000.000000001',
                'transaction_id' => '0.0.123@1700000000.000000001',
            ]),
        ]);
        Sanctum::actingAs($application->student);

        $this->postJson('/api/verify', $this->verificationPayload($application, $choices))
            ->assertOk()
            ->assertJsonPath('valid', true)
            ->assertJsonCount(2, 'checks');

        $this->assertDatabaseHas('applications', [
            'id' => $application->id,
            'receipt_consensus_timestamp' => '1700000000.000000001',
        ]);
    }

    public function test_admin_run_anchors_shared_commitment_hashes_and_verifier_reads_them_back(): void
    {
        $this->seed();
        $demoStudent = \App\Models\Student::where('code', 'S001')->firstOrFail();
        $application = $this->createDemoApplication($demoStudent);
        $admin = $demoStudent;
        $admin->update(['is_admin' => true]);
        Sanctum::actingAs($admin->fresh());
        config(['services.hedera.demo_mode' => true]);
        Process::fake(['*' => Process::sequence([
            $this->processReceipt(81),
            $this->processReceipt(82),
            $this->processReceipt(83),
        ])]);

        $this->postJson('/api/admin/run')->assertOk();

        $this->assertSame(3, Commitment::count());
        $this->assertDatabaseHas('commitments', [
            'kind' => 'rules',
            'hash' => Insaf::rulesHash(),
            'hcs_sequence' => 81,
            'hedera_network' => 'testnet',
            'hedera_topic_id' => '0.0.123',
        ]);
        $this->assertDatabaseHas('commitments', [
            'kind' => 'merkle_root',
            'hash' => Insaf::preferencesMerkleRoot(),
            'hcs_sequence' => 82,
        ]);
        $this->assertDatabaseHas('commitments', [
            'kind' => 'results',
            'hash' => Insaf::resultsHash(),
            'hcs_sequence' => 83,
        ]);
        Process::assertRanTimes(
            fn($process) => in_array('send.js', (array) $process->command, true),
            3
        );

        Http::fake(fn($request) => $this->mirrorResponseForSequence(
            (int) basename(parse_url($request->url(), PHP_URL_PATH))
        ));
        $this->getJson('/api/commitments/verify')
            ->assertOk()
            ->assertJsonPath('commitments.0.status', 'verified')
            ->assertJsonPath('commitments.1.status', 'verified')
            ->assertJsonPath('commitments.2.status', 'verified')
            ->assertJsonPath('commitments.0.hashscanUrl', 'https://hashscan.io/testnet/transaction/0.0.123@1700000000.000000081')
            ->assertJsonMissingPath('commitments.0.choices')
            ->assertJsonMissingPath('commitments.0.salt');
    }

    public function test_commitment_verifier_reports_data_changes_after_demo_tampering(): void
    {
        $this->seed();
        $student = \App\Models\Student::where('code', 'S001')->firstOrFail();
        $application = $this->createDemoApplication($student);
        Insaf::place();
        $storedHashes = $this->createPlacementCommitments();

        $originalChoices = array_map('intval', $application->choices);
        $changedChoices = array_merge(array_slice($originalChoices, 1), [$originalChoices[0]]);
        $changedPlacement = collect($changedChoices)
            ->first(fn($id) => (int) $id !== (int) $application->placed_university_id);
        $application->update([
            'choices' => $changedChoices,
            'commitment' => Insaf::commit($student->code, $changedChoices, $application->salt),
            'placed_university_id' => $changedPlacement,
            'placed_program_id' => University::find($changedPlacement)?->specialty_id,
        ]);

        Http::fake(fn($request) => $this->mirrorResponseForSequence(
            (int) basename(parse_url($request->url(), PHP_URL_PATH))
        ));
        $response = $this->getJson('/api/commitments/verify')->assertOk();

        $this->assertSame('verified', $response->json('commitments.0.status'));
        $this->assertSame('data_changed', $response->json('commitments.1.status'));
        $this->assertSame('data_changed', $response->json('commitments.2.status'));
        $this->assertSame($storedHashes['merkle_root'], $response->json('commitments.1.storedHash'));
        $this->assertNotSame($storedHashes['merkle_root'], $response->json('commitments.1.recomputedHash'));
        $this->assertSame($storedHashes['results'], $response->json('commitments.2.storedHash'));
        $this->assertNotSame($storedHashes['results'], $response->json('commitments.2.recomputedHash'));
    }

    public function test_commitment_verifier_marks_older_runs_superseded_and_verifies_latest_run(): void
    {
        $this->seed();
        $student = Student::where('code', 'S001')->firstOrFail();
        $application = $this->createDemoApplication($student);
        Insaf::place();

        $oldHashes = [
            'rules' => Insaf::rulesHash(),
            'merkle_root' => Insaf::preferencesMerkleRoot(),
            'results' => Insaf::resultsHash(),
        ];
        foreach ($oldHashes as $index => $hash) {
            $this->createStoredCommitment($index, $hash, 201 + count(Commitment::all()));
        }

        $changedChoices = array_reverse(array_map('intval', $application->choices));
        $application->update([
            'choices' => $changedChoices,
            'commitment' => Insaf::commit($student->code, $changedChoices, $application->salt),
        ]);
        Insaf::place();

        $latestHashes = [
            'rules' => Insaf::rulesHash(),
            'merkle_root' => Insaf::preferencesMerkleRoot(),
            'results' => Insaf::resultsHash(),
        ];
        foreach ($latestHashes as $index => $hash) {
            $this->createStoredCommitment($index, $hash, 204 + (count(Commitment::all()) - 3));
        }

        Http::fake(fn($request) => $this->mirrorResponseForSequence(
            (int) basename(parse_url($request->url(), PHP_URL_PATH))
        ));
        $response = $this->getJson('/api/commitments/verify')->assertOk();

        $this->assertSame(
            ['verified', 'verified', 'verified', 'superseded', 'superseded', 'superseded'],
            array_column($response->json('commitments'), 'status')
        );
        $response->assertJsonPath(
            'commitments.0.rawRecordUrl',
            'https://testnet.mirrornode.hedera.com/api/v1/topics/0.0.123/messages/204'
        );
        Http::assertSentCount(3);

        $listed = $this->getJson('/api/commitments')->assertOk()->json();
        $this->assertSame('current', $listed[0]['status']);
        $this->assertSame('superseded', $listed[3]['status']);
    }

    public function test_latest_commitment_run_detects_tampering_without_flagging_older_runs_as_errors(): void
    {
        $this->seed();
        $student = Student::where('code', 'S001')->firstOrFail();
        $application = $this->createDemoApplication($student);
        Insaf::place();

        foreach ([
            'rules' => Insaf::rulesHash(),
            'merkle_root' => Insaf::preferencesMerkleRoot(),
            'results' => Insaf::resultsHash(),
        ] as $kind => $hash) {
            $this->createStoredCommitment($kind, $hash, 301 + count(Commitment::all()));
        }

        $changedChoices = array_reverse(array_map('intval', $application->choices));
        $application->update([
            'choices' => $changedChoices,
            'commitment' => Insaf::commit($student->code, $changedChoices, $application->salt),
        ]);
        Insaf::place();
        foreach ([
            'rules' => Insaf::rulesHash(),
            'merkle_root' => Insaf::preferencesMerkleRoot(),
            'results' => Insaf::resultsHash(),
        ] as $kind => $hash) {
            $this->createStoredCommitment($kind, $hash, 304 + count(Commitment::all()) - 3);
        }

        $tamperedChoices = array_merge(array_slice($changedChoices, 1), [$changedChoices[0]]);
        $tamperedPlacement = collect($tamperedChoices)
            ->first(fn($id) => (int) $id !== (int) $application->placed_university_id);
        $application->update([
            'choices' => $tamperedChoices,
            'placed_university_id' => $tamperedPlacement,
            'placed_program_id' => University::find($tamperedPlacement)?->specialty_id,
        ]);
        Http::fake(fn($request) => $this->mirrorResponseForSequence(
            (int) basename(parse_url($request->url(), PHP_URL_PATH))
        ));

        $statuses = $this->getJson('/api/commitments/verify')->assertOk()->json('commitments');
        $this->assertSame(
            ['verified', 'data_changed', 'data_changed', 'superseded', 'superseded', 'superseded'],
            array_column($statuses, 'status')
        );
    }

    public function test_demo_reset_refuses_when_demo_mode_is_disabled(): void
    {
        config(['services.hedera.demo_mode' => false]);

        $this->assertSame(
            ConsoleCommand::FAILURE,
            Artisan::call('insaf:demo-reset', ['--force' => true])
        );
        $this->assertStringContainsString('DEMO_MODE=true', Artisan::output());
    }

    public function test_demo_reset_refuses_in_production_even_when_demo_mode_is_enabled(): void
    {
        config(['services.hedera.demo_mode' => true]);
        $this->app->detectEnvironment(fn() => 'production');

        $this->assertSame(
            ConsoleCommand::FAILURE,
            Artisan::call('insaf:demo-reset', ['--force' => true])
        );
        $this->assertStringContainsString('local/testing', Artisan::output());
    }

    public function test_demo_reset_only_clears_intended_demo_and_placement_data(): void
    {
        $this->seed();
        config(['services.hedera.demo_mode' => true]);
        Storage::fake('local');

        $demoStudent = Student::where('code', 'S001')->firstOrFail();
        $demoApplication = $this->createDemoApplication($demoStudent);
        $this->prepareReceipt($demoApplication, array_map('intval', $demoApplication->choices));
        $otherApplication = Application::with('student')
            ->whereHas('student', fn($query) => $query->where('code', '!=', 'S001'))
            ->firstOrFail();
        $otherBefore = $otherApplication->only([
            'choices',
            'salt',
            'commitment',
            'receipt_hash',
            'receipt_sequence',
        ]);

        $firstProgram = Program::firstOrFail();
        $firstUniversity = University::firstOrFail();
        Application::query()->update([
            'placed_program_id' => $firstProgram->id,
            'placed_university_id' => $firstUniversity->id,
        ]);
        Program::query()->update([
            'admitted' => 1,
            'cutoff_score' => 75,
            'cutoff_code' => 'S001',
        ]);
        University::query()->update([
            'admitted' => 1,
            'cutoff_score' => 75,
            'cutoff_code' => 'S001',
        ]);

        $demoPdfPath = 'incident-reports/demo.pdf';
        $otherPdfPath = 'incident-reports/other.pdf';
        Storage::disk('local')->put($demoPdfPath, 'demo');
        Storage::disk('local')->put($otherPdfPath, 'other');
        $demoReport = IncidentReport::create([
            'incident_id' => (string) \Illuminate\Support\Str::uuid(),
            'student_id' => $demoStudent->id,
            'submission_reference' => 'demo-submission',
            'original_hash' => str_repeat('a', 64),
            'current_hash' => str_repeat('b', 64),
            'status' => 'Pending Review',
            'report_data' => ['demo' => true],
            'pdf_path' => $demoPdfPath,
        ]);
        $otherReport = IncidentReport::create([
            'incident_id' => (string) \Illuminate\Support\Str::uuid(),
            'student_id' => $otherApplication->student_id,
            'submission_reference' => 'other-submission',
            'original_hash' => str_repeat('c', 64),
            'current_hash' => str_repeat('d', 64),
            'status' => 'Pending Review',
            'report_data' => ['demo' => false],
            'pdf_path' => $otherPdfPath,
        ]);
        $this->createStoredCommitment('rules', Insaf::rulesHash(), 401);
        $studentCount = Student::count();
        $adminStates = Student::pluck('is_admin', 'id')->all();

        $this->assertSame(
            ConsoleCommand::SUCCESS,
            Artisan::call('insaf:demo-reset', ['--force' => true])
        );

        $this->assertDatabaseMissing('applications', ['student_id' => $demoStudent->id]);
        $this->assertDatabaseHas('incident_reports', ['id' => $otherReport->id]);
        $this->assertDatabaseMissing('incident_reports', ['id' => $demoReport->id]);
        $this->assertDatabaseCount('commitments', 0);
        $this->assertFalse(Storage::disk('local')->exists($demoPdfPath));
        $this->assertTrue(Storage::disk('local')->exists($otherPdfPath));
        $this->assertSame($studentCount, Student::count());
        $this->assertSame($adminStates, Student::pluck('is_admin', 'id')->all());
        $this->assertSame(
            $otherBefore,
            $otherApplication->fresh()->only(array_keys($otherBefore))
        );
        $this->assertDatabaseHas('applications', [
            'id' => $otherApplication->id,
            'placed_program_id' => null,
            'placed_university_id' => null,
        ]);
        $this->assertDatabaseHas('programs', [
            'id' => $firstProgram->id,
            'admitted' => 0,
            'cutoff_score' => null,
            'cutoff_code' => null,
        ]);
        $this->assertDatabaseHas('universities', [
            'id' => $firstUniversity->id,
            'admitted' => 1,
            'cutoff_score' => 75,
            'cutoff_code' => 'S001',
        ]);
        $this->assertStringContainsString('remain on the topic', Artisan::output());
    }

    public function test_commitment_verifier_reports_chain_mismatch(): void
    {
        $this->seed();
        $hash = Insaf::rulesHash();
        $this->createStoredCommitment('rules', $hash, 91);
        Http::fake(['*' => Http::response([
            'message' => base64_encode('rules:' . str_repeat('a', 64)),
            'topic_id' => '0.0.123',
            'sequence_number' => 91,
        ])]);

        $this->getJson('/api/commitments/verify')
            ->assertOk()
            ->assertJsonPath('commitments.0.status', 'chain_mismatch');
    }

    public function test_legacy_commitments_fall_back_to_configured_topic_and_raw_mirror_url(): void
    {
        $this->seed();
        $commitment = $this->createStoredCommitment('rules', Insaf::rulesHash(), 90);
        $commitment->update([
            'hedera_network' => null,
            'hedera_topic_id' => null,
        ]);
        Http::fake(fn($request) => $this->mirrorResponseForSequence(
            (int) basename(parse_url($request->url(), PHP_URL_PATH))
        ));

        $this->getJson('/api/commitments/verify')
            ->assertOk()
            ->assertJsonPath('commitments.0.status', 'verified')
            ->assertJsonPath('commitments.0.network', 'testnet')
            ->assertJsonPath('commitments.0.topicId', '0.0.123')
            ->assertJsonPath(
                'commitments.0.rawRecordUrl',
                'https://testnet.mirrornode.hedera.com/api/v1/topics/0.0.123/messages/90'
            );
    }

    public function test_commitment_verifier_treats_mirror_node_404_as_pending(): void
    {
        $this->seed();
        $this->createStoredCommitment('rules', Insaf::rulesHash(), 92);
        Http::fake(['*' => Http::response([], 404)]);

        $this->getJson('/api/commitments/verify')
            ->assertOk()
            ->assertJsonPath('commitments.0.status', 'pending');
        Http::assertSentCount(3);
    }

    public function test_commitment_verifier_reports_mirror_node_errors_as_unavailable(): void
    {
        $this->seed();
        $this->createStoredCommitment('rules', Insaf::rulesHash(), 93);
        Http::fake(['*' => Http::response([], 503)]);

        $this->getJson('/api/commitments/verify')
            ->assertOk()
            ->assertJsonPath('commitments.0.status', 'unavailable');
    }

    public function test_commitment_verifier_reports_malformed_mirror_node_records_as_unavailable(): void
    {
        $this->seed();
        $this->createStoredCommitment('rules', Insaf::rulesHash(), 94);
        Http::fake(['*' => Http::response([
            'message' => '%%%invalid-base64%%%',
            'topic_id' => '0.0.123',
            'sequence_number' => 94,
        ])]);

        $this->getJson('/api/commitments/verify')
            ->assertOk()
            ->assertJsonPath('commitments.0.status', 'unavailable');
    }

    public function test_verification_reports_a_pending_mirror_node_record_without_marking_it_invalid(): void
    {
        $this->seed();
        $application = Application::with('student')->firstOrFail();
        $choices = array_map('intval', $application->choices);
        $this->prepareReceipt($application, $choices);
        Sanctum::actingAs($application->student);
        Http::fake(['*' => Http::response([], 404)]);

        $this->postJson('/api/verify', $this->verificationPayload($application, $choices))
            ->assertStatus(202)
            ->assertJsonPath('error', 'verification_pending');
    }

    public function test_verification_rejects_a_receipt_sequence_that_does_not_match_the_saved_submission(): void
    {
        $this->seed();
        $application = Application::with('student')->firstOrFail();
        $choices = array_map('intval', $application->choices);
        $this->prepareReceipt($application, $choices);
        Sanctum::actingAs($application->student);

        $payload = $this->verificationPayload($application, $choices);
        $payload['sequence'] = 43;

        $this->postJson('/api/verify', $payload)
            ->assertUnprocessable()
            ->assertJsonPath('error', 'receipt_mismatch');
        Http::assertNothingSent();
    }

    public function test_verification_reports_mirror_node_service_errors_explicitly(): void
    {
        $this->seed();
        $application = Application::with('student')->firstOrFail();
        $choices = array_map('intval', $application->choices);
        $this->prepareReceipt($application, $choices);
        Sanctum::actingAs($application->student);
        Http::fake(['*' => Http::response([], 503)]);

        $this->postJson('/api/verify', $this->verificationPayload($application, $choices))
            ->assertStatus(503)
            ->assertJsonPath('error', 'mirror_node_unavailable');
    }

    public function test_verification_does_not_succeed_when_mirror_node_configuration_is_invalid(): void
    {
        $this->seed();
        $application = Application::with('student')->firstOrFail();
        $choices = array_map('intval', $application->choices);
        $this->prepareReceipt($application, $choices);
        config(['services.hedera.mirror_node_url' => '']);
        Sanctum::actingAs($application->student);

        $this->postJson('/api/verify', $this->verificationPayload($application, $choices))
            ->assertStatus(503)
            ->assertJsonPath('error', 'mirror_node_misconfigured')
            ->assertJsonMissingPath('valid');

        Http::assertNothingSent();
    }

    public function test_verification_rejects_a_mirror_node_record_from_a_different_topic(): void
    {
        $this->seed();
        $application = Application::with('student')->firstOrFail();
        $choices = array_map('intval', $application->choices);
        $this->prepareReceipt($application, $choices);
        Sanctum::actingAs($application->student);
        Http::fake(['*' => Http::response([
            'message' => base64_encode('c:' . Insaf::commit(
                $application->student->code,
                $choices,
                $application->salt
            )),
            'topic_id' => '0.0.999',
            'sequence_number' => 42,
        ])]);

        $this->postJson('/api/verify', $this->verificationPayload($application, $choices))
            ->assertStatus(502)
            ->assertJsonPath('error', 'invalid_mirror_node_record');
    }

    public function test_verification_rejects_a_mirror_node_record_with_a_different_sequence(): void
    {
        $this->seed();
        $application = Application::with('student')->firstOrFail();
        $choices = array_map('intval', $application->choices);
        $this->prepareReceipt($application, $choices);
        Sanctum::actingAs($application->student);
        Http::fake(['*' => Http::response([
            'message' => base64_encode('c:' . Insaf::commit(
                $application->student->code,
                $choices,
                $application->salt
            )),
            'topic_id' => '0.0.123',
            'sequence_number' => 43,
        ])]);

        $this->postJson('/api/verify', $this->verificationPayload($application, $choices))
            ->assertStatus(502)
            ->assertJsonPath('error', 'invalid_mirror_node_record');
    }

    public function test_verification_rejects_malformed_mirror_node_message_encoding(): void
    {
        $this->seed();
        $application = Application::with('student')->firstOrFail();
        $choices = array_map('intval', $application->choices);
        $this->prepareReceipt($application, $choices);
        Sanctum::actingAs($application->student);
        Http::fake(['*' => Http::response([
            'message' => 'not base64!',
            'topic_id' => '0.0.123',
            'sequence_number' => 42,
        ])]);

        $this->postJson('/api/verify', $this->verificationPayload($application, $choices))
            ->assertStatus(502)
            ->assertJsonPath('error', 'invalid_mirror_node_record');
    }

    public function test_verification_handles_mirror_node_connection_failures_explicitly(): void
    {
        $this->seed();
        $application = Application::with('student')->firstOrFail();
        $choices = array_map('intval', $application->choices);
        $this->prepareReceipt($application, $choices);
        Sanctum::actingAs($application->student);
        Http::fake(fn () => throw new ConnectionException('Mirror Node connection failed'));

        $this->postJson('/api/verify', $this->verificationPayload($application, $choices))
            ->assertStatus(503)
            ->assertJsonPath('error', 'mirror_node_unavailable');
    }

    public function test_verification_returns_incident_details_when_saved_preferences_differ_from_hedera(): void
    {
        $this->seed();
        $application = Application::with('student')->firstOrFail();
        $originalChoices = array_map('intval', $application->choices);
        $originalHash = Insaf::commit(
            $application->student->code,
            $originalChoices,
            $application->salt
        );
        $application->update([
            'receipt_choices' => $originalChoices,
            'receipt_salt' => $application->salt,
            'receipt_hash' => $originalHash,
            'receipt_sequence' => 42,
            'receipt_transaction_id' => '0.0.123@1700000000.000000001',
            'receipt_network' => 'testnet',
            'receipt_topic_id' => '0.0.123',
            'choices' => array_reverse($originalChoices),
            'commitment' => Insaf::commit(
                $application->student->code,
                array_reverse($originalChoices),
                $application->salt
            ),
        ]);
        Http::fake([
            '*' => Http::response([
                'message' => base64_encode('c:' . $originalHash),
                'topic_id' => '0.0.123',
                'sequence_number' => 42,
                'consensus_timestamp' => '1700000000.000000001',
                'transaction_id' => '0.0.123@1700000000.000000001',
            ]),
        ]);
        Sanctum::actingAs($application->student);

        $this->postJson('/api/verify', [
            'code' => $application->student->code,
            'score' => $application->student->score,
            'choices' => $originalChoices,
            'salt' => $application->salt,
            'sequence' => 42,
            'tx' => '0.0.123@1700000000.000000001',
        ])
            ->assertOk()
            ->assertJsonPath('incident.studentReference', $application->student->code)
            ->assertJsonPath('incident.originalHash', $originalHash)
            ->assertJsonPath('incident.currentHash', $application->commitment)
            ->assertJsonPath('incident.hedera.topicId', '0.0.123')
            ->assertJsonPath('incident.hedera.consensusTimestamp', '1700000000.000000001')
            ->assertJsonPath('incident.status', 'Integrity mismatch — pending investigation.')
            ->assertJsonPath('incident.originalChoices', $originalChoices)
            ->assertJsonPath('incident.currentChoices', array_reverse($originalChoices))
            ->assertJsonStructure([
                'incident' => [
                    'id',
                    'submissionReference',
                    'differences' => ['byRank', 'removed', 'added'],
                    'verificationTimestamp',
                    'originalReceiptReference' => ['transactionId', 'sequence'],
                ],
            ]);
    }

    public function test_student_can_submit_an_integrity_report_for_admin_review(): void
    {
        [$application, $incident, $originalHash] = $this->prepareIncidentSubmission();
        Storage::fake('local');
        Http::fake([
            '*' => Http::response([
                'message' => base64_encode('c:' . $originalHash),
                'topic_id' => '0.0.123',
                'sequence_number' => 42,
                'consensus_timestamp' => '1700000000.000000001',
                'transaction_id' => '0.0.123@1700000000.000000001',
            ]),
        ]);
        Sanctum::actingAs($application->student);

        $this->getJson('/api/admin/incident-reports')->assertForbidden();
        $this->getJson("/api/admin/incident-reports/{$incident['id']}")->assertForbidden();
        $this->get("/api/admin/incident-reports/{$incident['id']}/pdf")->assertForbidden();
        $this->patchJson("/api/admin/incident-reports/{$incident['id']}", [
            'status' => 'Confirmed',
        ])->assertForbidden();

        $pdfContents = "%PDF-1.4\nincident report\n%%EOF";
        $response = $this->post('/api/incident-reports', [
            'incident' => json_encode($incident, JSON_THROW_ON_ERROR),
            'pdf' => UploadedFile::fake()->createWithContent('incident.pdf', $pdfContents),
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('incident.id', $incident['id'])
            ->assertJsonPath('incident.status', 'Pending Review');

        $this->assertDatabaseHas('incident_reports', [
            'incident_id' => $incident['id'],
            'student_id' => $application->student_id,
            'status' => 'Pending Review',
        ]);
        $storedPdfPath = \App\Models\IncidentReport::where('incident_id', $incident['id'])->value('pdf_path');
        Storage::disk('local')->assertExists($storedPdfPath);

        $this->post('/api/incident-reports', [
            'incident' => json_encode($incident, JSON_THROW_ON_ERROR),
            'pdf' => UploadedFile::fake()->createWithContent('incident.pdf', $pdfContents),
        ], ['Accept' => 'application/json'])->assertConflict();
        Storage::disk('local')->assertExists($storedPdfPath);

        $application->student->update(['is_admin' => true]);
        Sanctum::actingAs($application->student->fresh());

        $this->getJson('/api/admin/incident-reports')
            ->assertOk()
            ->assertJsonPath('data.0.student.code', $application->student->code)
            ->assertJsonPath('data.0.incident_id', $incident['id']);

        $this->getJson("/api/admin/incident-reports/{$incident['id']}")
            ->assertOk()
            ->assertJsonPath('originalHash', $originalHash)
            ->assertJsonPath('report.currentHash', $incident['currentHash'])
            ->assertJsonPath('report.hedera.network', 'testnet')
            ->assertJsonPath('report.originalReceiptReference.sequence', 42);

        $this->get("/api/admin/incident-reports/{$incident['id']}/pdf")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->patchJson("/api/admin/incident-reports/{$incident['id']}", [
            'status' => 'Under Investigation',
            'reviewNotes' => 'Compare the saved receipt with the university submission log.',
        ])->assertOk()
            ->assertJsonPath('status', 'Under Investigation')
            ->assertJsonPath('reviewNotes', 'Compare the saved receipt with the university submission log.');
        $this->assertDatabaseHas('incident_reports', [
            'incident_id' => $incident['id'],
            'status' => 'Under Investigation',
            'review_notes' => 'Compare the saved receipt with the university submission log.',
        ]);

        $this->patchJson("/api/admin/incident-reports/{$incident['id']}", [
            'status' => 'Arbitrary status',
        ])->assertUnprocessable();
    }

    public function test_student_can_report_incorrect_profile_information_for_admin_review(): void
    {
        $this->seed();
        $student = Student::where('code', 'S001')->firstOrFail();
        Sanctum::actingAs($student);

        $response = $this->postJson('/api/profile/problem-reports', [
            'category' => 'name',
            'message' => 'My displayed full name is incorrect.',
        ])->assertCreated()
            ->assertJsonPath('message', 'Your report was sent to the administration for review.');

        $reportId = $response->json('id');
        $this->assertDatabaseHas('incident_reports', [
            'incident_id' => $reportId,
            'student_id' => $student->id,
            'report_type' => 'student_problem',
            'status' => 'Pending Review',
        ]);
        $this->assertSame([
            'category' => 'name',
            'message' => 'My displayed full name is incorrect.',
        ], IncidentReport::where('incident_id', $reportId)->firstOrFail()->report_data);

        $student->update(['is_admin' => true]);
        Sanctum::actingAs($student->fresh());
        $this->getJson('/api/admin/incident-reports')
            ->assertOk()
            ->assertJsonPath('data.0.report_type', 'student_problem')
            ->assertJsonPath('data.0.student.code', $student->code);
        $this->getJson("/api/admin/incident-reports/{$reportId}")
            ->assertOk()
            ->assertJsonPath('reportType', 'student_problem')
            ->assertJsonPath('report.message', 'My displayed full name is incorrect.');
        $this->getJson("/api/admin/incident-reports/{$reportId}/pdf")->assertNotFound();
    }

    public function test_incident_submission_does_not_report_success_when_mirror_node_fails(): void
    {
        [$application, $incident] = $this->prepareIncidentSubmission();
        Storage::fake('local');
        Http::fake(['*' => Http::response([], 503)]);
        Sanctum::actingAs($application->student);

        $this->post('/api/incident-reports', [
            'incident' => json_encode($incident, JSON_THROW_ON_ERROR),
            'pdf' => UploadedFile::fake()->createWithContent('incident.pdf', "%PDF-1.4\n%%EOF"),
        ], ['Accept' => 'application/json'])
            ->assertStatus(503)
            ->assertJsonPath('error', 'mirror_node_unavailable');

        $this->assertDatabaseCount('incident_reports', 0);
        Http::assertSentCount(1);
    }

    public function test_incident_submission_rejects_invalid_json_metadata(): void
    {
        $this->seed();
        $application = Application::with('student')->firstOrFail();
        Sanctum::actingAs($application->student);

        $this->post('/api/incident-reports', [
            'incident' => '{invalid',
            'pdf' => UploadedFile::fake()->createWithContent('incident.pdf', "%PDF-1.4\n%%EOF"),
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('incident');
    }

    public function test_demo_tampering_and_ministry_actions_are_disabled_outside_demo_environments(): void
    {
        $this->seed();
        $application = Application::with('student')->firstOrFail();
        $application->student->update(['is_admin' => true]);
        Sanctum::actingAs($application->student);
        config(['services.hedera.demo_mode' => true]);
        $this->app->detectEnvironment(fn() => 'production');

        $this->postJson("/api/admin/tamper/{$application->student->code}")->assertForbidden();
    }

    public function test_admin_can_tamper_demo_student_and_verify_against_preserved_hedera_receipt(): void
    {
        $this->seed();
        $student = \App\Models\Student::where('code', 'S001')->firstOrFail();
        $application = $this->createDemoApplication($student);
        $originalChoices = array_map('intval', $application->choices);
        $this->prepareReceipt($application, $originalChoices);
        $application->refresh();
        $originalReceipt = [
            'choices' => array_map('intval', $application->receipt_choices),
            'salt' => $application->receipt_salt,
            'hash' => $application->receipt_hash,
            'transaction_id' => $application->receipt_transaction_id,
            'network' => $application->receipt_network,
            'topic_id' => $application->receipt_topic_id,
            'sequence' => (int) $application->receipt_sequence,
        ];
        config(['services.hedera.demo_mode' => true]);
        Http::fake([
            '*' => Http::response([
                'message' => base64_encode('c:' . $originalReceipt['hash']),
                'topic_id' => '0.0.123',
                'sequence_number' => 42,
                'consensus_timestamp' => '1700000000.000000001',
                'transaction_id' => $originalReceipt['transaction_id'],
            ]),
        ]);

        Sanctum::actingAs($student);
        $this->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('demoTamperAvailable', false);
        $this->postJson('/api/admin/run')->assertForbidden();
        $this->postJson('/api/admin/tamper/S001')->assertForbidden();

        $student->update(['is_admin' => true]);
        Sanctum::actingAs($student->fresh());
        $this->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('demoTamperAvailable', true);

        $tamperResponse = $this->postJson('/api/admin/tamper/S001')
            ->assertOk()
            ->assertJsonPath('tampered', true)
            ->assertJsonPath('code', 'S001')
            ->assertJsonPath('receipt.sequence', 42)
            ->assertJsonPath('receipt.topicId', '0.0.123');
        $beforeChoices = $tamperResponse->json('beforeChoices');
        $afterChoices = $tamperResponse->json('afterChoices');
        $this->assertSame($originalChoices, $beforeChoices);
        $this->assertNotSame($originalChoices, $afterChoices);

        $application->refresh();
        $this->assertSame($originalReceipt['choices'], array_map('intval', $application->receipt_choices));
        $this->assertSame($originalReceipt['salt'], $application->receipt_salt);
        $this->assertSame($originalReceipt['hash'], $application->receipt_hash);
        $this->assertSame($originalReceipt['transaction_id'], $application->receipt_transaction_id);
        $this->assertSame($originalReceipt['network'], $application->receipt_network);
        $this->assertSame($originalReceipt['topic_id'], $application->receipt_topic_id);
        $this->assertSame($originalReceipt['sequence'], (int) $application->receipt_sequence);
        $this->assertSame($afterChoices, array_map('intval', $application->choices));

        $this->postJson('/api/verify', $tamperResponse->json('receipt'))
            ->assertOk()
            ->assertJsonPath('valid', false)
            ->assertJsonPath('checks.0.ok', true)
            ->assertJsonPath('checks.1.ok', false)
            ->assertJsonPath('incident.originalHash', $originalReceipt['hash'])
            ->assertJsonPath('incident.currentChoices', $afterChoices)
            ->assertJsonPath('incident.hedera.topicId', '0.0.123');

        Http::assertSent(fn($request) =>
            str_contains($request->url(), '/api/v1/topics/0.0.123/messages/42')
        );
    }

    public function test_demo_actions_require_an_administrator_and_remain_available_in_testing_demo_mode(): void
    {
        $this->seed();
        $student = \App\Models\Student::where('code', 'S001')->firstOrFail();
        $application = $this->createDemoApplication($student);
        $this->prepareReceipt($application, array_map('intval', $application->choices));
        config(['services.hedera.demo_mode' => true]);
        $this->app->detectEnvironment(fn() => 'testing');

        Sanctum::actingAs($student);
        $this->postJson('/api/admin/run')->assertForbidden();
        $this->postJson('/api/admin/tamper/S001')->assertForbidden();

        $student->update(['is_admin' => true]);
        Sanctum::actingAs($student->fresh());
        $this->postJson('/api/admin/tamper/S001')
            ->assertOk()
            ->assertJsonPath('tampered', true);
    }

    private function prepareIncidentSubmission(): array
    {
        $this->seed();
        $application = Application::with('student')->firstOrFail();
        $student = $application->student;
        $originalChoices = array_map('intval', $application->choices);
        $currentChoices = array_reverse($originalChoices);
        $originalHash = Insaf::commit($student->code, $originalChoices, $application->salt);
        $currentHash = Insaf::commit($student->code, $currentChoices, $application->salt);
        $transactionId = '0.0.123@1700000000.000000001';
        $application->update([
            'receipt_choices' => $originalChoices,
            'receipt_salt' => $application->salt,
            'receipt_hash' => $originalHash,
            'receipt_sequence' => 42,
            'receipt_transaction_id' => $transactionId,
            'receipt_network' => 'testnet',
            'receipt_topic_id' => '0.0.123',
            'choices' => $currentChoices,
            'commitment' => $currentHash,
        ]);

        $byRank = [];
        foreach ($originalChoices as $rank => $originalId) {
            if ($originalId !== $currentChoices[$rank]) {
                $byRank[] = [
                    'rank' => $rank + 1,
                    'original' => $originalId,
                    'current' => $currentChoices[$rank],
                ];
            }
        }

        return [
            $application->fresh('student'),
            [
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'studentReference' => $student->code,
                'submissionReference' => 'submission-' . $student->code . '-42',
                'originalChoices' => $originalChoices,
                'originalHash' => $originalHash,
                'originalHashSource' => 'Hedera record',
                'currentChoices' => $currentChoices,
                'currentHash' => $currentHash,
                'differences' => [
                    'byRank' => $byRank,
                    'removed' => array_values(array_diff($originalChoices, $currentChoices)),
                    'added' => array_values(array_diff($currentChoices, $originalChoices)),
                ],
                'hedera' => [
                    'network' => 'testnet',
                    'topicId' => '0.0.123',
                    'sequence' => 42,
                    'consensusTimestamp' => '1700000000.000000001',
                    'recordReference' => $transactionId,
                ],
                'verificationTimestamp' => now()->toISOString(),
                'status' => 'Integrity mismatch — pending investigation.',
                'originalReceiptReference' => [
                    'transactionId' => $transactionId,
                    'sequence' => 42,
                ],
            ],
            $originalHash,
        ];
    }

    private function createPlacementCommitments(): array
    {
        $hashes = [
            'rules' => Insaf::rulesHash(),
            'merkle_root' => Insaf::preferencesMerkleRoot(),
            'results' => Insaf::resultsHash(),
        ];

        foreach ($hashes as $index => $hash) {
            $this->createStoredCommitment($index, $hash, 101 + count(Commitment::all()));
        }

        return $hashes;
    }

    private function createStoredCommitment(string $kind, string $hash, int $sequence): Commitment
    {
        return Commitment::create([
            'kind' => $kind,
            'hash' => $hash,
            'hcs_tx_id' => '0.0.123@1700000000.' . str_pad((string) $sequence, 9, '0', STR_PAD_LEFT),
            'hcs_sequence' => $sequence,
            'hedera_network' => 'testnet',
            'hedera_topic_id' => '0.0.123',
        ]);
    }

    private function processReceipt(int $sequence): \Illuminate\Process\FakeProcessResult
    {
        return Process::result(json_encode([
            'transactionId' => '0.0.123@1700000000.' . str_pad((string) $sequence, 9, '0', STR_PAD_LEFT),
            'sequence' => $sequence,
            'topicId' => '0.0.123',
            'network' => 'testnet',
        ], JSON_THROW_ON_ERROR));
    }

    private function mirrorResponseForSequence(int $sequence)
    {
        $commitment = Commitment::where('hcs_sequence', $sequence)->firstOrFail();

        return Http::response([
            'message' => base64_encode($commitment->kind . ':' . $commitment->hash),
            'topic_id' => '0.0.123',
            'sequence_number' => $sequence,
            'consensus_timestamp' => '1700000000.000000001',
            'transaction_id' => $commitment->hcs_tx_id,
        ]);
    }

    private function prepareReceipt(Application $application, array $choices): void
    {
        $application->update([
            'receipt_choices' => $choices,
            'receipt_salt' => $application->salt,
            'receipt_hash' => Insaf::commit(
                $application->student->code,
                $choices,
                $application->salt
            ),
            'receipt_transaction_id' => '0.0.123@1700000000.000000001',
            'receipt_network' => 'testnet',
            'receipt_topic_id' => '0.0.123',
            'receipt_consensus_timestamp' => null,
            'receipt_sequence' => 42,
            'receipt_submitted_at' => now(),
        ]);
    }

    private function createDemoApplication(\App\Models\Student $student): Application
    {
        $choices = University::query()
            ->orderBy('id')
            ->limit(5)
            ->pluck('id')
            ->map(fn($id) => (int) $id)
            ->all();
        $salt = bin2hex(random_bytes(16));

        return Application::create([
            'student_id' => $student->id,
            'choices' => $choices,
            'salt' => $salt,
            'commitment' => Insaf::commit($student->code, $choices, $salt),
        ]);
    }

    private function verificationPayload(Application $application, array $choices): array
    {
        return [
            'code' => $application->student->code,
            'score' => $application->student->score,
            'choices' => $choices,
            'salt' => $application->salt,
            'sequence' => 42,
            'tx' => '0.0.123@1700000000.000000001',
        ];
    }
}
