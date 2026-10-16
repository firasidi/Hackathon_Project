<?php

namespace App\Http\Controllers;

use App\Models\IncidentReport;
use App\Services\Insaf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class IncidentReportController extends Controller
{
    private const STATUSES = [
        'Pending Review',
        'Under Investigation',
        'Confirmed',
        'False Alarm',
    ];

    public function store(Request $request)
    {
        if (is_string($request->input('incident'))) {
            try {
                $incident = json_decode($request->input('incident'), true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                throw ValidationException::withMessages([
                    'incident' => 'The incident report data must be valid JSON.',
                ]);
            }

            $request->merge(['incident' => $incident]);
        }

        $data = $request->validate([
            'incident' => 'required|array:id,submissionReference,originalChoices,originalHash,originalHashSource,currentChoices,currentHash,differences,hedera,verificationTimestamp,status,originalReceiptReference,studentReference',
            'incident.id' => 'required|uuid',
            'incident.studentReference' => 'required|string|max:40',
            'incident.submissionReference' => 'required|string|max:120',
            'incident.originalChoices' => 'required|array|min:3',
            'incident.originalChoices.*' => 'integer|distinct|exists:universities,id',
            'incident.originalHash' => ['required', 'regex:/^[a-f0-9]{64}$/i'],
            'incident.originalHashSource' => 'required|string|in:Hedera record',
            'incident.currentChoices' => 'required|array|min:3',
            'incident.currentChoices.*' => 'integer|distinct|exists:universities,id',
            'incident.currentHash' => ['required', 'regex:/^[a-f0-9]{64}$/i'],
            'incident.differences' => 'required|array:byRank,removed,added',
            'incident.differences.byRank' => 'present|array',
            'incident.differences.removed' => 'present|array',
            'incident.differences.added' => 'present|array',
            'incident.hedera' => 'required|array:topicId,sequence,consensusTimestamp,recordReference',
            'incident.hedera.topicId' => 'nullable|string|max:40',
            'incident.hedera.sequence' => 'required|integer|min:1',
            'incident.hedera.consensusTimestamp' => 'nullable|string|max:40',
            'incident.hedera.recordReference' => 'nullable|string|max:100',
            'incident.verificationTimestamp' => 'required|date',
            'incident.status' => 'required|string|in:Integrity mismatch — pending investigation.',
            'incident.originalReceiptReference' => 'required|array:transactionId,sequence',
            'incident.originalReceiptReference.transactionId' => 'nullable|string|max:100',
            'incident.originalReceiptReference.sequence' => 'required|integer|min:1',
            'pdf' => 'required|file|mimetypes:application/pdf|max:10240',
        ]);

        $student = $request->user();
        $application = $student->application;
        abort_unless($application, 422, 'No saved application is available for this report.');
        abort_unless(
            $data['incident']['studentReference'] === $student->code,
            403,
            'You can submit reports only for your own student record.'
        );
        abort_unless(
            $application->receipt_sequence !== null &&
            (int) $application->receipt_sequence === (int) $data['incident']['hedera']['sequence'] &&
            (int) $application->receipt_sequence === (int) $data['incident']['originalReceiptReference']['sequence'],
            422,
            'The incident does not match your saved receipt sequence.'
        );

        $originalChoices = array_map('intval', $application->receipt_choices ?? []);
        $currentChoices = array_map('intval', $application->choices ?? []);
        abort_unless(
            $originalChoices === array_map('intval', $data['incident']['originalChoices']) &&
            $currentChoices === array_map('intval', $data['incident']['currentChoices']),
            422,
            'The report preferences do not match the saved receipt and current application.'
        );

        $originalHash = Insaf::commit(
            $student->code,
            $originalChoices,
            (string) $application->receipt_salt
        );
        $currentHash = Insaf::commit(
            $student->code,
            $currentChoices,
            (string) $application->salt
        );
        abort_unless(
            hash_equals($originalHash, $data['incident']['originalHash']) &&
            hash_equals($currentHash, $data['incident']['currentHash']) &&
            !hash_equals($originalHash, $currentHash) &&
            hash_equals((string) $application->receipt_hash, $originalHash),
            422,
            'The report fingerprints do not match the saved receipt and current application.'
        );

        $anchorRecord = Insaf::readAnchorRecord((int) $application->receipt_sequence);
        abort_unless(
            $anchorRecord &&
            ($anchorRecord['message'] ?? null) === 'c:' . $originalHash,
            422,
            'The original receipt could not be confirmed against Hedera.'
        );

        $originalReceiptReference = [
            'transactionId' => $application->receipt_transaction_id,
            'sequence' => (int) $application->receipt_sequence,
        ];
        abort_unless(
            ($data['incident']['originalReceiptReference']['transactionId'] ?? null) ===
                $originalReceiptReference['transactionId'],
            422,
            'The report receipt reference does not match the saved receipt.'
        );
        abort_if(
            IncidentReport::where('incident_id', $data['incident']['id'])->exists(),
            409,
            'This incident report has already been submitted.'
        );

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

        $reportData = [
            'incidentId' => $data['incident']['id'],
            'studentReference' => $student->code,
            'submissionReference' => $data['incident']['submissionReference'],
            'originalChoices' => $originalChoices,
            'originalHash' => $originalHash,
            'originalHashSource' => 'Hedera record',
            'currentChoices' => $currentChoices,
            'currentHash' => $currentHash,
            'differences' => [
                'byRank' => $rankDifferences,
                'removed' => array_values(array_diff($originalChoices, $currentChoices)),
                'added' => array_values(array_diff($currentChoices, $originalChoices)),
            ],
            'hedera' => [
                'topicId' => $anchorRecord['topic_id'] ?? null,
                'sequence' => $anchorRecord['sequence_number'] ?? $application->receipt_sequence,
                'consensusTimestamp' => $anchorRecord['consensus_timestamp'] ?? null,
                'recordReference' => $anchorRecord['transaction_id']
                    ?? $anchorRecord['initial_transaction_id']
                    ?? null,
            ],
            'verificationTimestamp' => $data['incident']['verificationTimestamp'],
            'status' => 'Integrity mismatch — pending investigation.',
            'originalReceiptReference' => $originalReceiptReference,
        ];

        $incidentId = $data['incident']['id'];
        $directory = "incidents/{$student->id}";
        $pdfName = "{$incidentId}-" . Str::uuid() . '.pdf';
        $storedPath = $data['pdf']->storeAs(
            $directory,
            $pdfName,
            'local'
        );
        abort_if(!$storedPath, 500, 'The incident report PDF could not be stored.');

        try {
            $report = DB::transaction(fn() => IncidentReport::create([
                'incident_id' => $incidentId,
                'student_id' => $student->id,
                'submission_reference' => $reportData['submissionReference'],
                'original_hash' => $originalHash,
                'current_hash' => $currentHash,
                'hedera_topic_id' => $reportData['hedera']['topicId'],
                'hedera_sequence' => $reportData['hedera']['sequence'],
                'hedera_consensus_timestamp' => $reportData['hedera']['consensusTimestamp'],
                'status' => 'Pending Review',
                'report_data' => $reportData,
                'pdf_path' => $storedPath,
            ]));
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($storedPath);
            throw $exception;
        }

        return response()->json([
            'message' => 'Incident report submitted for administrator review.',
            'incident' => [
                'id' => $report->incident_id,
                'status' => $report->status,
            ],
        ], 201);
    }

    public function index()
    {
        return IncidentReport::query()
            ->with('student:id,code')
            ->orderByDesc('created_at')
            ->paginate(25, [
                'id',
                'incident_id',
                'student_id',
                'submission_reference',
                'original_hash',
                'current_hash',
                'status',
                'created_at',
            ]);
    }

    public function show(string $incidentId)
    {
        $report = IncidentReport::where('incident_id', $incidentId)->firstOrFail();
        return [
            'id' => $report->incident_id,
            'studentReference' => $report->student->code,
            'submissionReference' => $report->submission_reference,
            'originalHash' => $report->original_hash,
            'currentHash' => $report->current_hash,
            'status' => $report->status,
            'report' => $report->report_data,
            'createdAt' => $report->created_at?->toISOString(),
        ];
    }

    public function download(string $incidentId)
    {
        $report = IncidentReport::where('incident_id', $incidentId)->firstOrFail();
        abort_unless(Storage::disk('local')->exists($report->pdf_path), 404, 'Incident PDF not found.');

        return Storage::disk('local')->download(
            $report->pdf_path,
            "incident-{$report->incident_id}.pdf",
            ['Cache-Control' => 'private, no-store']
        );
    }

    public function updateStatus(Request $request, string $incidentId)
    {
        $data = $request->validate([
            'status' => ['required', 'string', 'in:' . implode(',', self::STATUSES)],
        ]);

        $report = IncidentReport::where('incident_id', $incidentId)->firstOrFail();
        $report->update(['status' => $data['status']]);

        return [
            'id' => $report->incident_id,
            'status' => $report->status,
        ];
    }
}
