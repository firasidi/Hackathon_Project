<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\University;
use App\Services\Insaf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Facades\Http;
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
        $choices = University::query()
            ->orderBy('id')
            ->limit(3)
            ->pluck('id')
            ->map(fn($id) => (int) $id)
            ->all();
        Sanctum::actingAs($student);
        Process::fake([
            '*' => Process::result(json_encode([
                'transactionId' => '0.0.123@1700000000.000000001',
                'sequence' => '42',
                'topicId' => '0.0.123',
                'network' => 'testnet',
            ], JSON_THROW_ON_ERROR)),
        ]);

        $response = $this->postJson('/api/apply', ['choices' => $choices])
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
