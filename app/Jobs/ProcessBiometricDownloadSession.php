<?php

namespace App\Jobs;

use App\Models\HRM\BiometricDownloadSession;
use App\Services\Biometric\BiometricProcessingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessBiometricDownloadSession implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 360;

    protected BiometricDownloadSession $session;

    /**
     * Create a new job instance.
     */
    public function __construct(BiometricDownloadSession $session)
    {
        $this->session = $session;
        $this->queue = 'biometric';
    }

    /**
     * Execute the job.
     */
    public function handle(BiometricProcessingService $biometricService): void
    {
        $this->session->refresh();

        // If already completed or failed, we are done
        if (in_array($this->session->status, ['completed', 'failed', 'partial'])) {
            return;
        }

        // If it's been more than 5 minutes since the session was created, time out
        if ($this->session->created_at->addMinutes(5)->isPast()) {
            $this->session->markFailed('Timeout: The device did not respond within 5 minutes.');
            if ($this->session->command) {
                $this->session->command->markAsFailed('Timeout: Device did not request this command within 5 minutes.');
            }

            return;
        }

        // Check if command is still pending/sent
        $command = $this->session->command;
        if ($command) {
            if ($command->status === 'pending') {
                // Device hasn't connected to pick it up yet. Let's release the job to check again in 15 seconds.
                $this->release(15);

                return;
            }

            if ($command->status === 'sent') {
                // Command has been sent, device is processing or pushing logs.
                if ($this->session->status === 'pending') {
                    $this->session->markInProgress();
                }
                // Release to check again in 15 seconds.
                $this->release(15);

                return;
            }

            if ($command->status === 'failed') {
                $this->session->markFailed('Device failed to execute the download command: '.($command->error_message ?? 'Unknown device error'));

                return;
            }

            if ($command->status === 'executed') {
                // Handled by the webhook callback, but as a fallback/failsafe:
                if ($this->session->status === 'in_progress' || $this->session->status === 'pending') {
                    if ($this->session->failed_count > 0 && $this->session->processed_count > 0) {
                        $this->session->markPartial();
                    } elseif ($this->session->failed_count > 0 && $this->session->processed_count == 0 && $this->session->total_records > 0) {
                        $this->session->markFailed('Completed with errors. No records were processed successfully.');
                    } else {
                        $this->session->markCompleted();
                    }

                    // Terminal, successful transition — this is the only place in this
                    // job where a session settles (every other branch either releases
                    // or fails), so importing here runs exactly once per session and
                    // never on a release path.
                    if (in_array($this->session->status, ['completed', 'partial'], true)) {
                        $this->importDownloadedLogs($biometricService);
                    }
                }

                return;
            }
        } else {
            // No command linked? Something went wrong
            $this->session->markFailed('No active command linked to this session.');
        }
    }

    /**
     * Drain the rows this session just captured into real attendance.
     *
     * A download session only parks device logs in biometric_att_logs with
     * punch_status = 'downloaded'; this is what turns them into attendance.
     *
     * Failures are swallowed on purpose: the session state has already been
     * written, and letting an import error bubble would fail the job, retry it
     * (up to $tries) and — because the session is now terminal — hit the early
     * return at the top of handle() and accomplish nothing. The every-15-minutes
     * biometric:import-downloaded schedule is the backstop for anything missed.
     */
    protected function importDownloadedLogs(BiometricProcessingService $biometricService): void
    {
        try {
            $result = $biometricService->importDownloadedLogs($this->session);

            Log::info('Biometric download session auto-imported on completion', [
                'session_id' => $this->session->id,
                'status' => $this->session->status,
                'result' => $result,
            ]);
        } catch (\Throwable $e) {
            Log::error('Biometric download session auto-import failed: '.$e->getMessage(), [
                'session_id' => $this->session->id,
                'status' => $this->session->status,
                'exception' => $e,
            ]);
        }
    }
}
