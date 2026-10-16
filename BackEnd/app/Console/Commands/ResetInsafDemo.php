<?php

namespace App\Console\Commands;

use App\Models\Application;
use App\Models\Commitment;
use App\Models\IncidentReport;
use App\Models\Program;
use App\Models\Student;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ResetInsafDemo extends Command
{
    protected $signature = 'insaf:demo-reset {--force : Skip the confirmation prompt}';

    protected $description = 'Reset only the synthetic S001 demonstration state';

    public function handle(): int
    {
        if (!app()->environment(['local', 'testing']) ||
            !filter_var(config('services.hedera.demo_mode'), FILTER_VALIDATE_BOOLEAN)) {
            $this->error('Demo reset is available only in local/testing with DEMO_MODE=true.');
            return self::FAILURE;
        }

        if (!$this->option('force') &&
            !$this->confirm('Reset S001 demo state and all placement commitments?')) {
            $this->info('Demo reset cancelled.');
            return self::SUCCESS;
        }

        $pdfPaths = [];
        DB::transaction(function () use (&$pdfPaths): void {
            $student = Student::where('code', 'S001')->first();
            if ($student) {
                $reports = IncidentReport::where('student_id', $student->id);
                $pdfPaths = $reports->pluck('pdf_path')->filter()->all();
                $reports->delete();
                Application::where('student_id', $student->id)->delete();
            }

            Commitment::query()->delete();
            Application::query()->update([
                'placed_program_id' => null,
                'placed_university_id' => null,
            ]);
            Program::query()->update([
                'admitted' => 0,
                'cutoff_score' => null,
                'cutoff_code' => null,
            ]);
        });

        if ($pdfPaths !== []) {
            Storage::disk('local')->delete($pdfPaths);
        }

        $this->info('INSaf demo state reset. Other student, university, administrator, and account records were not changed.');
        $this->warn('Hedera messages cannot be deleted; all previous receipts remain on the topic.');

        return self::SUCCESS;
    }
}
