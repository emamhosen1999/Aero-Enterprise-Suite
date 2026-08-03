<?php

namespace Tests\Feature\Biometric;

use App\Models\HRM\Attendance;
use App\Models\HRM\AttendanceType;
use App\Models\HRM\BiometricDevice;
use App\Models\HRM\BiometricDeviceCommand;
use App\Models\User;
use App\Services\Biometric\DeviceCapabilityService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * ZKTeco ADMS capability discovery and device-internal settings.
 *
 * Three separate things are pinned here because they are one feature:
 *
 *  1. The device→server push allowlist. `admsPush()` used to fall through to the
 *     attendance parser for every table it did not recognise, so a BIODATA or
 *     ATTPHOTO push (matrix §1) was parsed as punches. It must now be skipped
 *     deliberately — while still answering the plain-text OK a ZKTeco unit
 *     needs, because anything else makes the device retry forever.
 *  2. Registry ingestion (`table=options&c=registry`), which was ignored.
 *  3. The settings/capability endpoints, including the guard that stops a
 *     strand-the-device key being written without an explicit confirmation.
 */
class DeviceCapabilityEndpointsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::firstOrCreate(['name' => 'Admin']);
        Permission::firstOrCreate(['name' => 'attendance.settings']);
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
     * A user the punch pipeline will actually accept: biometric attendance type,
     * and this device inside that type's zone. Mirrors BiometricSyncEngineTest.
     */
    private function zonedUser(string $employeeId, BiometricDevice $device): User
    {
        // Seeded by 2026_05_12_000003_seed_biometric_attendance_type.
        $type = AttendanceType::where('slug', 'biometric')->firstOrFail();
        $type->biometricDevices()->syncWithoutDetaching([$device->id]);

        return User::factory()->create([
            'employee_id' => $employeeId,
            'attendance_type_id' => $type->id,
        ]);
    }

    private function push(string $serial, string $query, string $body)
    {
        $uri = '/iclock/cdata?SN='.rawurlencode($serial).$query;

        return $this->call('POST', $uri, [], [], [], ['CONTENT_TYPE' => 'text/plain'], $body);
    }

    // ───────────────────────────── Task 1: unknown-table allowlist

    public function test_biodata_push_is_not_parsed_as_attendance(): void
    {
        User::factory()->create(['employee_id' => '42']);
        $device = $this->device();

        // A realistic BIODATA line: it superficially resembles a punch (tabs,
        // a PIN, a timestamp-ish field) which is exactly why the old
        // fallthrough mis-ingested it.
        $body = "PIN=42\tNo=0\tIndex=0\tValid=1\tDuress=0\tType=1\tMajorVer=10\tMinorVer=0\tFormat=0\tTmp=ABCDEF==";

        $response = $this->push($device->serial_number, '&table=BIODATA', $body);

        $response->assertOk();
        $this->assertSame('OK', $response->getContent());
        $this->assertDatabaseCount('biometric_att_logs', 0);
        $this->assertDatabaseMissing('biometric_att_logs', ['serial_number' => $device->serial_number]);
    }

    public function test_attphoto_push_is_not_parsed_as_attendance(): void
    {
        // The handshake advertises ATTPHOTOStamp, so this push is one we
        // actively invite; it must be skipped, not misparsed.
        $device = $this->device();

        $body = "PIN=42\tSN=0\tsize=1024\tCMD=uploadphoto\r\nBINARYJUNK\x00\x01\x02";

        $response = $this->push($device->serial_number, '&table=ATTPHOTO', $body);

        $response->assertOk();
        $this->assertSame('OK', $response->getContent());
        $this->assertDatabaseCount('biometric_att_logs', 0);
    }

    /**
     * The full blast radius of the old fallthrough, pinned separately.
     *
     * `processAttendanceLogs()` splits on tabs and only requires parts[0] and
     * parts[1] to be non-empty. A BIODATA line therefore parsed as
     * PIN="PIN=42", DateTime="No=0" — which passes that check — and went on to
     * `resolveOrCreateUser()`, which creates a soft-deleted placeholder
     * `User` for any PIN it has never seen. So the real damage was not only
     * junk in biometric_att_logs: every unhandled push could mint bogus user
     * accounts named after protocol field names.
     */
    public function test_biodata_push_creates_no_attendance_and_no_placeholder_user(): void
    {
        $device = $this->device();
        $usersBefore = User::withTrashed()->count();

        $body = "PIN=42\tNo=0\tIndex=0\tValid=1\tDuress=0\tType=1\tMajorVer=10\tMinorVer=0\tFormat=0\tTmp=ABCDEF==";

        $response = $this->push($device->serial_number, '&table=BIODATA', $body);

        $response->assertOk();
        $this->assertSame('OK', $response->getContent());

        $this->assertDatabaseCount('biometric_att_logs', 0);
        $this->assertDatabaseCount('attendances', 0);
        $this->assertSame($usersBefore, User::withTrashed()->count(), 'BIODATA must not mint placeholder users');
        $this->assertNull(
            User::withTrashed()->where('employee_id', 'like', 'PIN=%')->first(),
            'a protocol field name must never become an employee_id'
        );
    }

    public function test_attphoto_push_creates_no_attendance_and_no_placeholder_user(): void
    {
        $device = $this->device();
        $usersBefore = User::withTrashed()->count();

        $body = "PIN=42\tSN=0\tsize=1024\tCMD=uploadphoto\r\nBINARYJUNK\x00\x01\x02";

        $response = $this->push($device->serial_number, '&table=ATTPHOTO', $body);

        $response->assertOk();
        $this->assertSame('OK', $response->getContent());

        $this->assertDatabaseCount('biometric_att_logs', 0);
        $this->assertDatabaseCount('attendances', 0);
        $this->assertSame($usersBefore, User::withTrashed()->count(), 'ATTPHOTO must not mint placeholder users');
    }

    public function test_unknown_table_is_accepted_but_not_parsed(): void
    {
        $device = $this->device();

        foreach (['errorlog', 'rtlog', 'SOMETHINGNEW'] as $table) {
            $response = $this->push($device->serial_number, '&table='.$table, "42\t2026-07-15 09:00:00\t0");

            $response->assertOk();
            $this->assertSame('OK', $response->getContent(), "table={$table} must still answer OK");
        }

        $this->assertDatabaseCount('biometric_att_logs', 0);
    }

    /**
     * The happy path, all the way through — not just "a row landed in
     * biometric_att_logs".
     *
     * This is what makes the `attendances` count of 0 in the BIODATA/ATTPHOTO
     * tests above mean anything: a genuine ATTLOG push from an eligible user
     * must still reach the `attendances` table. Assert both halves, or "no
     * attendance was created" proves nothing more than "nothing works".
     */
    public function test_attlog_push_still_ingests_attendance(): void
    {
        // The allowlist must not start rejecting traffic that works today.
        $device = $this->device();
        $user = $this->zonedUser('42', $device);

        $response = $this->push($device->serial_number, '&table=ATTLOG', "42\t2026-07-15 09:00:00\t0");

        $response->assertOk();
        $this->assertSame('OK', $response->getContent());
        $this->assertDatabaseHas('biometric_att_logs', [
            'serial_number' => $device->serial_number,
            'user_pin' => '42',
            'punch_status' => 'processed',
        ]);

        $attendances = Attendance::where('user_id', $user->id)->get();
        $this->assertCount(1, $attendances, 'ATTLOG must still produce a real attendance row');
        $this->assertSame(
            '2026-07-15 09:00:00',
            Carbon::parse($attendances[0]->punchin)->format('Y-m-d H:i:s')
        );
    }

    /**
     * The control for the placeholder-user guard.
     *
     * The BIODATA/ATTPHOTO tests assert the user count does not move. That
     * assertion is only meaningful if the same pipeline demonstrably DOES mint a
     * placeholder when it is supposed to — otherwise a globally broken push
     * handler would pass them. So: identical unknown PIN, ATTLOG table, and the
     * soft-deleted placeholder appears exactly as it always has.
     *
     * This also pins that the fix is scoped to the table dispatch and did not
     * quietly disable auto-creation for real punches.
     */
    public function test_unknown_pin_on_attlog_still_creates_the_placeholder_user(): void
    {
        $device = $this->device();
        $usersBefore = User::withTrashed()->count();

        $this->push($device->serial_number, '&table=ATTLOG', "9911\t2026-07-15 09:00:00\t0")
            ->assertOk();

        $this->assertSame($usersBefore + 1, User::withTrashed()->count());

        $placeholder = User::withTrashed()->where('employee_id', '9911')->first();
        $this->assertNotNull($placeholder);
        $this->assertTrue($placeholder->trashed(), 'placeholder is created soft-deleted/inactive');

        $this->assertDatabaseHas('biometric_att_logs', [
            'user_pin' => '9911',
            'punch_status' => 'unknown_user',
        ]);
    }

    public function test_legacy_untabled_push_still_ingests_attendance(): void
    {
        // Older firmware pushes punches with no `table` at all. That shape was
        // accepted before the allowlist and must still be.
        User::factory()->create(['employee_id' => '77']);
        $device = $this->device();

        $response = $this->push($device->serial_number, '', "77\t2026-07-15 10:30:00\t0");

        $response->assertOk();
        $this->assertSame('OK', $response->getContent());
        $this->assertDatabaseHas('biometric_att_logs', [
            'serial_number' => $device->serial_number,
            'user_pin' => '77',
        ]);
    }

    // ───────────────────────────── Task 2: registry ingestion

    public function test_registry_push_populates_device_capabilities(): void
    {
        $device = $this->device(['model' => null, 'ip_address' => null]);

        $body = "DeviceType=att\r\nFirmVer=Ver 6.60 Apr 21 2017\r\nIPAddress=192.168.1.201\r\n"
            ."MACAddress=00:17:61:01:02:03\r\nPlatform=ZMM220_TFT";

        $response = $this->push($device->serial_number, '&table=options&c=registry', $body);

        $response->assertOk();
        $this->assertSame('OK', $response->getContent());

        $this->assertDatabaseHas('biometric_device_capabilities', [
            'biometric_device_id' => $device->id,
            'capability_key' => 'FirmVer',
            'value' => 'Ver 6.60 Apr 21 2017',
        ]);
        $this->assertDatabaseHas('biometric_device_capabilities', [
            'biometric_device_id' => $device->id,
            'capability_key' => 'MACAddress',
            'value' => '00:17:61:01:02:03',
        ]);
        $this->assertDatabaseHas('biometric_device_capabilities', [
            'biometric_device_id' => $device->id,
            'capability_key' => 'Platform',
            'value' => 'ZMM220_TFT',
        ]);

        // Blank columns on the device record are filled in from the device's
        // own report so an admin no longer types them by hand.
        $device->refresh();
        $this->assertSame('192.168.1.201', $device->ip_address);
        $this->assertSame('att', $device->model);

        // And no attendance row was created from it.
        $this->assertDatabaseCount('biometric_att_logs', 0);
    }

    public function test_registry_push_does_not_overwrite_admin_entered_values(): void
    {
        $device = $this->device(['ip_address' => '10.0.0.5']);

        $this->push(
            $device->serial_number,
            '&table=options&c=registry',
            'DeviceType=att,FirmVer=Ver 6.60,IPAddress=192.168.1.201,MACAddress=00:17:61:01:02:03,Platform=ZMM220_TFT'
        )->assertOk();

        $this->assertSame('10.0.0.5', $device->fresh()->ip_address);
    }

    // ───────────────────────────── Task 3: capability replies

    public function test_get_option_reply_pushed_on_options_table_is_parsed(): void
    {
        $device = $this->device();

        $command = BiometricDeviceCommand::create([
            'biometric_device_id' => $device->id,
            'command_type' => 'GET_OPTION',
            'payload' => ['keys' => ['UserCount', 'MaxUserCount']],
            'status' => BiometricDeviceCommand::STATUS_SENT,
            'sent_at' => now(),
        ]);

        $this->push(
            $device->serial_number,
            '&table=options',
            "GET OPTION FROM: {$device->serial_number}\r\nUserCount=137\r\nMaxUserCount=3000\r\nFWVersion=Ver 6.60"
        )->assertOk();

        $this->assertDatabaseHas('biometric_device_capabilities', [
            'biometric_device_id' => $device->id,
            'capability_key' => 'UserCount',
            'value' => '137',
            'is_unsupported' => false,
        ]);

        $snapshot = app(DeviceCapabilityService::class)->snapshot($device->fresh());
        $this->assertSame(137, $snapshot['capacity']['users']['used']);
        $this->assertSame(3000, $snapshot['capacity']['users']['max']);
        $this->assertNotNull($command->fresh());
    }

    public function test_non_zero_ack_records_the_key_as_unsupported(): void
    {
        $device = $this->device();

        $command = BiometricDeviceCommand::create([
            'biometric_device_id' => $device->id,
            'command_type' => 'GET_OPTION',
            'payload' => ['keys' => ['FaceCount']],
            'status' => BiometricDeviceCommand::STATUS_SENT,
            'sent_at' => now(),
        ]);

        $uri = '/iclock/devicecmd?SN='.rawurlencode($device->serial_number);
        $this->call('POST', $uri, [], [], [], ['CONTENT_TYPE' => 'text/plain'], "ID={$command->id}&Return=-1004&CMD=GET OPTION")
            ->assertOk();

        // -1004 is a capability fact, not a generic failure.
        $this->assertSame(BiometricDeviceCommand::STATUS_UNSUPPORTED, $command->fresh()->status);
        $this->assertDatabaseHas('biometric_device_capabilities', [
            'biometric_device_id' => $device->id,
            'capability_key' => 'FaceCount',
            'is_unsupported' => true,
        ]);

        $snapshot = app(DeviceCapabilityService::class)->snapshot($device->fresh());
        $this->assertContains('FaceCount', $snapshot['unsupported_keys']);
        $this->assertFalse($snapshot['capacity']['faces']['supported']);
    }

    // ───────────────────────────── Task 5: settings + capability endpoints

    public function test_settings_catalogue_is_returned(): void
    {
        $response = $this->actingAs($this->admin())
            ->getJson(route('biometric-devices.settings-catalogue'));

        $response->assertOk()
            ->assertJsonStructure(['catalogue', 'groups', 'dangerous_keys', 'confirmation_field']);

        $this->assertSame(
            array_keys(DeviceCapabilityService::SETTINGS_CATALOGUE),
            array_keys($response->json('catalogue'))
        );
        $this->assertContains('NetworkOn', $response->json('dangerous_keys'));
        $this->assertNotContains('VoiceOn', $response->json('dangerous_keys'));
    }

    public function test_settings_catalogue_requires_permission(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson(route('biometric-devices.settings-catalogue'))
            ->assertForbidden();
    }

    public function test_capabilities_endpoint_returns_the_snapshot(): void
    {
        $device = $this->device();

        $response = $this->actingAs($this->admin())
            ->getJson(route('biometric-devices.capabilities', $device->id));

        $response->assertOk()
            ->assertJsonPath('capabilities.device_id', $device->id)
            ->assertJsonPath('capabilities.has_data', false)
            ->assertJsonPath('capabilities.is_stale', true);
    }

    public function test_probe_queues_info_and_get_option(): void
    {
        $device = $this->device();

        $response = $this->actingAs($this->admin())
            ->postJson(route('biometric-devices.probe', $device->id));

        $response->assertOk()->assertJsonPath('asynchronous', true);
        $this->assertStringContainsString('next poll', $response->json('message'));

        $commands = BiometricDeviceCommand::where('biometric_device_id', $device->id)->get();
        $this->assertCount(2, $commands);
        $this->assertEqualsCanonicalizing(
            ['INFO', 'GET_OPTION'],
            $commands->pluck('command_type')->all()
        );
        $this->assertEqualsCanonicalizing(
            $commands->pluck('id')->all(),
            $response->json('command_ids')
        );

        $getOption = $commands->firstWhere('command_type', 'GET_OPTION');
        foreach (DeviceCapabilityService::CAPABILITY_KEYS as $key) {
            $this->assertStringContainsString($key, $getOption->toAdmsString());
        }
    }

    public function test_settings_update_queues_one_command_per_key(): void
    {
        $device = $this->device();

        $response = $this->actingAs($this->admin())
            ->postJson(route('biometric-devices.settings.update', $device->id), [
                'settings' => ['MThreshold' => 45, 'VoiceOn' => 1],
            ]);

        $response->assertOk();

        $commands = BiometricDeviceCommand::where('biometric_device_id', $device->id)->get();
        $this->assertCount(2, $commands);
        $this->assertSame(['SET_OPTION', 'SET_OPTION'], $commands->pluck('command_type')->all());
        $this->assertEqualsCanonicalizing(
            ['MThreshold', 'VoiceOn'],
            $commands->pluck('payload.key')->all()
        );
        $this->assertStringContainsString(
            'SET OPTION MThreshold=45',
            $commands->firstWhere('payload.key', 'MThreshold')->toAdmsString()
        );
    }

    public function test_dangerous_setting_is_rejected_without_confirmation(): void
    {
        $device = $this->device();

        $response = $this->actingAs($this->admin())
            ->postJson(route('biometric-devices.settings.update', $device->id), [
                'settings' => ['NetworkOn' => 0],
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('requires_confirmation', true)
            ->assertJsonPath('dangerous_keys', ['NetworkOn']);

        $this->assertDatabaseCount('biometric_device_commands', 0);
    }

    public function test_dangerous_setting_is_accepted_with_confirmation(): void
    {
        $device = $this->device();

        $this->actingAs($this->admin())
            ->postJson(route('biometric-devices.settings.update', $device->id), [
                'settings' => ['TCPPort' => 4370],
                'confirm_dangerous' => true,
            ])->assertOk();

        $command = BiometricDeviceCommand::where('biometric_device_id', $device->id)->firstOrFail();
        $this->assertSame('SET_OPTION', $command->command_type);
        $this->assertSame('TCPPort', $command->payload['key']);
        $this->assertStringContainsString('SET OPTION TCPPort=4370', $command->toAdmsString());
    }

    public function test_out_of_catalogue_key_is_rejected(): void
    {
        $device = $this->device();

        $this->actingAs($this->admin())
            ->postJson(route('biometric-devices.settings.update', $device->id), [
                'settings' => ['Shell' => 'rm -rf /', 'MThreshold' => 45],
                'confirm_dangerous' => true,
            ])
            ->assertStatus(422)
            ->assertJsonPath('unknown_keys', ['Shell']);

        // Nothing at all is queued — not even the valid key in the same request.
        $this->assertDatabaseCount('biometric_device_commands', 0);
    }

    // ───────────────────────────── Task 4: ADMS command queue endpoint

    public function test_queue_command_accepts_the_new_capability_verbs(): void
    {
        $device = $this->device();

        foreach (['INFO', 'CHECK', 'QUERY_USERINFO'] as $type) {
            $this->actingAs($this->admin())
                ->postJson(route('api.biometric-devices.commands.queue', $device->id), [
                    'device_id' => $device->id,
                    'command_type' => $type,
                ])->assertOk()->assertJsonPath('command.type', $type);
        }

        $this->assertSame(3, BiometricDeviceCommand::where('biometric_device_id', $device->id)->count());
    }

    public function test_queue_command_rejects_dangerous_set_option_without_confirmation(): void
    {
        $device = $this->device();

        $this->actingAs($this->admin())
            ->postJson(route('api.biometric-devices.commands.queue', $device->id), [
                'device_id' => $device->id,
                'command_type' => 'SET_OPTION',
                'payload' => ['key' => 'DeviceID', 'value' => '9'],
            ])
            ->assertStatus(422)
            ->assertJsonPath('requires_confirmation', true);

        $this->assertDatabaseCount('biometric_device_commands', 0);

        $this->actingAs($this->admin())
            ->postJson(route('api.biometric-devices.commands.queue', $device->id), [
                'device_id' => $device->id,
                'command_type' => 'SET_OPTION',
                'payload' => ['key' => 'DeviceID', 'value' => '9'],
                'confirm_dangerous' => true,
            ])->assertOk();

        $this->assertDatabaseCount('biometric_device_commands', 1);
    }

    public function test_queue_command_rejects_a_key_outside_the_catalogue(): void
    {
        $device = $this->device();

        $this->actingAs($this->admin())
            ->postJson(route('api.biometric-devices.commands.queue', $device->id), [
                'device_id' => $device->id,
                'command_type' => 'SET_OPTION',
                'payload' => ['key' => 'Shell', 'value' => 'reboot'],
                'confirm_dangerous' => true,
            ])->assertStatus(422);

        $this->assertDatabaseCount('biometric_device_commands', 0);
    }

    // ───────────────────────────── Routing: the bulk/{id} collision

    public function test_bulk_routes_do_not_collide_with_id_routes(): void
    {
        $expected = [
            ['POST', 'settings/biometric-devices/bulk/ping', 'biometric-devices.bulk.ping', []],
            ['POST', 'settings/biometric-devices/bulk/delete', 'biometric-devices.bulk.delete', []],
            ['POST', 'settings/biometric-devices/bulk/download-logs', 'biometric-devices.bulk.download-logs', []],
            ['GET', 'settings/biometric-devices/settings-catalogue', 'biometric-devices.settings-catalogue', []],
            ['POST', 'settings/biometric-devices/7/probe', 'biometric-devices.probe', ['id' => '7']],
            ['GET', 'settings/biometric-devices/7/capabilities', 'biometric-devices.capabilities', ['id' => '7']],
            ['POST', 'settings/biometric-devices/7/settings', 'biometric-devices.settings.update', ['id' => '7']],
        ];

        foreach ($expected as [$method, $uri, $name, $parameters]) {
            $route = app('router')->getRoutes()->match(Request::create('/'.$uri, $method));

            $this->assertSame($name, $route->getName(), "{$method} {$uri} resolved to the wrong route");
            $this->assertSame($parameters, $route->parameters(), "{$method} {$uri} bound the wrong parameters");
        }
    }
}
