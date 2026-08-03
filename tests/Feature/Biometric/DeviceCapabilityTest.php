<?php

namespace Tests\Feature\Biometric;

use App\Models\HRM\BiometricDevice;
use App\Models\HRM\BiometricDeviceCommand;
use App\Services\Biometric\DeviceCapabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Covers the ZKTeco ADMS capability layer:
 *
 *   - the extended server->device command vocabulary (INFO, CHECK, GET OPTION,
 *     SET OPTION, DATA QUERY USERINFO) emits the documented wire strings,
 *   - acknowledgement return codes are decoded instead of collapsed, so -1004
 *     ("not supported on this model") is distinguishable from a real failure,
 *   - device replies are parsed liberally and persisted per (device, key),
 *   - registration data fills blank device columns without clobbering an admin,
 *   - snapshot() returns the shape the UI is built against.
 */
class DeviceCapabilityTest extends TestCase
{
    use RefreshDatabase;

    private function device(array $overrides = []): BiometricDevice
    {
        return BiometricDevice::create(array_merge([
            'name' => 'Gate MB460',
            'serial_number' => 'SN-'.uniqid(),
            'protocol' => 'adms',
            'is_active' => true,
        ], $overrides));
    }

    private function command(BiometricDevice $device, string $type, ?array $payload = null): BiometricDeviceCommand
    {
        return BiometricDeviceCommand::create([
            'biometric_device_id' => $device->id,
            'command_type' => $type,
            'payload' => $payload,
            'status' => 'pending',
        ]);
    }

    private function service(): DeviceCapabilityService
    {
        return app(DeviceCapabilityService::class);
    }

    // ──────────────────────────────────────────────────────────────
    //  Task 1 — command vocabulary
    // ──────────────────────────────────────────────────────────────

    public function test_info_and_check_emit_bare_verbs(): void
    {
        $device = $this->device();

        $info = $this->command($device, 'INFO');
        $check = $this->command($device, 'CHECK');

        $this->assertSame("C:{$info->id}:INFO", $info->toAdmsString());
        $this->assertSame("C:{$check->id}:CHECK", $check->toAdmsString());
    }

    public function test_get_option_emits_comma_separated_keys_from_payload(): void
    {
        $device = $this->device();

        $command = $this->command($device, 'GET_OPTION', [
            'keys' => ['UserCount', 'MaxUserCount', 'FaceCount'],
        ]);

        $this->assertSame(
            "C:{$command->id}:GET OPTION FROM UserCount,MaxUserCount,FaceCount",
            $command->toAdmsString()
        );
    }

    public function test_get_option_accepts_a_comma_string_and_falls_back_to_the_full_probe_set(): void
    {
        $device = $this->device();

        $fromString = $this->command($device, 'GET_OPTION', ['keys' => 'FWVersion, Platform ,,']);
        $this->assertSame(
            "C:{$fromString->id}:GET OPTION FROM FWVersion,Platform",
            $fromString->toAdmsString()
        );

        $empty = $this->command($device, 'GET_OPTION', []);
        $this->assertSame(
            "C:{$empty->id}:GET OPTION FROM ".implode(',', DeviceCapabilityService::CAPABILITY_KEYS),
            $empty->toAdmsString()
        );
    }

    public function test_set_option_emits_one_key_per_command(): void
    {
        $device = $this->device();

        $command = $this->command($device, 'SET_OPTION', ['key' => 'MThreshold', 'value' => 45]);

        $this->assertSame("C:{$command->id}:SET OPTION MThreshold=45", $command->toAdmsString());
    }

    public function test_query_userinfo_emits_the_documented_string_with_optional_pin(): void
    {
        $device = $this->device();

        $all = $this->command($device, 'QUERY_USERINFO');
        $one = $this->command($device, 'QUERY_USERINFO', ['pin' => '42']);

        $this->assertSame("C:{$all->id}:DATA QUERY USERINFO", $all->toAdmsString());
        $this->assertSame("C:{$one->id}:DATA QUERY USERINFO PIN=42", $one->toAdmsString());
    }

    public function test_legacy_get_userinfo_type_now_emits_data_query_userinfo(): void
    {
        // The old `GET USERINFO` string appears in no reference implementation.
        // The enum value must keep working for rows already in the table.
        $device = $this->device();
        $command = $this->command($device, 'GET_USERINFO');

        $this->assertSame("C:{$command->id}:DATA QUERY USERINFO", $command->toAdmsString());
        $this->assertStringNotContainsString('GET USERINFO', $command->toAdmsString());
    }

    public function test_existing_command_strings_are_unchanged(): void
    {
        $device = $this->device();

        $reboot = $this->command($device, 'REBOOT');
        $clear = $this->command($device, 'CLEAR_LOG');
        $delete = $this->command($device, 'DELETE_USER', ['pin' => '7']);

        $this->assertSame("C:{$reboot->id}:REBOOT", $reboot->toAdmsString());
        $this->assertSame("C:{$clear->id}:CLEAR LOG", $clear->toAdmsString());
        $this->assertSame("C:{$delete->id}:DATA DELETE USERINFO PIN=7", $delete->toAdmsString());
    }

    // ──────────────────────────────────────────────────────────────
    //  Task 2 — return codes
    // ──────────────────────────────────────────────────────────────

    public function test_return_codes_are_decoded(): void
    {
        $success = BiometricDeviceCommand::decodeReturnCode('0');
        $this->assertTrue($success['ok']);
        $this->assertFalse($success['unsupported']);
        $this->assertSame('Success', $success['label']);

        $notSupported = BiometricDeviceCommand::decodeReturnCode('-1004');
        $this->assertFalse($notSupported['ok']);
        $this->assertTrue($notSupported['unsupported']);
        $this->assertSame('Not supported on this model', $notSupported['label']);

        $noData = BiometricDeviceCommand::decodeReturnCode('-1');
        $this->assertTrue($noData['unsupported']);

        $syntax = BiometricDeviceCommand::decodeReturnCode('-1002');
        $this->assertFalse($syntax['ok']);
        $this->assertFalse($syntax['unsupported']);
        $this->assertSame('Syntax error', $syntax['label']);

        $file = BiometricDeviceCommand::decodeReturnCode('-2');
        $this->assertFalse($file['ok']);
        $this->assertFalse($file['unsupported']);

        $unknown = BiometricDeviceCommand::decodeReturnCode('4242');
        $this->assertFalse($unknown['ok']);
        $this->assertFalse($unknown['unsupported']);
        $this->assertFalse($unknown['known']);
    }

    public function test_unsupported_ack_is_distinguishable_from_a_genuine_failure(): void
    {
        $device = $this->device();

        $ok = $this->command($device, 'INFO');
        $ok->markAsExecuted('0');
        $this->assertSame('executed', $ok->fresh()->status);

        $unsupported = $this->command($device, 'GET_OPTION', ['keys' => ['FaceCount']]);
        $unsupported->markAsExecuted('-1004');
        $unsupported = $unsupported->fresh();
        $this->assertSame('unsupported', $unsupported->status);
        $this->assertNotSame('failed', $unsupported->status);
        $this->assertTrue($unsupported->isUnsupported());
        $this->assertSame('Not supported on this model', $unsupported->error_message);
        $this->assertSame('-1004', $unsupported->return_code);

        $failed = $this->command($device, 'SET_OPTION', ['key' => 'MThreshold', 'value' => 'x']);
        $failed->markAsExecuted('-1002');
        $this->assertSame('failed', $failed->fresh()->status);
    }

    // ──────────────────────────────────────────────────────────────
    //  Task 3/4 — parsing and storage
    // ──────────────────────────────────────────────────────────────

    public function test_option_response_is_parsed_and_persisted(): void
    {
        $device = $this->device();

        $parsed = $this->service()->parseOptionResponse(
            $device,
            "UserCount=12\tMaxUserCount=3000\nFWVersion=Ver 6.60 Apr 22 2016"
        );

        $this->assertSame('12', $parsed['UserCount']);
        $this->assertSame('3000', $parsed['MaxUserCount']);
        // A value containing spaces must survive intact.
        $this->assertSame('Ver 6.60 Apr 22 2016', $parsed['FWVersion']);

        $this->assertDatabaseHas('biometric_device_capabilities', [
            'biometric_device_id' => $device->id,
            'capability_key' => 'UserCount',
            'value' => '12',
            'source' => 'get_option',
        ]);

        $this->assertNotNull($device->fresh()->getAttribute('capabilities_probed_at'));
    }

    public function test_info_response_is_parsed_and_persisted(): void
    {
        $device = $this->device();

        $parsed = $this->service()->parseInfoResponse($device, "INFO\nAttLogCount=900,MaxAttLogCount=100000");

        $this->assertSame(['AttLogCount' => '900', 'MaxAttLogCount' => '100000'], $parsed);
        $this->assertDatabaseHas('biometric_device_capabilities', [
            'biometric_device_id' => $device->id,
            'capability_key' => 'MaxAttLogCount',
            'source' => 'info',
        ]);
    }

    public function test_malformed_input_never_throws_and_records_nothing_it_cannot_understand(): void
    {
        $device = $this->device();
        $service = $this->service();

        $this->assertSame([], $service->parseOptionResponse($device, ''));
        $this->assertSame([], $service->parseOptionResponse($device, '~~~ garbage ~~~'));
        $this->assertSame([], $service->parseOptionResponse($device, "\t\n\n,,,&&"));
        $this->assertSame([], $service->parseInfoResponse($device, '19,3000,0,900'));

        // Nothing from GET OPTION was understood, so nothing was stored for it.
        $this->assertDatabaseMissing('biometric_device_capabilities', [
            'biometric_device_id' => $device->id,
            'source' => 'get_option',
        ]);

        // The positional INFO payload is parked verbatim rather than guessed at.
        $this->assertDatabaseHas('biometric_device_capabilities', [
            'biometric_device_id' => $device->id,
            'capability_key' => '~RawInfo',
            'value' => '19,3000,0,900',
        ]);

        // A key with no value is still a key.
        $this->assertSame(['FaceCount' => ''], $service->parseOptionResponse($device, 'FaceCount='));
    }

    public function test_reprobing_updates_rather_than_duplicating(): void
    {
        $device = $this->device();
        $service = $this->service();

        $service->parseOptionResponse($device, 'UserCount=12');
        $service->parseOptionResponse($device, 'UserCount=41');

        $rows = DB::table('biometric_device_capabilities')
            ->where('biometric_device_id', $device->id)
            ->where('capability_key', 'UserCount')
            ->get();

        $this->assertCount(1, $rows);
        $this->assertSame('41', $rows->first()->value);
    }

    public function test_mark_unsupported_flags_the_keys_from_an_option_command(): void
    {
        $device = $this->device();
        $service = $this->service();

        $command = $this->command($device, 'GET_OPTION', ['keys' => ['FaceCount', 'MaxFaceCount']]);
        $command->markAsExecuted('-1004');
        $service->markUnsupported($command, '-1004');

        $this->assertDatabaseHas('biometric_device_capabilities', [
            'biometric_device_id' => $device->id,
            'capability_key' => 'FaceCount',
            'is_unsupported' => true,
        ]);
        $this->assertDatabaseHas('biometric_device_capabilities', [
            'biometric_device_id' => $device->id,
            'capability_key' => 'MaxFaceCount',
            'is_unsupported' => true,
        ]);
    }

    public function test_mark_unsupported_falls_back_to_the_command_verb_and_ignores_real_failures(): void
    {
        $device = $this->device();
        $service = $this->service();

        $info = $this->command($device, 'INFO');
        $service->markUnsupported($info, '-1004');
        $this->assertDatabaseHas('biometric_device_capabilities', [
            'biometric_device_id' => $device->id,
            'capability_key' => 'CMD:INFO',
            'is_unsupported' => true,
        ]);

        // -1002 is a syntax error, not a capability answer: record nothing.
        $reboot = $this->command($device, 'REBOOT');
        $service->markUnsupported($reboot, '-1002');
        $this->assertDatabaseMissing('biometric_device_capabilities', [
            'biometric_device_id' => $device->id,
            'capability_key' => 'CMD:REBOOT',
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    //  recordRegistry
    // ──────────────────────────────────────────────────────────────

    public function test_record_registry_fills_blank_device_columns(): void
    {
        $device = $this->device(['model' => null, 'ip_address' => null]);

        $this->service()->recordRegistry($device, [
            'DeviceType' => 'MB460',
            'FirmVer' => 'Ver 6.60',
            'IPAddress' => '192.168.1.50',
            'MACAddress' => '00:17:61:12:34:56',
            'Platform' => 'ZMM220_TFT',
        ]);

        $device->refresh();
        $this->assertSame('MB460', $device->model);
        $this->assertSame('192.168.1.50', $device->ip_address);

        foreach (['DeviceType', 'FirmVer', 'IPAddress', 'MACAddress', 'Platform'] as $key) {
            $this->assertDatabaseHas('biometric_device_capabilities', [
                'biometric_device_id' => $device->id,
                'capability_key' => $key,
                'source' => 'registry',
            ]);
        }
    }

    public function test_record_registry_does_not_clobber_admin_set_values(): void
    {
        $device = $this->device([
            'model' => 'Admin typed this',
            'ip_address' => '10.0.0.9',
        ]);

        $this->service()->recordRegistry($device, [
            'DeviceType' => 'MB460',
            'IPAddress' => '192.168.1.50',
            'MACAddress' => '',          // empty
            'Platform' => 'unknown',     // meaningless
        ]);

        $device->refresh();
        $this->assertSame('Admin typed this', $device->model);
        $this->assertSame('10.0.0.9', $device->ip_address);

        // The device's own answer is still recorded so the UI can show the drift.
        $this->assertDatabaseHas('biometric_device_capabilities', [
            'biometric_device_id' => $device->id,
            'capability_key' => 'IPAddress',
            'value' => '192.168.1.50',
        ]);

        // Meaningless values are never promoted into identity fields.
        $snapshot = $this->service()->snapshot($device->fresh());
        $this->assertNull($snapshot['identity']['platform']);
        $this->assertNull($snapshot['identity']['mac_address']);
    }

    public function test_record_registry_is_case_insensitive_and_survives_an_empty_payload(): void
    {
        $device = $this->device(['model' => null]);

        $this->service()->recordRegistry($device, ['devicetype' => 'K40', 'Junk' => 'x']);
        $this->assertSame('K40', $device->fresh()->model);

        $this->service()->recordRegistry($device, ['nothing' => 'useful']);
        $this->assertDatabaseMissing('biometric_device_capabilities', [
            'biometric_device_id' => $device->id,
            'capability_key' => 'nothing',
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    //  snapshot
    // ──────────────────────────────────────────────────────────────

    public function test_snapshot_returns_the_documented_shape(): void
    {
        $device = $this->device(['ip_address' => '10.0.0.9']);
        $service = $this->service();

        $service->parseOptionResponse($device, implode("\t", [
            'DeviceName=Main Gate',
            'FWVersion=Ver 6.60',
            'Platform=ZMM220_TFT',
            'MACAddress=00:17:61:12:34:56',
            'IPAddress=192.168.1.50',
            'UserCount=150',
            'MaxUserCount=3000',
            'FPCount=300',
            'MaxFingerCount=6000',
            'AttLogCount=50000',
            'MaxAttLogCount=100000',
            'TransactionCount=7',
            'LockCount=1',
            'WorkCode=1',
        ]));

        $faceProbe = $this->command($device, 'GET_OPTION', ['keys' => ['FaceCount', 'MaxFaceCount']]);
        $service->markUnsupported($faceProbe, '-1004');

        $snapshot = $service->snapshot($device->fresh());

        $this->assertSame([
            'device_id', 'name', 'serial_number', 'identity', 'capacity', 'counters',
            'flags', 'supported_keys', 'unsupported_keys', 'options', 'probed_at',
            'is_stale', 'has_data',
        ], array_keys($snapshot));

        $this->assertSame($device->id, $snapshot['device_id']);
        $this->assertTrue($snapshot['has_data']);
        $this->assertFalse($snapshot['is_stale']);
        $this->assertNotNull($snapshot['probed_at']);

        $this->assertSame([
            'device_name' => 'Main Gate',
            'device_type' => null,
            'platform' => 'ZMM220_TFT',
            'firmware' => 'Ver 6.60',
            'mac_address' => '00:17:61:12:34:56',
            'ip_address' => '192.168.1.50',
            'record_ip_address' => '10.0.0.9',
            'record_model' => null,
        ], $snapshot['identity']);

        $this->assertSame(
            ['users', 'fingerprints', 'faces', 'attendance'],
            array_keys($snapshot['capacity'])
        );

        $this->assertSame([
            'label' => 'Users',
            'used' => 150,
            'max' => 3000,
            'percent' => 5.0,
            'supported' => true,
            'known' => true,
        ], $snapshot['capacity']['users']);

        $this->assertSame(50.0, $snapshot['capacity']['attendance']['percent']);

        // -1004 on the face keys is how the UI learns to hide face features.
        $this->assertFalse($snapshot['capacity']['faces']['supported']);
        $this->assertNull($snapshot['capacity']['faces']['percent']);
        $this->assertContains('FaceCount', $snapshot['unsupported_keys']);
        $this->assertContains('MaxFaceCount', $snapshot['unsupported_keys']);
        $this->assertContains('UserCount', $snapshot['supported_keys']);
        $this->assertNotContains('FaceCount', $snapshot['supported_keys']);

        $this->assertSame(['transactions' => 7, 'locks' => 1], $snapshot['counters']);
        $this->assertSame(['work_code' => true], $snapshot['flags']);

        $this->assertSame([
            'value' => '150',
            'unsupported' => false,
            'source' => 'get_option',
            'probed_at' => $snapshot['options']['UserCount']['probed_at'],
        ], $snapshot['options']['UserCount']);
        $this->assertNotNull($snapshot['options']['UserCount']['probed_at']);
    }

    public function test_snapshot_of_a_never_probed_device_is_an_explicit_empty_state(): void
    {
        $snapshot = $this->service()->snapshot($this->device());

        $this->assertFalse($snapshot['has_data']);
        $this->assertTrue($snapshot['is_stale']);
        $this->assertNull($snapshot['probed_at']);
        $this->assertSame([], $snapshot['supported_keys']);
        $this->assertSame([], $snapshot['unsupported_keys']);

        foreach ($snapshot['capacity'] as $meter) {
            $this->assertNull($meter['used']);
            $this->assertNull($meter['max']);
            $this->assertNull($meter['percent']);
            // Never asked is not the same as cannot.
            $this->assertTrue($meter['supported']);
            $this->assertFalse($meter['known']);
        }
    }

    public function test_snapshot_reports_a_stale_probe(): void
    {
        $device = $this->device();
        $this->service()->parseOptionResponse($device, 'UserCount=1');

        $device->setAttribute('capabilities_probed_at', now()->subDays(3));
        $device->save();

        $this->assertTrue($this->service()->snapshot($device->fresh())['is_stale']);
    }

    // ──────────────────────────────────────────────────────────────
    //  SETTINGS_CATALOGUE
    // ──────────────────────────────────────────────────────────────

    public function test_settings_catalogue_flags_the_strand_the_device_keys(): void
    {
        $catalogue = DeviceCapabilityService::SETTINGS_CATALOGUE;

        foreach (['NetworkOn', 'TCPPort', 'UDPPort', 'DeviceID', 'AutoPowerOff'] as $key) {
            $this->assertArrayHasKey($key, $catalogue);
            $this->assertTrue($catalogue[$key]['dangerous'], "{$key} must be flagged dangerous");
        }

        // The two most operationally useful settings must be present and safe.
        foreach (['MThreshold', 'AlarmReRec'] as $key) {
            $this->assertArrayHasKey($key, $catalogue);
            $this->assertFalse($catalogue[$key]['dangerous']);
        }

        foreach ($catalogue as $key => $meta) {
            $this->assertSame(
                ['group', 'label', 'type', 'dangerous', 'help'],
                array_keys($meta),
                "{$key} has the wrong catalogue shape"
            );
            $this->assertContains($meta['type'], ['bool', 'int', 'string', 'time']);
        }
    }
}
