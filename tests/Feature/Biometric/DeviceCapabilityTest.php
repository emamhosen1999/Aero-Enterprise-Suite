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

    /**
     * The `INFO` reply from a real ZKTeco MB460 — serial AF6P231260266, firmware
     * Ver 8.0.4.6-20230217, push 2.0.33S — captured in production, where the
     * command came back `status=executed, return=0` and stored 74 keys.
     *
     * Synthetic fixtures are what let three defects reach a live device: the
     * `~` prefix on every maximum and on most of the identity fields, `MAC`
     * rather than `MACAddress`, and `~Max…` values that are not record counts.
     * So this is the device's own vocabulary, not ours.
     *
     * ── Provenance, because it is the whole point ────────────────────────────
     * The device sent 74 keys; the incident notes kept an excerpt. Verbatim
     * from that excerpt — every value below is the device's own — are:
     *
     *   ~SerialNumber ~DeviceName ~Platform ~OEMVendor ~MaxUserCount
     *   ~MaxFingerCount ~MaxFaceCount ~MaxAttLogCount ~MaxUserPhotoCount
     *   ~MaxPvCount FWVersion PushVersion MAC IPAddress FPCount FaceCount
     *   FvCount PvCount FingerFunOn FaceFunOn FvFunOn PvFunOn FlashSize
     *   FreeFlashSize
     *
     * The rest are reconstructed from the same unit's behaviour rather than
     * copied from the excerpt, and are kept only because a 24-key INFO would be
     * an unrealistically tidy payload:
     *
     *   UserCount        — the production snapshot rendered `users.used = 13`,
     *                      which only this key can have supplied
     *   TransactionCount — 1009, confirmed by a later six-key GET OPTION probe
     *                      as a value this device "already had from INFO"
     *   ~MaxFvCount      — noted alongside ~MaxUserPhotoCount/~MaxPvCount as the
     *                      2000/10/0 trio in DeviceCapabilityService
     *   PhotoFunOn, ATTPhotoCount, ~ZKFPVersion, ~AlgVer, ~DSTF, FPVersion,
     *   FaceVersion, Language, IsTFT — routine ZK INFO filler
     *
     * Assertions that carry weight are pinned to the verbatim group. The one
     * exception is `PhotoFunOn`, which drives `flags.user_photo`; treat that
     * single assertion as covering the ENGINE_FLAGS mechanism, not as evidence
     * about this model.
     *
     * @var array<string, string>
     */
    private const MB460_INFO = [
        '~SerialNumber' => 'AF6P231260266',
        '~DeviceName' => 'MB460/ID',
        '~Platform' => 'ZMM220_TFT',
        '~OEMVendor' => 'ZKTECO CO.',
        '~MaxUserCount' => '20',
        '~MaxFingerCount' => '20',
        '~MaxFaceCount' => '1500',
        '~MaxAttLogCount' => '10',
        '~MaxUserPhotoCount' => '2000',
        '~MaxFvCount' => '10',
        '~MaxPvCount' => '0',
        '~ZKFPVersion' => '10',
        '~AlgVer' => '10',
        '~DSTF' => '1',
        'FWVersion' => 'Ver 8.0.4.6-20230217',
        'PushVersion' => 'Ver 2.0.33S-20220623',
        'MAC' => '00:17:61:11:65:1b',
        'IPAddress' => '192.168.68.100',
        'UserCount' => '13',
        'TransactionCount' => '1009',
        'FPCount' => '26',
        'FaceCount' => '1',
        'FvCount' => '0',
        'PvCount' => '0',
        'FingerFunOn' => '1',
        'FaceFunOn' => '1',
        'FvFunOn' => '0',
        'PvFunOn' => '0',
        'PhotoFunOn' => '1',
        'ATTPhotoCount' => '0',
        'FlashSize' => '202880',
        'FreeFlashSize' => '136752',
        'FPVersion' => '10',
        'FaceVersion' => '7',
        'Language' => '69',
        'IsTFT' => '1',
    ];

    /**
     * The fixture above on the wire. ZK terminals stream `key=value` pairs
     * separated by tabs/newlines; the column layout in the incident notes is
     * presentation, not protocol.
     */
    private function mb460InfoPayload(): string
    {
        $pairs = [];

        foreach (self::MB460_INFO as $key => $value) {
            $pairs[] = $key.'='.$value;
        }

        return implode("\t", $pairs);
    }

    /**
     * The device record as it exists in production for this unit — the admin
     * typed an IP the device has since drifted away from.
     */
    private function mb460(): BiometricDevice
    {
        return $this->device([
            'name' => 'Head Office Gate',
            'serial_number' => 'AF6P231260266',
            'ip_address' => '192.168.1.132',
        ]);
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
    //  Real hardware — ZKTeco MB460, serial AF6P231260266
    // ──────────────────────────────────────────────────────────────

    public function test_real_mb460_info_payload_populates_identity(): void
    {
        $device = $this->mb460();

        $parsed = $this->service()->parseInfoResponse($device, $this->mb460InfoPayload());

        // Every key the device sent survives the parser, `~` and all.
        $this->assertSame(self::MB460_INFO, $parsed);

        $snapshot = $this->service()->snapshot($device->fresh());

        $this->assertSame([
            // `~DeviceName`, not `DeviceName`.
            'device_name' => 'MB460/ID',
            'device_type' => null,
            // `~Platform`, not `Platform`.
            'platform' => 'ZMM220_TFT',
            'firmware' => 'Ver 8.0.4.6-20230217',
            // `MAC`, not `MACAddress`.
            'mac_address' => '00:17:61:11:65:1b',
            'ip_address' => '192.168.68.100',
            'record_ip_address' => '192.168.1.132',
            'record_model' => null,
        ], $snapshot['identity']);

        $this->assertTrue($snapshot['has_data']);
        $this->assertFalse($snapshot['is_stale']);
        $this->assertSame([], $snapshot['unsupported_keys']);

        // The serial the device reports under `~SerialNumber`, and the fact
        // that it agrees with the record an admin registered the unit under —
        // a mismatch here is how a swapped/RMA'd terminal is spotted.
        $this->assertContains('~SerialNumber', $snapshot['supported_keys']);
        $this->assertSame('AF6P231260266', $snapshot['options']['~SerialNumber']['value']);
        $this->assertSame('AF6P231260266', $snapshot['serial_number']);

        // Firmware and push protocol both survive: the values carry spaces and
        // must not be truncated at the first one.
        $this->assertSame('Ver 8.0.4.6-20230217', $snapshot['options']['FWVersion']['value']);
        $this->assertSame('Ver 2.0.33S-20220623', $snapshot['options']['PushVersion']['value']);
        $this->assertSame('ZKTECO CO.', $snapshot['options']['~OEMVendor']['value']);

        foreach ($snapshot['options'] as $key => $option) {
            $this->assertSame('info', $option['source'], "{$key} came from the wrong source");
            $this->assertFalse($option['unsupported'], "{$key} was wrongly flagged unsupported");
        }
    }

    public function test_capacity_maxima_resolve_despite_the_tilde_prefix(): void
    {
        $device = $this->mb460();
        $this->service()->parseInfoResponse($device, $this->mb460InfoPayload());

        $capacity = $this->service()->snapshot($device->fresh())['capacity'];

        // `~MaxFaceCount = 1500` is the MB460's published face capacity as a
        // literal count, so this meter is the one that can be drawn honestly.
        $this->assertSame([
            'label' => 'Faces',
            'used' => 1,
            'max' => 1500,
            'percent' => 0.1,
            'supported' => true,
            'known' => true,
        ], $capacity['faces']);

        // The prefix no longer hides the answer: the live counts land too.
        $this->assertSame(13, $capacity['users']['used']);
        $this->assertSame(26, $capacity['fingerprints']['used']);
        $this->assertTrue($capacity['users']['known']);
        $this->assertTrue($capacity['fingerprints']['known']);
    }

    public function test_a_maximum_below_the_live_count_never_renders_a_percentage(): void
    {
        $device = $this->mb460();
        $this->service()->parseInfoResponse($device, $this->mb460InfoPayload());

        $snapshot = $this->service()->snapshot($device->fresh());
        $capacity = $snapshot['capacity'];

        // FPCount=26 against ~MaxFingerCount=20 would be "130% full" on a device
        // with thousands of slots free. The denominator is withheld, not merely
        // excluded from `percent`: consumers divide used/max themselves.
        $this->assertSame(26, $capacity['fingerprints']['used']);
        $this->assertNull($capacity['fingerprints']['max']);
        $this->assertNull($capacity['fingerprints']['percent']);
        // The fingerprint engine is present, so this is "headroom unknown",
        // not "no such store".
        $this->assertTrue($capacity['fingerprints']['supported']);

        // `~MaxUserCount = 20` passes used <= max (13 of 20) and would render a
        // plausible-looking 65% on a unit specced in the thousands. Only the
        // per-key unit declaration catches that one.
        $this->assertNull($capacity['users']['max']);
        $this->assertNull($capacity['users']['percent']);

        $this->assertNull($capacity['attendance']['max']);
        $this->assertNull($capacity['attendance']['percent']);

        // Nothing anywhere may render an impossible meter.
        foreach ($capacity as $id => $meter) {
            if ($meter['percent'] !== null) {
                $this->assertGreaterThanOrEqual(0, $meter['percent'], "{$id} percent below zero");
                $this->assertLessThanOrEqual(100, $meter['percent'], "{$id} percent above 100");
            }

            if ($meter['max'] !== null && $meter['used'] !== null) {
                $this->assertLessThanOrEqual(
                    $meter['max'],
                    $meter['used'],
                    "{$id} publishes a maximum below its own live count"
                );
            }
        }

        // The device's own number is still on record for a human to read.
        $this->assertSame('20', $snapshot['options']['~MaxFingerCount']['value']);
    }

    public function test_an_absent_engine_is_distinguishable_from_an_empty_one(): void
    {
        $device = $this->mb460();
        $this->service()->parseInfoResponse($device, $this->mb460InfoPayload());

        $snapshot = $this->service()->snapshot($device->fresh());

        // FvFunOn/PvFunOn = 0: those engines do not exist on this model.
        // FaceFunOn = 1 with FaceCount = 1: that one exists and is in use.
        $this->assertFalse($snapshot['flags']['finger_vein']);
        $this->assertFalse($snapshot['flags']['palm_vein']);
        $this->assertTrue($snapshot['flags']['face']);
        $this->assertTrue($snapshot['flags']['fingerprint']);
        $this->assertTrue($snapshot['flags']['user_photo']);
        $this->assertNull($snapshot['flags']['work_code']);

        // `~MaxPvCount = 0` is a real zero, corroborated by PvCount = 0 and
        // PvFunOn = 0 — a store of nought because there is no palm engine, not
        // a store that has filled up.
        $this->assertSame('0', $snapshot['options']['~MaxPvCount']['value']);
        $this->assertSame('0', $snapshot['options']['PvCount']['value']);
        $this->assertSame('0', $snapshot['options']['PvFunOn']['value']);

        // Feature-absent is carried by the flag, not by pretending the device
        // rejected the key: it answered all three perfectly well.
        foreach (['~MaxPvCount', 'PvCount', 'PvFunOn'] as $key) {
            $this->assertContains($key, $snapshot['supported_keys']);
            $this->assertFalse($snapshot['options'][$key]['unsupported']);
        }

        foreach ($snapshot['capacity'] as $id => $meter) {
            $this->assertNotSame(100.0, $meter['percent'], "{$id} reported as completely full");
        }
    }

    public function test_a_zero_maximum_with_its_engine_off_is_the_palm_vein_case(): void
    {
        // The MB460 has no palm-vein meter of its own, so the exact shape
        // `~MaxPvCount = 0` + `PvFunOn = 0` produces is asserted here on the
        // meter that shares its code path. 0 of 0 is "no such store", never
        // "100% full", and never a division.
        $device = $this->device();
        $this->service()->parseOptionResponse($device, "FaceCount=0\t~MaxFaceCount=0\tFaceFunOn=0");

        $faces = $this->service()->snapshot($device->fresh())['capacity']['faces'];

        $this->assertSame(0, $faces['used']);
        $this->assertSame(0, $faces['max']);
        $this->assertNull($faces['percent']);
        $this->assertFalse($faces['supported']);
    }

    public function test_a_zero_maximum_reads_as_feature_absent_rather_than_full(): void
    {
        // Palm vein has no meter of its own, so the same rule is asserted on a
        // meter that does: a device reporting a face store of zero has no face
        // engine, and 0 of 0 is not "100% full".
        $device = $this->device();
        $this->service()->parseOptionResponse($device, "FaceCount=0\t~MaxFaceCount=0\tFaceFunOn=0");

        $faces = $this->service()->snapshot($device->fresh())['capacity']['faces'];

        $this->assertFalse($faces['supported']);
        $this->assertNull($faces['percent']);
        $this->assertFalse($this->service()->snapshot($device->fresh())['flags']['face']);
    }

    // ──────────────────────────────────────────────────────────────
    //  Silently omitted keys
    // ──────────────────────────────────────────────────────────────

    public function test_a_key_omitted_from_a_successful_reply_is_recorded_as_unavailable(): void
    {
        // Real probe against the MB460: seven keys asked for, Return=0, six
        // answered. MThreshold came back neither as a value nor as an error.
        $device = $this->mb460();

        $command = BiometricDeviceCommand::create([
            'biometric_device_id' => $device->id,
            'command_type' => 'GET_OPTION',
            'payload' => ['keys' => [
                'MThreshold', 'AlarmReRec', 'LockOn', 'VoiceOn', 'WorkCode', '~MaxUserCount', 'FPCount',
            ]],
            'status' => BiometricDeviceCommand::STATUS_SENT,
            'sent_at' => now(),
        ]);
        $command->markAsExecuted('0');
        $this->assertSame('executed', $command->fresh()->status);

        $parsed = $this->service()->parseOptionResponse($device, implode("\t", [
            'AlarmReRec=30', 'LockOn=10', 'VoiceOn=1', 'WorkCode=0', '~MaxUserCount=20', 'FPCount=26',
        ]));

        $this->assertSame([
            'AlarmReRec' => '30',
            'LockOn' => '10',
            'VoiceOn' => '1',
            'WorkCode' => '0',
            '~MaxUserCount' => '20',
            'FPCount' => '26',
        ], $parsed);

        $this->assertDatabaseHas('biometric_device_capabilities', [
            'biometric_device_id' => $device->id,
            'capability_key' => 'MThreshold',
            'value' => null,
            'is_unsupported' => true,
            'source' => DeviceCapabilityService::SOURCE_OMITTED,
        ]);

        $snapshot = $this->service()->snapshot($device->fresh());

        // Unavailable, and legibly so: 'omitted' is not the same fact as -1004,
        // and the UI must not print "the device answered -1004" over it.
        $this->assertContains('MThreshold', $snapshot['unsupported_keys']);
        $this->assertNotContains('MThreshold', $snapshot['supported_keys']);
        $this->assertTrue($snapshot['options']['MThreshold']['unsupported']);
        $this->assertSame('omitted', $snapshot['options']['MThreshold']['source']);
        $this->assertNull($snapshot['options']['MThreshold']['value']);

        // …and specifically not "never probed", which is what it looked like
        // before: no row at all.
        $this->assertArrayNotHasKey('MSpeed', $snapshot['options']);

        // The six that did answer are untouched and carry their values.
        foreach (['AlarmReRec' => '30', 'LockOn' => '10', 'VoiceOn' => '1', 'WorkCode' => '0', 'FPCount' => '26'] as $key => $value) {
            $this->assertSame($value, $snapshot['options'][$key]['value'], "{$key} lost its value");
            $this->assertFalse($snapshot['options'][$key]['unsupported']);
            $this->assertContains($key, $snapshot['supported_keys']);
        }

        $this->assertSame('20', $snapshot['options']['~MaxUserCount']['value']);
        $this->assertFalse($snapshot['flags']['work_code']);
    }

    public function test_omission_never_erases_a_value_the_device_already_gave_us(): void
    {
        $device = $this->mb460();
        $service = $this->service();

        // INFO volunteered the face maximum…
        $service->parseInfoResponse($device, $this->mb460InfoPayload());

        BiometricDeviceCommand::create([
            'biometric_device_id' => $device->id,
            'command_type' => 'GET_OPTION',
            'payload' => ['keys' => ['~MaxFaceCount', 'FPCount']],
            'status' => BiometricDeviceCommand::STATUS_SENT,
            'sent_at' => now(),
        ]);

        // …and a later GET OPTION left it out. Reconciliation fills in
        // unknowns; it does not overwrite an answer we already have.
        $service->parseOptionResponse($device, 'FPCount=26');

        $snapshot = $service->snapshot($device->fresh());
        $this->assertSame('1500', $snapshot['options']['~MaxFaceCount']['value']);
        $this->assertFalse($snapshot['options']['~MaxFaceCount']['unsupported']);
        $this->assertSame(1500, $snapshot['capacity']['faces']['max']);
    }

    public function test_a_reply_matching_none_of_the_requested_keys_condemns_nothing(): void
    {
        $device = $this->mb460();

        BiometricDeviceCommand::create([
            'biometric_device_id' => $device->id,
            'command_type' => 'GET_OPTION',
            'payload' => ['keys' => ['MThreshold', 'VThreshold']],
            'status' => BiometricDeviceCommand::STATUS_SENT,
            'sent_at' => now(),
        ]);

        // An unsolicited push that overlaps the outstanding probe not at all is
        // most likely not its reply, so it may not condemn its keys.
        $this->service()->parseOptionResponse($device, 'UserCount=13');

        $snapshot = $this->service()->snapshot($device->fresh());
        $this->assertSame([], $snapshot['unsupported_keys']);
        $this->assertArrayNotHasKey('MThreshold', $snapshot['options']);
    }

    public function test_an_omitted_key_recovers_when_a_later_probe_answers_it(): void
    {
        $device = $this->mb460();
        $service = $this->service();

        BiometricDeviceCommand::create([
            'biometric_device_id' => $device->id,
            'command_type' => 'GET_OPTION',
            'payload' => ['keys' => ['MThreshold', 'VoiceOn']],
            'status' => BiometricDeviceCommand::STATUS_SENT,
            'sent_at' => now(),
        ]);

        $service->parseOptionResponse($device, 'VoiceOn=1');
        $this->assertContains('MThreshold', $service->snapshot($device->fresh())['unsupported_keys']);

        $service->parseOptionResponse($device, "MThreshold=45\tVoiceOn=1");

        $snapshot = $service->snapshot($device->fresh());
        $this->assertSame('45', $snapshot['options']['MThreshold']['value']);
        $this->assertFalse($snapshot['options']['MThreshold']['unsupported']);
        $this->assertSame([], $snapshot['unsupported_keys']);
    }

    public function test_an_explicit_minus_1004_is_distinguishable_from_a_silent_omission(): void
    {
        // Both end up unavailable, but they are different sentences on screen:
        // "not supported on this model (the device answered -1004)" versus "the
        // device ignored this key when probed". Only `source` separates them, so
        // a later omission must not be allowed to relabel a -1004.
        $device = $this->mb460();
        $service = $this->service();

        $rejected = BiometricDeviceCommand::create([
            'biometric_device_id' => $device->id,
            'command_type' => 'GET_OPTION',
            'payload' => ['keys' => ['VThreshold']],
            'status' => BiometricDeviceCommand::STATUS_SENT,
            'sent_at' => now()->subMinute(),
        ]);
        $rejected->markAsExecuted('-1004');
        $service->markUnsupported($rejected, '-1004');

        // A second probe asks for the already-rejected key alongside a fresh one
        // and comes back Return=0 carrying neither.
        BiometricDeviceCommand::create([
            'biometric_device_id' => $device->id,
            'command_type' => 'GET_OPTION',
            'payload' => ['keys' => ['MThreshold', 'VThreshold', 'VoiceOn']],
            'status' => BiometricDeviceCommand::STATUS_SENT,
            'sent_at' => now(),
        ]);

        $service->parseOptionResponse($device, 'VoiceOn=1');

        $snapshot = $service->snapshot($device->fresh());

        // Unavailable, both of them.
        $this->assertContains('MThreshold', $snapshot['unsupported_keys']);
        $this->assertContains('VThreshold', $snapshot['unsupported_keys']);
        $this->assertTrue($snapshot['options']['MThreshold']['unsupported']);
        $this->assertTrue($snapshot['options']['VThreshold']['unsupported']);

        // …but told apart. The -1004 keeps the source that recorded the code;
        // the silent omission is the only one labelled 'omitted'.
        $this->assertSame(
            DeviceCapabilityService::SOURCE_OMITTED,
            $snapshot['options']['MThreshold']['source'],
            'a silently omitted key must be recorded as omitted'
        );
        $this->assertSame(
            DeviceCapabilityService::SOURCE_GET_OPTION,
            $snapshot['options']['VThreshold']['source'],
            'a later omission must not relabel a key the device explicitly answered -1004'
        );
        $this->assertNotSame(
            $snapshot['options']['MThreshold']['source'],
            $snapshot['options']['VThreshold']['source']
        );

        // The command row carries the same distinction for command history.
        $this->assertSame('unsupported', $rejected->fresh()->status);
        $this->assertSame('-1004', $rejected->fresh()->return_code);

        // And the key that did answer is untouched.
        $this->assertSame('1', $snapshot['options']['VoiceOn']['value']);
        $this->assertContains('VoiceOn', $snapshot['supported_keys']);
    }

    // ──────────────────────────────────────────────────────────────
    //  Real hardware — the six-key attendance probe
    // ──────────────────────────────────────────────────────────────

    /**
     * The second live GET_OPTION probe against the same MB460, run to fill the
     * attendance meter the first snapshot reported as `known: false`.
     *
     * Six keys asked for, `return=0`, five answered. `AttLogCount` came back
     * neither as a value nor as an error — the same silent omission as
     * `MThreshold`, and the second independent instance of it, which is what
     * makes SOURCE_OMITTED a model of the hardware rather than a one-off.
     *
     * @return array{0: BiometricDeviceCommand, 1: string}
     */
    private function mb460AttendanceProbe(BiometricDevice $device): array
    {
        $command = BiometricDeviceCommand::create([
            'biometric_device_id' => $device->id,
            'command_type' => 'GET_OPTION',
            'payload' => ['keys' => [
                'AttLogCount', '~MaxAttLogCount', 'UserCount',
                'TransactionCount', 'LockCount', 'FaceCount',
            ]],
            'status' => BiometricDeviceCommand::STATUS_SENT,
            'sent_at' => now(),
        ]);
        $command->markAsExecuted('0');

        // Five of six, verbatim. AttLogCount is absent, not empty.
        $reply = implode("\t", [
            '~MaxAttLogCount=10',
            'LockCount=1',
            'TransactionCount=1009',
            'UserCount=13',
            'FaceCount=1',
        ]);

        return [$command, $reply];
    }

    public function test_attendance_count_falls_back_to_transaction_count_when_the_model_omits_attlogcount(): void
    {
        $device = $this->mb460();
        $service = $this->service();

        [$command, $reply] = $this->mb460AttendanceProbe($device);
        $parsed = $service->parseOptionResponse($device, $reply, $command);

        $this->assertSame([
            '~MaxAttLogCount' => '10',
            'LockCount' => '1',
            'TransactionCount' => '1009',
            'UserCount' => '13',
            'FaceCount' => '1',
        ], $parsed);

        $snapshot = $service->snapshot($device->fresh());

        // The omitted key is recorded as unavailable, exactly as MThreshold was.
        $this->assertContains('AttLogCount', $snapshot['unsupported_keys']);
        $this->assertTrue($snapshot['options']['AttLogCount']['unsupported']);
        $this->assertSame(
            DeviceCapabilityService::SOURCE_OMITTED,
            $snapshot['options']['AttLogCount']['source']
        );
        $this->assertNull($snapshot['options']['AttLogCount']['value']);

        // …and the meter populates anyway, from the key this model does answer.
        // Before the fallback existed it read `used: null, known: false` for
        // ever, however many times an admin pressed Probe.
        $attendance = $snapshot['capacity']['attendance'];
        $this->assertSame(1009, $attendance['used']);
        $this->assertTrue($attendance['known']);

        // The fallback must not be mistaken for a rejection: AttLogCount is
        // flagged unsupported, but TransactionCount answered, so the store is
        // plainly there and the meter stays supported.
        $this->assertTrue($attendance['supported']);

        // The other four land normally.
        $this->assertSame(1, $snapshot['counters']['locks']);
        $this->assertSame(1009, $snapshot['counters']['transactions']);
        $this->assertSame(13, $snapshot['capacity']['users']['used']);
        $this->assertSame(1, $snapshot['capacity']['faces']['used']);
    }

    public function test_a_documented_attlogcount_still_wins_over_the_fallback(): void
    {
        // The fallback is an order, not a replacement: a model that answers the
        // documented spelling must not have its own number overridden by a
        // transaction counter that may well count something else.
        $device = $this->device();
        $this->service()->parseOptionResponse(
            $device,
            "AttLogCount=50000\tTransactionCount=7\tMaxAttLogCount=100000"
        );

        $snapshot = $this->service()->snapshot($device->fresh());

        $this->assertSame(50000, $snapshot['capacity']['attendance']['used']);
        $this->assertSame(100000, $snapshot['capacity']['attendance']['max']);
        $this->assertSame(50.0, $snapshot['capacity']['attendance']['percent']);
        $this->assertSame(7, $snapshot['counters']['transactions']);
    }

    public function test_max_attlog_of_ten_against_a_thousand_records_withholds_the_denominator(): void
    {
        // `~MaxAttLogCount = 10` against 1009 live records is the second
        // real-world instance of the units problem, and a starker one than the
        // fingerprint case: dividing raw would render 10,090% full.
        //
        // Two independent gates would each catch it. MAX_KEY_UNITS is the one
        // that actually fires — `~maxattlogcount` is declared UNKNOWN, so the
        // value never becomes a denominator at all. The used-greater-than-max
        // guard in meter() is the backstop that would still catch it if anyone
        // ever promoted that key to RAW. The assertion is on the outcome, so it
        // holds whichever gate is doing the work.
        $device = $this->mb460();
        $service = $this->service();

        [$command, $reply] = $this->mb460AttendanceProbe($device);
        $service->parseOptionResponse($device, $reply, $command);

        $attendance = $service->snapshot($device->fresh())['capacity']['attendance'];

        $this->assertSame(1009, $attendance['used']);
        $this->assertNull($attendance['max'], 'a maximum of 10 must never be a denominator for 1009 records');
        $this->assertNull($attendance['percent']);

        // The device's own answer is still on record for a human to read, and
        // it is not flagged as a rejection — the device answered it happily.
        $this->assertSame('10', $service->snapshot($device->fresh())['options']['~MaxAttLogCount']['value']);
        $this->assertContains('~MaxAttLogCount', $service->snapshot($device->fresh())['supported_keys']);
    }

    public function test_the_full_mb460_picture_after_info_then_the_attendance_probe(): void
    {
        // Both real payloads in the order they happened. Nothing the INFO reply
        // established may be lost by the later, narrower probe.
        $device = $this->mb460();
        $service = $this->service();

        $service->parseInfoResponse($device, $this->mb460InfoPayload());
        [$command, $reply] = $this->mb460AttendanceProbe($device);
        $service->parseOptionResponse($device, $reply, $command);

        $snapshot = $service->snapshot($device->fresh());

        // Identity, established by INFO, is untouched by the option probe.
        $this->assertSame('MB460/ID', $snapshot['identity']['device_name']);
        $this->assertSame('00:17:61:11:65:1b', $snapshot['identity']['mac_address']);

        // Every meter is either honest or silent — never absurd.
        $this->assertSame(1009, $snapshot['capacity']['attendance']['used']);
        $this->assertNull($snapshot['capacity']['attendance']['percent']);
        $this->assertSame(1500, $snapshot['capacity']['faces']['max']);
        $this->assertSame(0.1, $snapshot['capacity']['faces']['percent']);
        $this->assertNull($snapshot['capacity']['fingerprints']['max']);
        $this->assertNull($snapshot['capacity']['users']['max']);

        foreach ($snapshot['capacity'] as $id => $meter) {
            if ($meter['percent'] !== null) {
                $this->assertGreaterThanOrEqual(0, $meter['percent'], "{$id} percent below zero");
                $this->assertLessThanOrEqual(100, $meter['percent'], "{$id} percent above 100");
            }

            if ($meter['max'] !== null && $meter['used'] !== null) {
                $this->assertLessThanOrEqual($meter['max'], $meter['used'], "{$id} max below its own count");
            }
        }

        // The only unavailable key across both real payloads is the one the
        // device silently dropped.
        $this->assertSame(['AttLogCount'], $snapshot['unsupported_keys']);
    }

    // ──────────────────────────────────────────────────────────────
    //  Padding around `=`
    // ──────────────────────────────────────────────────────────────

    /**
     * Five real values from the MB460, chosen because each one breaks a
     * different naive parser:
     *
     *   FWVersion / PushVersion — a value with a space in it, so a value cannot
     *                             be terminated at the next space
     *   ~OEMVendor              — a space AND a trailing `.`, which is in the
     *                             key charset and so is a candidate boundary
     *   MAC                     — colons, which are not
     *   ~Platform               — the `~` prefix, on the last pair of the line
     *
     * @var array<string, string>
     */
    private const PADDING_CASE = [
        'FWVersion' => 'Ver 8.0.4.6-20230217',
        'PushVersion' => 'Ver 2.0.33S-20220623',
        '~OEMVendor' => 'ZKTECO CO.',
        'MAC' => '00:17:61:11:65:1b',
        '~Platform' => 'ZMM220_TFT',
    ];

    /**
     * The same five pairs on the wire four ways: with and without padding around
     * `=`, and with the pairs separated by tabs (each its own segment) or by
     * plain spaces (all of them on one line, which is the case where the value
     * boundary has to do real work).
     *
     * @return array<string, string>
     */
    private function paddingPayloads(): array
    {
        $render = function (string $separator, string $pad): string {
            $pairs = [];

            foreach (self::PADDING_CASE as $key => $value) {
                $pairs[] = $key.$pad.'='.$pad.$value;
            }

            return implode($separator, $pairs);
        };

        return [
            'unpadded, tab-separated' => $render("\t", ''),
            'padded, tab-separated' => $render("\t", ' '),
            'unpadded, one line' => $render(' ', ''),
            'padded, one line' => $render(' ', ' '),
        ];
    }

    public function test_pairs_parse_identically_with_and_without_padding_around_the_equals(): void
    {
        foreach ($this->paddingPayloads() as $label => $payload) {
            $device = $this->device();

            $parsed = $this->service()->parseOptionResponse($device, $payload);

            // Not "most of them", not "the ones without spaces" — the same map
            // every time. A firmware that pads is a formatting difference, and a
            // formatting difference may not cost us the reply.
            $this->assertSame(
                self::PADDING_CASE,
                $parsed,
                "the [{$label}] form did not parse to the same pairs"
            );
        }
    }

    public function test_padded_pairs_are_stored_and_never_dumped_into_the_raw_info_park(): void
    {
        // The failure this guards is not "a value came back slightly wrong". The
        // old parser required `=` to touch the key, so a padded reply matched
        // ZERO pairs, `parseInfoResponse` took the positional-CSV branch, and the
        // entire payload went into `~RawInfo` with no key ever recorded — total
        // and silent, on a screen whose whole job is to say what the device is.
        $device = $this->mb460();

        $parsed = $this->service()->parseInfoResponse(
            $device,
            $this->paddingPayloads()['padded, one line']
        );

        $this->assertSame(self::PADDING_CASE, $parsed);

        $this->assertDatabaseMissing('biometric_device_capabilities', [
            'biometric_device_id' => $device->id,
            'capability_key' => '~RawInfo',
        ]);

        $snapshot = $this->service()->snapshot($device->fresh());

        foreach (self::PADDING_CASE as $key => $value) {
            $this->assertSame($value, $snapshot['options'][$key]['value'], "{$key} lost its value");
            $this->assertFalse($snapshot['options'][$key]['unsupported']);
        }

        // …and the padded spellings still resolve through the `~`-tolerant index
        // into the identity block the UI actually renders.
        $this->assertSame('Ver 8.0.4.6-20230217', $snapshot['identity']['firmware']);
        $this->assertSame('ZMM220_TFT', $snapshot['identity']['platform']);
        $this->assertSame('00:17:61:11:65:1b', $snapshot['identity']['mac_address']);
    }

    public function test_padding_tolerance_does_not_swallow_a_following_key(): void
    {
        // The value boundary is padded in the same way as the separator, so the
        // two agree. If only the separator had been relaxed, a padded line
        // carrying several pairs would run them together into one enormous
        // value — a subtler corruption than losing the reply, and harder to see.
        $device = $this->device();

        $parsed = $this->service()->parseOptionResponse(
            $device,
            'UserCount = 13 FPCount = 26 DeviceName = Head Office Gate'
        );

        $this->assertSame([
            'UserCount' => '13',
            'FPCount' => '26',
            'DeviceName' => 'Head Office Gate',
        ], $parsed);

        // A trailing space-bearing value is not truncated at its first space.
        $this->assertSame('Head Office Gate', $parsed['DeviceName']);
    }

    // ──────────────────────────────────────────────────────────────
    //  Which command the device actually refused
    // ──────────────────────────────────────────────────────────────

    public function test_a_rejected_write_is_recorded_against_set_option_not_get_option(): void
    {
        // The settings form writes with SET OPTION and the probe reads with GET
        // OPTION. "Which of the two did the device refuse?" is the first
        // question asked when a setting will not stick, and the row used to
        // answer it wrongly: a -1004 on a write was filed as a rejected read.
        $device = $this->device();
        $service = $this->service();

        $write = $this->command($device, 'SET_OPTION', ['key' => 'MThreshold', 'value' => 45]);
        $write->markAsExecuted('-1004');
        $service->markUnsupported($write, '-1004');

        $this->assertDatabaseHas('biometric_device_capabilities', [
            'biometric_device_id' => $device->id,
            'capability_key' => 'MThreshold',
            'is_unsupported' => true,
            'value' => null,
            'source' => DeviceCapabilityService::SOURCE_SET_OPTION,
        ]);

        $read = $this->command($device, 'GET_OPTION', ['keys' => ['FaceCount']]);
        $read->markAsExecuted('-1004');
        $service->markUnsupported($read, '-1004');

        $verb = $this->command($device, 'INFO');
        $verb->markAsExecuted('-1004');
        $service->markUnsupported($verb, '-1004');

        $snapshot = $service->snapshot($device->fresh());

        $this->assertSame(DeviceCapabilityService::SOURCE_SET_OPTION, $snapshot['options']['MThreshold']['source']);
        $this->assertSame(DeviceCapabilityService::SOURCE_GET_OPTION, $snapshot['options']['FaceCount']['source']);
        $this->assertSame(DeviceCapabilityService::SOURCE_COMMAND, $snapshot['options']['CMD:INFO']['source']);

        // Three different sentences, three different sources.
        $this->assertSame(3, count(array_unique([
            $snapshot['options']['MThreshold']['source'],
            $snapshot['options']['FaceCount']['source'],
            $snapshot['options']['CMD:INFO']['source'],
        ])));
    }

    public function test_every_rejection_source_still_reads_as_a_minus_1004_to_the_ui(): void
    {
        // BiometricPanel.jsx readKeyState() branches on exactly one value:
        //
        //     if (entry.unsupported) return entry.source === 'omitted'
        //         ? 'omitted' : 'unsupported';
        //
        // so any source it does not recognise degrades to the -1004 wording.
        // That is the true sentence for a rejected read, a rejected write and a
        // rejected verb alike — all three are the device answering a rejection
        // code out loud — and it is why a fourth and fifth source value could be
        // added without touching the component. This test pins that: a key the
        // device REFUSED must never be labelled `omitted`, because `omitted`
        // means the opposite (the device claimed success and said nothing), and
        // it is the one label that changes what the admin is told.
        $device = $this->device();
        $service = $this->service();

        $write = $this->command($device, 'SET_OPTION', ['key' => 'VoiceOn', 'value' => 1]);
        $write->markAsExecuted('-1004');
        $service->markUnsupported($write, '-1004');

        $read = $this->command($device, 'GET_OPTION', ['keys' => ['FvCount']]);
        $read->markAsExecuted('-1004');
        $service->markUnsupported($read, '-1004');

        $verb = $this->command($device, 'CHECK');
        $verb->markAsExecuted('-1004');
        $service->markUnsupported($verb, '-1004');

        $snapshot = $service->snapshot($device->fresh());

        foreach (['VoiceOn', 'FvCount', 'CMD:CHECK'] as $key) {
            $this->assertTrue(
                $snapshot['options'][$key]['unsupported'],
                "{$key} must be flagged unavailable so the UI stops offering it"
            );
            $this->assertNotSame(
                DeviceCapabilityService::SOURCE_OMITTED,
                $snapshot['options'][$key]['source'],
                "{$key} was refused out loud and must not be labelled a silent omission"
            );
            $this->assertContains($key, $snapshot['unsupported_keys']);
        }
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

    // ──────────────────────────────────────────────────────────────
    //  Attendance state (IN/OUT) — the deliberate gap
    // ──────────────────────────────────────────────────────────────

    /**
     * The candidate option keys for the device's attendance-state behaviour.
     *
     * All single-source, from one reverse-engineered TCP/UDP SDK parameter table
     * extracted from a single device of a different model, and all describing
     * state *display* or an undocumented schedule encoding rather than the state
     * a punch is recorded with. Matrix §4b, "Attendance state — not
     * establishable from here", has the evidence against each.
     *
     * They are named here, in the test, precisely because they are NOT in the
     * production constants. Deleting an assertion below is the deliberate act
     * that promoting one of them requires.
     *
     * @var array<int, string>
     */
    private const ATTENDANCE_STATE_CANDIDATES = [
        '~ShowState',
        'AlwaysShowState',
        'AS1',
        'AS2',
    ];

    public function test_no_unestablished_attendance_state_key_is_offered_as_a_setting(): void
    {
        // The MB460 loses 6.1% of user-days because it stamps the day's first
        // punch check-OUT (matrix §4b). The durable fix would be a device option
        // — but no such key is established for ZMM220_TFT / 8.0.4.6-20230217,
        // and a catalogue row is an affordance: an admin clicks it and believes
        // the terminal changed. We shipped that once already, when CLEAR_PHOTO
        // and CLEAR_BIODATA were catalogued as destructive while emitting
        // UNKNOWN. Here it would be worse than inert — a wrong attendance-state
        // value does not error, it silently mis-states every punch on the unit.
        $catalogue = DeviceCapabilityService::SETTINGS_CATALOGUE;

        foreach (self::ATTENDANCE_STATE_CANDIDATES as $key) {
            $this->assertArrayNotHasKey(
                $key,
                $catalogue,
                "{$key} is single-source and unconfirmed on this hardware; it must not be offered as a settable row until a real device answers it Return=0. See matrix §4b."
            );
        }
    }

    public function test_no_unestablished_attendance_state_key_is_added_to_the_working_probe(): void
    {
        // CAPABILITY_KEYS is the default GET OPTION probe, and its docblock
        // carries a standing warning: some firmware rejects a WHOLE GET OPTION
        // when any single key is unknown, rather than answering -1004 per key.
        // That probe works against the live MB460 and the capability screen
        // depends on it. The attendance-state diagnostic is therefore a separate,
        // isolated command (matrix §4b) so a wholesale rejection costs only the
        // diagnostic.
        foreach (self::ATTENDANCE_STATE_CANDIDATES as $key) {
            $this->assertNotContains(
                $key,
                DeviceCapabilityService::CAPABILITY_KEYS,
                "{$key} must not be spent on the default probe; run it as its own GET OPTION instead. See matrix §4b."
            );
        }
    }

    public function test_every_catalogued_setting_emits_a_real_set_option_string(): void
    {
        // The CLEAR_PHOTO/CLEAR_BIODATA class of defect, pinned generically: a
        // key may not appear in the catalogue unless the command layer can
        // actually put it on the wire. `UNKNOWN` is what toAdmsString() emits
        // for anything it has no case for, and it is the exact string that
        // reached a device last time.
        $device = $this->device();

        foreach (DeviceCapabilityService::SETTINGS_CATALOGUE as $key => $meta) {
            $command = $this->command($device, 'SET_OPTION', ['key' => $key, 'value' => 1]);
            $emitted = $command->toAdmsString();

            $this->assertSame(
                "C:{$command->id}:SET OPTION {$key}=1",
                $emitted,
                "{$key} is catalogued but does not emit a usable SET OPTION string"
            );
            $this->assertStringNotContainsString('UNKNOWN', $emitted);
        }
    }

    public function test_an_omitted_attendance_state_key_degrades_to_unavailable_and_never_to_settable(): void
    {
        // The likeliest outcome of the §4b diagnostic, modelled on this device's
        // established behaviour: it answers `Return=0` and silently leaves the
        // key out, exactly as it already does for MThreshold, AttLogCount,
        // TimeZone, DateTime and GMTOffset. That is informative, not a failure —
        // it is how we learn attendance state is not remotely settable here.
        $device = $this->mb460();
        $service = $this->service();

        $command = BiometricDeviceCommand::create([
            'biometric_device_id' => $device->id,
            'command_type' => 'GET_OPTION',
            'payload' => ['keys' => ['~ShowState', 'AlwaysShowState', 'TOState', 'AS1', 'AS2']],
            'status' => BiometricDeviceCommand::STATUS_SENT,
            'sent_at' => now(),
        ]);

        // The command string an operator would actually run against the MB460.
        $this->assertSame(
            "C:{$command->id}:GET OPTION FROM ~ShowState,AlwaysShowState,TOState,AS1,AS2",
            $command->toAdmsString()
        );

        $command->markAsExecuted('0');
        $this->assertSame('executed', $command->fresh()->status);

        // Return=0, one key of five answered. The four state keys are absent —
        // not empty, absent.
        $parsed = $service->parseOptionResponse($device, 'TOState=10', $command);
        $this->assertSame(['TOState' => '10'], $parsed);

        $snapshot = $service->snapshot($device->fresh());

        foreach (['~ShowState', 'AlwaysShowState', 'AS1', 'AS2'] as $key) {
            $this->assertContains($key, $snapshot['unsupported_keys'], "{$key} must read as unavailable");
            $this->assertNotContains($key, $snapshot['supported_keys']);
            $this->assertTrue($snapshot['options'][$key]['unsupported']);
            $this->assertNull($snapshot['options'][$key]['value']);

            // "The device ignored this key", not "the device answered -1004".
            // Printing the wrong one of those tells an admin something untrue.
            $this->assertSame(
                DeviceCapabilityService::SOURCE_OMITTED,
                $snapshot['options'][$key]['source'],
                "{$key} was silently omitted and must be recorded as such"
            );

            // And unavailability may never leak back into the settings form.
            $this->assertArrayNotHasKey($key, DeviceCapabilityService::SETTINGS_CATALOGUE);
        }

        // The one key that did answer is untouched, and is the only one of the
        // five that is catalogued — so the form offers exactly what the device
        // demonstrated it will talk about.
        $this->assertSame('10', $snapshot['options']['TOState']['value']);
        $this->assertFalse($snapshot['options']['TOState']['unsupported']);
        $this->assertContains('TOState', $snapshot['supported_keys']);
        $this->assertArrayHasKey('TOState', DeviceCapabilityService::SETTINGS_CATALOGUE);
    }

    public function test_the_state_diagnostic_is_isolated_from_the_default_probe(): void
    {
        // A firmware that rejects a whole GET OPTION over one unknown key must
        // cost us the diagnostic and nothing else. Pinned as a behaviour: the
        // -1004 condemns only the keys the diagnostic asked for, and the default
        // probe's keys are untouched and still readable afterwards.
        $device = $this->mb460();
        $service = $this->service();

        $diagnostic = $this->command($device, 'GET_OPTION', [
            'keys' => ['~ShowState', 'AlwaysShowState', 'AS1', 'AS2'],
        ]);
        $diagnostic->markAsExecuted('-1004');
        $service->markUnsupported($diagnostic, '-1004');

        // The capability probe still works, on the same device, right after.
        $service->parseInfoResponse($device, $this->mb460InfoPayload());

        $snapshot = $service->snapshot($device->fresh());

        $this->assertSame(
            ['AS1', 'AS2', 'AlwaysShowState', '~ShowState'],
            $snapshot['unsupported_keys'],
            'a rejected state diagnostic may condemn only its own keys'
        );

        // Identity and capacity — the capability screen — are unharmed.
        $this->assertSame('MB460/ID', $snapshot['identity']['device_name']);
        $this->assertSame('ZMM220_TFT', $snapshot['identity']['platform']);
        $this->assertSame(13, $snapshot['capacity']['users']['used']);
        $this->assertSame(1500, $snapshot['capacity']['faces']['max']);

        // …and the rejection is legible as a refusal, not as a silent omission.
        foreach (['~ShowState', 'AlwaysShowState', 'AS1', 'AS2'] as $key) {
            $this->assertNotSame(
                DeviceCapabilityService::SOURCE_OMITTED,
                $snapshot['options'][$key]['source'],
                "{$key} was refused out loud and must not be labelled a silent omission"
            );
        }
    }
}
