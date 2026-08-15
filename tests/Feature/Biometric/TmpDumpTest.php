<?php

namespace Tests\Feature\Biometric;

use App\Models\HRM\BiometricDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TmpDumpTest extends TestCase
{
    use RefreshDatabase;

    public function test_dump(): void
    {
        $device = BiometricDevice::create([
            'name' => 'Main Gate MB460', 'serial_number' => 'AF6P231260266',
            'protocol' => 'adms', 'auth_token' => 'tok', 'is_active' => true,
            'clock_offset_seconds' => 7195, 'clock_offset_samples' => 25,
            'clock_offset_measured_at' => now(),
        ]);

        $att = function ($user, $date, $in, $out) {
            return DB::table('attendances')->insertGetId([
                'user_id' => $user->id, 'date' => $date, 'punchin' => $in, 'punchout' => $out,
                'policy_status' => 'accepted', 'needs_approval' => false,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        };
        $log = function ($user, $t, $c, $st) use ($device) {
            DB::table('biometric_att_logs')->insert([
                'biometric_device_id' => $device->id, 'serial_number' => $device->serial_number,
                'user_pin' => (string) $user->employee_id, 'user_id' => $user->id,
                'punch_time' => $t, 'check_type' => $c, 'punch_status' => $st,
                'occurred_at' => $t, 'created_at' => now(), 'updated_at' => now(),
            ]);
        };
        $claim = function ($user, $id, $col, $before, $after, $date) use ($device) {
            DB::table('attendance_clock_corrections')->insert([
                'attendance_id' => $id, 'punch_column' => $col,
                'biometric_device_id' => $device->id, 'device_serial' => $device->serial_number,
                'user_id' => $user->id, 'attendance_date' => $date, 'applied_seconds' => -7200,
                'punchin_before' => $col === 'punchin' ? $before : null,
                'punchin_after' => $col === 'punchin' ? $after : null,
                'punchout_before' => $col === 'punchout' ? $before : null,
                'punchout_after' => $col === 'punchout' ? $after : null,
                'payload' => json_encode(['id' => $id]), 'run_id' => '11111111-1111-4111-8111-111111111111',
                'archived_at' => now(), 'applied_at' => now(),
            ]);
        };

        // --- Already fully corrected by the first run (the 459). Must not move. ---
        for ($i = 0; $i < 3; $i++) {
            $u = User::factory()->create();
            $id = $att($u, '2026-06-0'.($i + 1), '2026-06-0'.($i + 1).' 09:00:00', '2026-06-0'.($i + 1).' 17:00:00');
            $log($u, '2026-06-0'.($i + 1).' 11:00:00', 'in', 'processed');
            $log($u, '2026-06-0'.($i + 1).' 19:00:00', 'out', 'processed');
            $claim($u, $id, 'punchin', '2026-06-0'.($i + 1).' 11:00:00', '2026-06-0'.($i + 1).' 09:00:00', '2026-06-0'.($i + 1));
            $claim($u, $id, 'punchout', '2026-06-0'.($i + 1).' 19:00:00', '2026-06-0'.($i + 1).' 17:00:00', '2026-06-0'.($i + 1));
        }

        // --- Attendance 9901: punch-in done, punch-out residual (duplicate log). ---
        $hannan = User::factory()->create(['name' => 'Md. Abdul Hannan']);
        $id9901 = $att($hannan, '2026-07-04', '2026-07-04 09:40:46', '2026-07-04 19:09:48');
        $log($hannan, '2026-07-04 11:40:46', 'in', 'processed');
        $log($hannan, '2026-07-04 19:09:48', 'out', 'duplicate');
        $claim($hannan, $id9901, 'punchin', '2026-07-04 11:40:46', '2026-07-04 09:40:46', '2026-07-04');

        // --- Untouched rows whose only log is `duplicate` (the residual 12). ---
        foreach ([['2026-07-06', '11:02:31', '19:14:09'], ['2026-07-07', '10:58:44', '19:03:22']] as [$d, $i2, $o2]) {
            $u = User::factory()->create();
            $id = $att($u, $d, "$d $i2", "$d $o2");
            $log($u, "$d $i2", 'in', 'duplicate');
            $log($u, "$d $o2", 'out', 'duplicate');
        }

        // --- A downloaded-only anomaly: reported, never corrected. ---
        $u = User::factory()->create();
        $id = $att($u, '2026-07-08', '2026-07-08 11:11:11', null);
        $log($u, '2026-07-08 11:11:11', 'in', 'downloaded');

        Artisan::call('biometric:correct-historical-clock-offset', [
            '--device' => 'AF6P231260266', '--seconds' => 7200, '--samples' => 10,
        ]);

        file_put_contents(__DIR__.'/../../../storage/dump.txt', Artisan::output());
        $this->assertTrue(true);
    }
}
