<?php

namespace App\Console\Commands;

use App\Models\HRM\BiometricAttLog;
use App\Models\HRM\BiometricDownloadSession;
use App\Services\Biometric\BiometricProcessingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Second half of the biometric capture-then-import flow.
 *
 * A download session only CAPTURES device logs into biometric_att_logs with
 * punch_status = 'downloaded'; nothing turns them into attendance. This command
 * replays those captured rows through the live punch rules.
 */
class ImportDownloadedBiometricLogs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'biometric:import-downloaded {session? : Session id; omit to import all completed sessions with pending downloaded rows}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import previously downloaded biometric attendance logs into real attendance records';

    protected BiometricProcessingService $biometricService;

    /**
     * Create a new command instance.
     */
    public function __construct(BiometricProcessingService $biometricService)
    {
        parent::__construct();
        $this->biometricService = $biometricService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $sessionId = $this->argument('session');

        if ($sessionId) {
            $session = BiometricDownloadSession::with('device')->find($sessionId);

            if (! $session) {
                $this->error("Download session {$sessionId} not found.");

                return 1;
            }

            $sessions = collect([$session]);
        } else {
            $sessions = $this->sessionsWithPendingRows();

            if ($sessions->isEmpty()) {
                $this->info('No completed download sessions with pending downloaded logs.');

                return 0;
            }

            $this->info("Found {$sessions->count()} session(s) with pending downloaded logs.");
        }

        $totals = ['imported' => 0, 'duplicates' => 0, 'failed' => 0, 'skipped_unknown' => 0];

        foreach ($sessions as $session) {
            $label = "session #{$session->id}".($session->device ? " ({$session->device->name})" : '');
            $this->info("Importing {$label}...");

            try {
                $result = $this->biometricService->importDownloadedLogs($session);
            } catch (\Throwable $e) {
                $this->error("Failed to import {$label}: ".$e->getMessage());
                Log::error("Biometric downloaded-log import failed for session {$session->id}: ".$e->getMessage());

                continue;
            }

            foreach ($totals as $key => $value) {
                $totals[$key] = $value + ($result[$key] ?? 0);
            }

            $this->info(sprintf(
                '  imported: %d, duplicates: %d, failed: %d, skipped (unknown user): %d',
                $result['imported'],
                $result['duplicates'],
                $result['failed'],
                $result['skipped_unknown']
            ));
        }

        $this->info(sprintf(
            'Done. Total imported: %d, duplicates: %d, failed: %d, skipped (unknown user): %d',
            $totals['imported'],
            $totals['duplicates'],
            $totals['failed'],
            $totals['skipped_unknown']
        ));

        return 0;
    }

    /**
     * Finished sessions (completed / partial) whose device still holds captured
     * `downloaded` rows. Pending / in-progress sessions are excluded on purpose:
     * the device may still be pushing, and replaying a half-received batch would
     * mis-pair in/out punches. Oldest first so history replays chronologically.
     */
    protected function sessionsWithPendingRows()
    {
        $deviceIds = BiometricAttLog::where('punch_status', 'downloaded')
            ->distinct()
            ->pluck('biometric_device_id')
            ->filter()
            ->all();

        if (empty($deviceIds)) {
            return collect();
        }

        return BiometricDownloadSession::with('device')
            ->whereIn('status', ['completed', 'partial'])
            ->whereIn('biometric_device_id', $deviceIds)
            ->orderBy('created_at')
            ->get();
    }
}
