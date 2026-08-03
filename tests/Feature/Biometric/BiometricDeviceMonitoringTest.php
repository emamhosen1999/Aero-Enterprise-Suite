<?php

namespace Tests\Feature\Biometric;

use App\Http\Controllers\Settings\BiometricDeviceController;
use App\Models\HRM\BiometricDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Covers the two monitoring endpoints on the biometric settings panel that the
 * React BiometricPanel polls and paginates against:
 *
 *  - biometric-devices.logs (getAdmsLogs) — used to be pointed at a hardcoded
 *    storage/logs/laravel.log, which does not exist under the `daily` driver, and
 *    reported fabricated pagination (`current_page` was the literal 1, `total`
 *    was the length of a fixed tail slice) to a UI that renders real page
 *    controls off those numbers. Both failures were silent, and the one job this
 *    viewer has is showing the EnsureAdmsDeviceAuthorized rejections that explain
 *    why a device is being refused.
 *
 *  - biometric-devices.health (getHealthMetrics) — used to shell out to a
 *    blocking `ping -w 1000` once per device on an endpoint the panel refreshes
 *    every 30 seconds. Liveness is now read from last_heartbeat_at, which is both
 *    cheap and more correct for a push-protocol terminal.
 *
 * The log tests deliberately repoint the logging channel at a scratch directory
 * rather than writing into storage/logs: it keeps the assertions deterministic
 * (the developer's real log is not in the sample) and leaves no litter behind.
 */
class BiometricDeviceMonitoringTest extends TestCase
{
    use RefreshDatabase;

    private string $logDir;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::firstOrCreate(['name' => 'Admin']);
        Permission::firstOrCreate(['name' => 'attendance.settings']);

        PingSpyBiometricDeviceController::$pingedIps = [];

        $this->logDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'adms-log-test-'.uniqid();
        @mkdir($this->logDir, 0777, true);

        // Pin the resolver onto a scratch directory using the `daily` driver —
        // the exact layout (laravel-Y-m-d.log) the old hardcoded path missed.
        config([
            'logging.default' => 'daily',
            'logging.channels.daily.path' => $this->logDir.DIRECTORY_SEPARATOR.'laravel.log',
        ]);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->logDir.DIRECTORY_SEPARATOR.'*') ?: [] as $file) {
            @unlink($file);
        }

        @rmdir($this->logDir);

        parent::tearDown();
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->givePermissionTo('attendance.settings');

        return $admin;
    }

    private function device(array $overrides = []): BiometricDevice
    {
        return BiometricDevice::create(array_merge([
            'name' => 'Gate MB460',
            'serial_number' => 'SN-'.uniqid(),
            'protocol' => 'adms',
            'is_active' => true,
        ], $overrides));
    }

    /**
     * Write a file using the `daily` channel's on-disk name, which is what the
     * previously hardcoded storage/logs/laravel.log path could never find.
     */
    private function writeDailyLog(string $date, array $lines): string
    {
        $path = $this->logDir.DIRECTORY_SEPARATOR.'laravel-'.$date.'.log';
        file_put_contents($path, implode("\n", $lines)."\n");

        return $path;
    }

    // ------------------------------------------------------------- ADMS logs

    public function test_adms_logs_are_found_under_the_daily_filename_layout(): void
    {
        $this->writeDailyLog('2026-08-01', [
            '[2026-08-01 09:15:00] local.WARNING: ADMS device rejected: unknown serial SN-9001',
            '[2026-08-01 09:15:01] local.INFO: Unrelated cache warm-up finished',
            '[2026-08-01 09:16:30] local.ERROR: Biometric push failed for device 4',
        ]);

        // Nothing is named laravel.log — the old implementation read only that.
        $this->assertFileDoesNotExist($this->logDir.DIRECTORY_SEPARATOR.'laravel.log');

        $response = $this->actingAs($this->admin())
            ->getJson(route('biometric-devices.logs'));

        $response->assertOk();

        $messages = array_column($response->json('logs'), 'message');

        $this->assertCount(2, $messages, 'Only the ADMS/biometric lines should match.');
        $this->assertStringContainsString('ADMS device rejected: unknown serial SN-9001', $messages[1]);
        $this->assertStringContainsString('Biometric push failed for device 4', $messages[0]);

        // The unrelated line must not leak into the ADMS viewer.
        foreach ($messages as $message) {
            $this->assertStringNotContainsString('cache warm-up', $message);
        }

        // Parsed metadata the UI colours and sorts by.
        $logs = $response->json('logs');
        $this->assertSame('error', $logs[0]['level']);
        $this->assertSame('2026-08-01 09:16:30', $logs[0]['created_at']);
        $this->assertSame('warning', $logs[1]['level']);
        $this->assertSame('laravel-2026-08-01.log', $logs[1]['file']);

        $this->assertSame(2, $response->json('total'));
        $this->assertSame(['laravel-2026-08-01.log'], $response->json('log_files'));
    }

    public function test_adms_logs_return_an_empty_non_erroring_response_when_no_log_file_exists(): void
    {
        $this->assertSame([], glob($this->logDir.DIRECTORY_SEPARATOR.'*'));

        $response = $this->actingAs($this->admin())
            ->getJson(route('biometric-devices.logs'));

        $response->assertOk();

        // Every key the React panel consumes is still present, and the response
        // says outright that there was no file rather than implying "all clear".
        $response->assertJson([
            'logs' => [],
            'total' => 0,
            'current_page' => 1,
            'per_page' => 100,
            'last_page' => 1,
            'from' => null,
            'to' => null,
            'scanned_lines' => 0,
            'scan_truncated' => false,
            'log_files' => [],
        ]);

        $this->assertNotEmpty($response->json('message'));
        $this->assertNull($response->json('error'));
    }

    public function test_adms_log_pagination_reports_truthful_numbers_and_pages_through_distinct_rows(): void
    {
        // 24 matching lines plus noise that must not be counted.
        $lines = [];
        for ($i = 1; $i <= 24; $i++) {
            $lines[] = sprintf(
                '[2026-08-01 %02d:00:00] local.INFO: ADMS heartbeat entry number %02d',
                $i % 24,
                $i
            );
        }
        $lines[] = '[2026-08-01 23:59:00] local.INFO: Queue worker restarted';
        $lines[] = '[2026-08-01 23:59:10] local.INFO: Scheduler ran 3 tasks';
        $lines[] = '[2026-08-01 23:59:20] local.DEBUG: Cache flushed';

        $this->writeDailyLog('2026-08-01', $lines);

        $admin = $this->admin();

        $page1 = $this->actingAs($admin)
            ->getJson(route('biometric-devices.logs', ['page' => 1, 'per_page' => 10]))
            ->assertOk();

        $this->assertSame(24, $page1->json('total'), 'total must count matched lines, not a fixed tail slice.');
        $this->assertSame(1, $page1->json('current_page'));
        $this->assertSame(10, $page1->json('per_page'));
        $this->assertSame(3, $page1->json('last_page'));
        $this->assertSame(1, $page1->json('from'));
        $this->assertSame(10, $page1->json('to'));
        $this->assertCount(10, $page1->json('logs'));
        $this->assertFalse($page1->json('scan_truncated'));

        $page2 = $this->actingAs($admin)
            ->getJson(route('biometric-devices.logs', ['page' => 2, 'per_page' => 10]))
            ->assertOk();

        // current_page must be the page actually served, not a hardcoded 1.
        $this->assertSame(2, $page2->json('current_page'));
        $this->assertSame(24, $page2->json('total'));
        $this->assertSame(11, $page2->json('from'));
        $this->assertSame(20, $page2->json('to'));
        $this->assertCount(10, $page2->json('logs'));

        $page3 = $this->actingAs($admin)
            ->getJson(route('biometric-devices.logs', ['page' => 3, 'per_page' => 10]))
            ->assertOk();

        $this->assertSame(3, $page3->json('current_page'));
        $this->assertSame(21, $page3->json('from'));
        $this->assertSame(24, $page3->json('to'));
        $this->assertCount(4, $page3->json('logs'));

        $one = array_column($page1->json('logs'), 'message');
        $two = array_column($page2->json('logs'), 'message');
        $three = array_column($page3->json('logs'), 'message');

        $this->assertSame([], array_intersect($one, $two), 'Page 2 must return different rows than page 1.');
        $this->assertSame([], array_intersect($one, $three));
        $this->assertSame([], array_intersect($two, $three));

        // The three pages together are exactly the matched set, and the noise
        // lines are in none of them.
        $all = array_merge($one, $two, $three);
        $this->assertCount(24, array_unique($all));

        foreach ($all as $message) {
            $this->assertStringContainsString('ADMS heartbeat entry number', $message);
        }

        // Newest-first: entry 24 leads page 1, entry 1 closes page 3.
        $this->assertStringContainsString('number 24', $one[0]);
        $this->assertStringContainsString('number 01', $three[3]);
    }

    public function test_adms_log_page_beyond_the_last_is_clamped_and_says_which_page_it_served(): void
    {
        $this->writeDailyLog('2026-08-01', [
            '[2026-08-01 09:00:00] local.INFO: ADMS one',
            '[2026-08-01 09:00:01] local.INFO: ADMS two',
        ]);

        $response = $this->actingAs($this->admin())
            ->getJson(route('biometric-devices.logs', ['page' => 99, 'per_page' => 10]))
            ->assertOk();

        $this->assertSame(1, $response->json('last_page'));
        // Reports the page it actually served rather than echoing the request.
        $this->assertSame(1, $response->json('current_page'));
        $this->assertCount(2, $response->json('logs'));
        $this->assertSame(2, $response->json('total'));
    }

    public function test_adms_logs_span_multiple_daily_files(): void
    {
        $older = $this->writeDailyLog('2026-07-30', [
            '[2026-07-30 08:00:00] local.INFO: ADMS older day entry',
        ]);
        $newer = $this->writeDailyLog('2026-07-31', [
            '[2026-07-31 08:00:00] local.INFO: ADMS newer day entry',
        ]);

        // Make the ordering deterministic regardless of write speed.
        touch($older, time() - 3600);
        touch($newer, time());
        clearstatcache();

        $response = $this->actingAs($this->admin())
            ->getJson(route('biometric-devices.logs'))
            ->assertOk();

        $messages = array_column($response->json('logs'), 'message');

        $this->assertCount(2, $messages);
        $this->assertStringContainsString('newer day entry', $messages[0]);
        $this->assertStringContainsString('older day entry', $messages[1]);
        $this->assertSame(2, $response->json('total'));
        $this->assertSame(
            ['laravel-2026-07-31.log', 'laravel-2026-07-30.log'],
            $response->json('log_files')
        );
    }

    public function test_adms_logs_require_the_attendance_settings_permission(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson(route('biometric-devices.logs'))
            ->assertForbidden();
    }

    // ------------------------------------------------------- Health / no ping

    public function test_health_endpoint_never_shells_out_to_ping(): void
    {
        $this->app->bind(BiometricDeviceController::class, PingSpyBiometricDeviceController::class);

        // Six devices, none of which will answer. Under the old implementation
        // this was six sequential blocking `ping -w 1000` calls on an endpoint
        // the panel polls every 30 seconds.
        $this->device(['name' => 'No IP', 'serial_number' => 'SN-NOIP', 'ip_address' => null]);

        for ($i = 1; $i <= 5; $i++) {
            $this->device([
                'name' => 'Unroutable '.$i,
                'serial_number' => 'SN-UNROUTABLE-'.$i,
                // TEST-NET-3, reserved for documentation: guaranteed not to answer.
                'ip_address' => '203.0.113.'.$i,
            ]);
        }

        $started = microtime(true);

        $response = $this->actingAs($this->admin())
            ->getJson(route('biometric-devices.health'))
            ->assertOk();

        $elapsed = microtime(true) - $started;

        // The deterministic assertion: the ping seam was never entered.
        $this->assertSame(
            [],
            PingSpyBiometricDeviceController::$pingedIps,
            'getHealthMetrics must not perform ICMP; it is polled every 30 seconds.'
        );

        $response->assertJsonPath('meta.ping_performed', false);
        $response->assertJsonPath('meta.liveness_source', 'last_heartbeat_at');

        foreach ($response->json('devices') as $device) {
            // latency stays in the payload (the table column renders it) but is
            // honestly null and explicitly flagged as unmeasured rather than
            // being passed off as "measured, unreachable".
            $this->assertArrayHasKey('latency', $device);
            $this->assertNull($device['latency']);
            $this->assertFalse($device['latency_measured']);
            $this->assertSame('last_heartbeat_at', $device['liveness_source']);
        }

        // Secondary, generous guard: six blocking pings could not finish in this.
        $this->assertLessThan(
            5.0,
            $elapsed,
            'Health metrics took '.round($elapsed, 2).'s — that smells like per-device ICMP.'
        );
    }

    public function test_device_without_ip_and_device_with_unroutable_ip_are_shaped_identically(): void
    {
        $this->app->bind(BiometricDeviceController::class, PingSpyBiometricDeviceController::class);

        $heartbeat = now()->subMinutes(30);

        $this->device([
            'serial_number' => 'SN-SHAPE-NOIP',
            'ip_address' => null,
            'last_heartbeat_at' => $heartbeat,
        ]);
        $this->device([
            'serial_number' => 'SN-SHAPE-UNROUTABLE',
            'ip_address' => '203.0.113.77',
            'last_heartbeat_at' => $heartbeat,
        ]);

        $devices = collect(
            $this->actingAs($this->admin())
                ->getJson(route('biometric-devices.health'))
                ->assertOk()
                ->json('devices')
        )->keyBy('serial_number');

        $noIp = $devices['SN-SHAPE-NOIP'];
        $unroutable = $devices['SN-SHAPE-UNROUTABLE'];

        $this->assertSame(array_keys($noIp), array_keys($unroutable));

        // Everything except the identity/address fields must match: reachability
        // is derived from the heartbeat, so having an IP changes nothing.
        foreach (['is_online', 'latency', 'latency_measured', 'liveness_source', 'health_score', 'status'] as $key) {
            $this->assertSame($noIp[$key], $unroutable[$key], "Field {$key} diverged between the two devices.");
        }

        $this->assertSame([], PingSpyBiometricDeviceController::$pingedIps);
    }

    public function test_health_status_reflects_last_heartbeat_for_online_and_offline_devices(): void
    {
        $this->device([
            'name' => 'Live Terminal',
            'serial_number' => 'SN-LIVE',
            'ip_address' => '203.0.113.10',
            'last_heartbeat_at' => now()->subMinute(),
        ]);

        $this->device([
            'name' => 'Silent Terminal',
            'serial_number' => 'SN-SILENT',
            'ip_address' => '203.0.113.11',
            'last_heartbeat_at' => now()->subHours(3),
        ]);

        $this->device([
            'name' => 'Never Seen',
            'serial_number' => 'SN-NEVER',
            'last_heartbeat_at' => null,
        ]);

        $response = $this->actingAs($this->admin())
            ->getJson(route('biometric-devices.health'))
            ->assertOk();

        $devices = collect($response->json('devices'))->keyBy('serial_number');

        $live = $devices['SN-LIVE'];
        $this->assertTrue($live['is_online']);
        $this->assertSame(100, $live['health_score']);
        $this->assertSame('healthy', $live['status']);
        $this->assertNotNull($live['last_heartbeat']);

        // Offline, older than 5 minutes and older than an hour: 100-50-30-20.
        $silent = $devices['SN-SILENT'];
        $this->assertFalse($silent['is_online']);
        $this->assertSame(0, $silent['health_score']);
        $this->assertSame('critical', $silent['status']);

        // Never heard from: offline only, so no heartbeat-age deductions apply.
        $never = $devices['SN-NEVER'];
        $this->assertFalse($never['is_online']);
        $this->assertNull($never['last_heartbeat']);
        $this->assertSame(50, $never['health_score']);
        $this->assertSame('warning', $never['status']);

        $response->assertJsonPath('summary.total', 3)
            ->assertJsonPath('summary.online', 1)
            ->assertJsonPath('summary.offline', 2)
            ->assertJsonPath('summary.healthy', 1)
            ->assertJsonPath('summary.warning', 1)
            ->assertJsonPath('summary.critical', 1);
    }

    public function test_health_endpoint_requires_the_attendance_settings_permission(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson(route('biometric-devices.health'))
            ->assertForbidden();
    }

    // ------------------------------------------------------ Tail reader seam

    public function test_tail_reader_returns_newest_lines_first_and_flags_a_budget_cut(): void
    {
        $path = $this->logDir.DIRECTORY_SEPARATOR.'tail-probe.log';
        file_put_contents($path, "L1\nL2\nL3\nL4\nL5\nL6\n");

        /** @var PingSpyBiometricDeviceController $controller */
        $controller = $this->app->make(PingSpyBiometricDeviceController::class);

        // Whole file fits inside the budget: everything, oldest line last.
        $reachedStart = null;
        $all = $controller->tailLinesForTest($path, 10, $reachedStart);
        $this->assertSame(['L6', 'L5', 'L4', 'L3', 'L2', 'L1'], $all);
        $this->assertTrue($reachedStart);

        // Budget smaller than the file. The whole file sits inside one 64 KB
        // read block, so the byte cursor still lands on 0 — the reader must not
        // take that as "fully scanned" and let the caller publish a floor as an
        // exact total.
        $reachedStart = null;
        $partial = $controller->tailLinesForTest($path, 3, $reachedStart);
        $this->assertSame(['L6', 'L5', 'L4'], $partial);
        $this->assertFalse($reachedStart);
    }

    public function test_tail_reader_handles_a_file_with_no_trailing_newline(): void
    {
        $path = $this->logDir.DIRECTORY_SEPARATOR.'tail-no-newline.log';
        file_put_contents($path, "A\nB\nC");

        /** @var PingSpyBiometricDeviceController $controller */
        $controller = $this->app->make(PingSpyBiometricDeviceController::class);

        $reachedStart = null;
        $this->assertSame(['C', 'B', 'A'], $controller->tailLinesForTest($path, 10, $reachedStart));
        $this->assertTrue($reachedStart);
    }

    public function test_tail_reader_returns_nothing_for_an_empty_file(): void
    {
        $path = $this->logDir.DIRECTORY_SEPARATOR.'tail-empty.log';
        file_put_contents($path, '');

        /** @var PingSpyBiometricDeviceController $controller */
        $controller = $this->app->make(PingSpyBiometricDeviceController::class);

        $reachedStart = null;
        $this->assertSame([], $controller->tailLinesForTest($path, 10, $reachedStart));
        $this->assertTrue($reachedStart);
    }
}

/**
 * Substitutes the one seam that shells out, so a test can prove a polled
 * endpoint never reaches it, and exposes the protected tail reader.
 *
 * It records instead of throwing: a recorded call produces a readable "expected
 * [] got ['203.0.113.1']" failure rather than a 500 that has to be unpicked.
 */
class PingSpyBiometricDeviceController extends BiometricDeviceController
{
    /** @var array<int, mixed> */
    public static array $pingedIps = [];

    protected function executePing($ip)
    {
        static::$pingedIps[] = $ip;

        return false;
    }

    public function tailLinesForTest(string $path, int $maxLines, ?bool &$reachedStartOfFile = null): array
    {
        return $this->tailLines($path, $maxLines, $reachedStartOfFile);
    }
}
