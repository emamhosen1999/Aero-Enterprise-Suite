<?php

namespace App\Console\Commands;

use App\Jobs\ProcessBiometricDownloadSession;
use App\Models\HRM\BiometricDevice;
use App\Services\Biometric\BiometricProcessingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ScheduledBiometricLogDownload extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'biometric:scheduled-log-download
                            {--hours=6 : Time threshold in hours since last sync}
                            {--days=3 : Rolling window, in days, of attendance history the device is asked to re-push}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scheduled download of attendance logs from all active ADMS devices';

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
        $hoursThreshold = (int) $this->option('hours');
        $windowDays = max(0, (int) $this->option('days'));

        // Bound what we ask the device for. CHECK_ATTLOG with no payload defaults to
        // StartTime=2000-01-01, i.e. "re-push your ENTIRE attendance history", and the
        // capture branch in processAttendanceLogs() inserts every pushed line without a
        // dedupe. Unbounded on a 4-hour cadence was already bad; on a 30-minute cadence
        // biometric_att_logs would grow by the full device history 48 times a day. A
        // rolling window costs nothing (rows already imported come back as duplicates)
        // and keeps the table proportional to recent traffic.
        //
        // Only the SCHEDULED path is bounded: a manual/UI-triggered download still
        // passes no payload, so an admin asking for a full backfill still gets one.
        $payload = [
            'start_time' => now()->subDays($windowDays)->format('Y-m-d H:i:s'),
            'end_time' => now()->addDay()->format('Y-m-d H:i:s'),
        ];

        $this->info("Starting scheduled log download for devices not synced in the last {$hoursThreshold} hour(s)...");
        $this->info("Requesting logs from {$payload['start_time']} to {$payload['end_time']} ({$windowDays}-day window).");

        // Fetch active ADMS devices
        $devices = BiometricDevice::active()->get()->filter(function ($device) {
            return $device->isAdms();
        });

        if ($devices->isEmpty()) {
            $this->info('No active ADMS devices found.');

            return 0;
        }

        $triggeredCount = 0;
        foreach ($devices as $device) {
            // Check if last download was more than threshold hours ago (or never)
            $shouldDownload = is_null($device->last_log_download_at) ||
                              $device->last_log_download_at->lt(now()->subHours($hoursThreshold));

            if (! $shouldDownload) {
                $this->info("Skipping device {$device->name} (serial: {$device->serial_number}) - last synced recently.");

                continue;
            }

            try {
                $this->info("Triggering download for device {$device->name}...");
                $session = $this->biometricService->initiateLogDownload(
                    $device,
                    'scheduled',
                    null,
                    $payload
                );

                // Dispatch monitoring job
                ProcessBiometricDownloadSession::dispatch($session);

                $triggeredCount++;
            } catch (\Exception $e) {
                $this->error("Failed to trigger download for device {$device->name}: ".$e->getMessage());
                Log::error("Scheduled log download failed for device {$device->id}: ".$e->getMessage());
            }
        }

        $this->info("Triggered downloads for {$triggeredCount} device(s).");

        return 0;
    }
}
