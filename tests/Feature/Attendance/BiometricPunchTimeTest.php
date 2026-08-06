<?php

namespace Tests\Feature\Attendance;

use App\Models\HRM\Attendance;
use App\Models\HRM\AttendanceType;
use App\Models\HRM\BiometricDevice;
use App\Models\User;
use App\Services\Attendance\AttendancePunchService;
use App\Services\Biometric\BiometricProcessingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A biometric/device punch must be recorded at the device's REAL punch time, not the
 * server's processing time (a device may push or be back-downloaded hours later).
 * Manual/web punches must always use server time so a user cannot back-date their punch.
 *
 * ── Device time is still the basis; it is now a CORRECTED device time ───────
 *
 * A real terminal's clock can be wrong. The production MB460 (`AF6P231260266`)
 * ran exactly 1 h 59 m 56 s fast for four months, so "honour the device" wrote
 * every punch two hours late. DeviceClockService measures that offset from live
 * pushes and BiometricProcessingService applies it at ingest, from the raw
 * timestamp the device sent.
 *
 * That does NOT weaken the guarantee this file exists for — server processing
 * time is still never used as the punch moment, and a web punch still cannot
 * carry its own. It sharpens it: the basis is the device's account of WHEN,
 * adjusted by a measured, evidenced statement about how wrong that device's
 * clock is. Both halves are pinned below: the first test keeps the original
 * assertion for an unmeasured device (nothing is corrected, so device time is
 * used verbatim — the exact behaviour it always pinned), and the second proves
 * that once an offset IS measured the stored moment is the corrected device
 * time, which is neither the raw device stamp nor the server's clock.
 */
class BiometricPunchTimeTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_biometric_punch_uses_device_time_not_server_time(): void
    {
        // Server processes the punch at 14:05 (late download), but the device recorded 08:00.
        // No device clock offset is in play here (this path has no device, and an
        // unmeasured device is corrected by nothing), so the device's own time is
        // what must be stored — unchanged.
        Carbon::setTestNow(Carbon::parse('2026-06-19 14:05:00'));
        $user = User::factory()->create();

        $req = Request::create('/biometric/punch', 'POST', [
            'source' => 'biometric',
            'punch_time' => '2026-06-19 08:00:00',
            'check_type' => 'in',
        ]);
        $res = app(AttendancePunchService::class)->processPunch($user, $req);

        $this->assertSame('success', $res['status']);
        $att = Attendance::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('2026-06-19 08:00:00', Carbon::parse($att->punchin)->format('Y-m-d H:i:s'));
        $this->assertSame('2026-06-19', Carbon::parse($att->date)->format('Y-m-d'));
    }

    public function test_biometric_punch_uses_corrected_device_time_and_still_never_server_time(): void
    {
        $device = BiometricDevice::create([
            'name' => 'Gate MB460',
            'serial_number' => 'AF6P231260266',
            'protocol' => 'adms',
            'auth_token' => 'token-'.uniqid(),
            'is_active' => true,
        ]);

        $type = AttendanceType::where('slug', 'biometric')->firstOrFail();
        $type->biometricDevices()->syncWithoutDetaching([$device->id]);
        $user = User::factory()->create(['employee_id' => '42', 'attendance_type_id' => $type->id]);

        $service = app(BiometricProcessingService::class);

        // Six live pushes for an unenrolled PIN teach the server that this
        // device's clock reads 7,196 s late — the real production measurement.
        $base = Carbon::parse('2026-06-19 06:00:00');
        for ($i = 0; $i < 6; $i++) {
            $serverMoment = $base->copy()->addMinutes($i);
            Carbon::setTestNow($serverMoment);
            $service->processAttendanceLogs(
                "9999\t".$serverMoment->copy()->addSeconds(7196)->format('Y-m-d H:i:s')."\t0",
                $device,
                $device->serial_number
            );
        }

        // The employee really arrives at 08:00:00. The device stamps 09:59:56 and
        // the server does not handle the push until 14:05 (a late delivery).
        Carbon::setTestNow(Carbon::parse('2026-06-19 14:05:00'));
        $service->processAttendanceLogs("42\t2026-06-19 09:59:56\t0", $device, $device->serial_number);

        $att = Attendance::where('user_id', $user->id)->firstOrFail();
        $punchIn = Carbon::parse($att->punchin)->format('Y-m-d H:i:s');

        // Corrected device time…
        $this->assertSame('2026-06-19 08:00:00', $punchIn);
        // …not the device's raw stamp…
        $this->assertNotSame('2026-06-19 09:59:56', $punchIn);
        // …and, as ever, not the server's processing time.
        $this->assertNotSame('2026-06-19 14:05:00', $punchIn);

        // The device's own account survives beside the corrected one.
        $log = DB::table('biometric_att_logs')->where('user_pin', '42')->first();
        $this->assertStringStartsWith('2026-06-19 09:59:56', (string) $log->punch_time);
        $this->assertStringStartsWith('2026-06-19 08:00:00', (string) $log->corrected_punch_time);
        $this->assertSame(-7196, (int) $log->clock_offset_applied_seconds);
    }

    public function test_web_punch_ignores_spoofed_punch_time_and_uses_server_time(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-19 09:30:00'));
        $user = User::factory()->create();

        // A web request carrying punch_time but NO trusted source must be ignored.
        $req = new Request(['check_type' => 'in', 'punch_time' => '2026-06-19 06:00:00']);
        $res = app(AttendancePunchService::class)->processPunch($user, $req);

        $this->assertSame('success', $res['status']);
        $att = Attendance::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('2026-06-19 09:30:00', Carbon::parse($att->punchin)->format('Y-m-d H:i:s'));
    }
}
