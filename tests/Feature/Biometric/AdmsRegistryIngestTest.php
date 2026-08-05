<?php

namespace Tests\Feature\Biometric;

use App\Models\HRM\BiometricDevice;
use App\Services\Biometric\DeviceCapabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * `table=options&c=registry` ingest, plus the two tables next to it that must
 * stay skipped (matrix §1).
 *
 * The registration push is how a device tells us what it actually is —
 * DeviceType, FirmVer, IPAddress, MACAddress, Platform — and it was previously
 * discarded, which is why administrators still typed firmware and MAC in by
 * hand. Three properties are load-bearing and each has a test below:
 *
 *  1. **It fills blanks.** A column nobody has filled in gets the device's own
 *     answer, so the record stops being a form to complete.
 *  2. **It never overwrites.** A populated column is left exactly as it is. The
 *     live MB460 (SN AF6P231260266) is the reason: it reports 192.168.68.100
 *     against a stored 192.168.1.132, and DHCP churn silently rewriting a
 *     curated record is a worse failure than a stale field an admin can see.
 *     Drift resolves by being *shown* — the device's answer always lands in
 *     biometric_device_capabilities, so the snapshot carries both sides.
 *  3. **It answers `OK`.** Per the header comment in routes/iclock.php a ZKTeco
 *     unit that receives a body it does not understand retries the same payload
 *     forever. "We could not parse this" must never become "we reject this",
 *     which is why the malformed-body case below asserts 200/`OK` and not a 4xx.
 *
 * BIODATA and ATTPHOTO are here because the registry work shares its dispatch
 * with them: before the allowlist, every unrecognised table fell through to the
 * attendance parser. Those two tests are regression guards on that fallthrough
 * staying closed.
 */
class AdmsRegistryIngestTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The live unit this feature was built against.
     */
    private const LIVE_SERIAL = 'AF6P231260266';

    private const RECORD_IP = '192.168.1.132';

    private const DEVICE_REPORTED_IP = '192.168.68.100';

    // ── fixtures ────────────────────────────────────────────────────

    private function device(array $overrides = []): BiometricDevice
    {
        // auth_token is unique on biometric_devices.
        return BiometricDevice::create(array_merge([
            'name' => 'Gate MB460',
            'serial_number' => 'SN-'.uniqid(),
            'protocol' => 'adms',
            'auth_token' => 'token-'.uniqid(),
            'is_active' => true,
        ], $overrides));
    }

    private function push(string $serial, string $query, string $body)
    {
        $uri = '/iclock/cdata?SN='.rawurlencode($serial).$query;

        return $this->call('POST', $uri, [], [], [], ['CONTENT_TYPE' => 'text/plain'], $body);
    }

    private function registryPush(BiometricDevice $device, string $body)
    {
        return $this->push($device->serial_number, '&table=options&c=registry', $body);
    }

    /**
     * A registration body in the comma-separated form the protocol documents.
     */
    private function registryBody(array $overrides = []): string
    {
        $fields = array_merge([
            'DeviceType' => 'MB460',
            'FirmVer' => 'Ver 8.0.4.6-20230217',
            'IPAddress' => self::DEVICE_REPORTED_IP,
            'MACAddress' => '00:17:61:01:88:27',
            'Platform' => 'ZMM220_TFT',
        ], $overrides);

        $pairs = [];

        foreach ($fields as $key => $value) {
            $pairs[] = $key.'='.$value;
        }

        return implode(',', $pairs);
    }

    /**
     * @return array<string, string|null> capability_key => value
     */
    private function capabilities(BiometricDevice $device): array
    {
        return DB::table('biometric_device_capabilities')
            ->where('biometric_device_id', $device->id)
            ->pluck('value', 'capability_key')
            ->all();
    }

    private function assertNoPunchesRecorded(string $context): void
    {
        $this->assertSame(0, DB::table('biometric_att_logs')->count(), $context.': staged no att-log rows');
        $this->assertSame(0, DB::table('attendances')->count(), $context.': created no attendance rows');
    }

    // ── 1. the registration payload is stored ───────────────────────

    /**
     * The headline case: every documented registration field lands in the
     * capability table under its canonical spelling.
     */
    public function test_a_registry_push_stores_every_registration_field(): void
    {
        $device = $this->device();

        $response = $this->registryPush($device, $this->registryBody());

        $response->assertOk();
        $this->assertSame('OK', $response->getContent());

        $capabilities = $this->capabilities($device);

        foreach (DeviceCapabilityService::REGISTRY_KEYS as $key) {
            $this->assertArrayHasKey($key, $capabilities, "registration field {$key} was not stored");
        }

        $this->assertSame('MB460', $capabilities['DeviceType']);
        $this->assertSame('Ver 8.0.4.6-20230217', $capabilities['FirmVer']);
        $this->assertSame(self::DEVICE_REPORTED_IP, $capabilities['IPAddress']);
        $this->assertSame('00:17:61:01:88:27', $capabilities['MACAddress']);
        $this->assertSame('ZMM220_TFT', $capabilities['Platform']);

        // Provenance matters: these rows came from a push, not from a probe the
        // server issued, and the UI prints that distinction.
        $this->assertSame(
            DeviceCapabilityService::SOURCE_REGISTRY,
            DB::table('biometric_device_capabilities')
                ->where('biometric_device_id', $device->id)
                ->where('capability_key', 'IPAddress')
                ->value('source')
        );

        // A registration is not attendance.
        $this->assertNoPunchesRecorded('registry push');
    }

    /**
     * Blank columns on the device record are filled from the device's own
     * answer — the whole point of the feature, per matrix §5.
     */
    public function test_a_registry_push_fills_blank_device_columns(): void
    {
        $device = $this->device(['ip_address' => null, 'model' => null]);

        $this->assertNull($device->ip_address);
        $this->assertNull($device->model);

        $this->registryPush($device, $this->registryBody())->assertOk();

        $device->refresh();

        $this->assertSame(self::DEVICE_REPORTED_IP, $device->ip_address);
        $this->assertSame('MB460', $device->model);
        $this->assertNotNull($device->capabilities_probed_at, 'a registration is a capability observation');
    }

    /**
     * A device reporting placeholder junk must not fill a blank column with it.
     * `0.0.0.0` is a device saying "I do not know my address", and writing it
     * into the record would turn an honest blank into a wrong value.
     */
    public function test_a_registry_push_does_not_fill_a_blank_column_with_a_placeholder(): void
    {
        $device = $this->device(['ip_address' => null]);

        $this->registryPush($device, $this->registryBody(['IPAddress' => '0.0.0.0']))->assertOk();

        $this->assertNull($device->refresh()->ip_address);
    }

    // ── 2. a populated column is never overwritten ──────────────────

    /**
     * The real production case, asserted end to end.
     *
     * SN AF6P231260266 reports 192.168.68.100; the record says 192.168.1.132.
     * The record wins — a device may fill a blank, never overwrite an answer —
     * and the drift is surfaced instead of applied: the device's own value is
     * still stored in the capability table, and the snapshot exposes both sides
     * so an administrator can decide.
     */
    public function test_a_registry_push_never_overwrites_a_populated_ip_address(): void
    {
        $device = $this->device([
            'serial_number' => self::LIVE_SERIAL,
            'ip_address' => self::RECORD_IP,
            'model' => 'MB460-CURATED',
        ]);

        $response = $this->registryPush($device, $this->registryBody());

        $response->assertOk();
        $this->assertSame('OK', $response->getContent());

        $device->refresh();

        $this->assertSame(
            self::RECORD_IP,
            $device->ip_address,
            'a device-reported address must never clobber the stored record'
        );
        $this->assertSame(
            'MB460-CURATED',
            $device->model,
            'the same rule applies to every mapped column, not just the IP'
        );

        // The device's answer is not discarded — it is kept where the UI can
        // show the disagreement.
        $this->assertSame(self::DEVICE_REPORTED_IP, $this->capabilities($device)['IPAddress']);

        $snapshot = app(DeviceCapabilityService::class)->snapshot($device);

        $this->assertSame(self::DEVICE_REPORTED_IP, $snapshot['identity']['ip_address']);
        $this->assertSame(self::RECORD_IP, $snapshot['identity']['record_ip_address']);
        $this->assertNotSame(
            $snapshot['identity']['ip_address'],
            $snapshot['identity']['record_ip_address'],
            'the drift must remain visible rather than being resolved by a silent write'
        );
    }

    /**
     * Repeating the push does not eventually wear the record down, and does not
     * accumulate history rows: the capability table is unique on (device, key).
     */
    public function test_repeated_registry_pushes_are_idempotent(): void
    {
        $device = $this->device(['ip_address' => self::RECORD_IP]);

        $this->registryPush($device, $this->registryBody())->assertOk();
        $this->registryPush($device, $this->registryBody())->assertOk();
        $this->registryPush($device, $this->registryBody())->assertOk();

        $this->assertSame(self::RECORD_IP, $device->refresh()->ip_address);
        $this->assertSame(
            count(DeviceCapabilityService::REGISTRY_KEYS),
            DB::table('biometric_device_capabilities')->where('biometric_device_id', $device->id)->count()
        );
    }

    // ── 3. the shapes real firmware actually sends ──────────────────

    /**
     * Newline-separated pairs with the SDK's `~` prefix (matrix §4b). Both
     * variations are real and firmware mixes them freely, so the parser has to
     * resolve `~IPAddress` to the documented `IPAddress` spelling.
     */
    public function test_a_newline_separated_registry_push_with_tilde_prefixed_keys_is_understood(): void
    {
        $device = $this->device(['ip_address' => null, 'model' => null]);

        $body = "~DeviceType=MB460\r\n"
            ."FirmVer=Ver 8.0.4.6-20230217\r\n"
            .'~IPAddress='.self::DEVICE_REPORTED_IP."\r\n"
            ."~Platform=ZMM220_TFT\r\n";

        $this->registryPush($device, $body)->assertOk();

        $capabilities = $this->capabilities($device);

        $this->assertSame('MB460', $capabilities['DeviceType'] ?? null);
        $this->assertSame(self::DEVICE_REPORTED_IP, $capabilities['IPAddress'] ?? null);
        $this->assertSame('ZMM220_TFT', $capabilities['Platform'] ?? null);

        $device->refresh();
        $this->assertSame(self::DEVICE_REPORTED_IP, $device->ip_address);
        $this->assertSame('MB460', $device->model);
    }

    /**
     * Padding around the `=`.
     *
     * DeviceCapabilityService::parsePairs() tolerates `Key = Value` because the
     * ZK option namespace is answered by many firmware families; the controller's
     * mirror of that parser did not, so a padded registration parsed to zero
     * pairs and was silently dropped while still answering `OK` — the worst kind
     * of failure, because nothing looks wrong.
     */
    public function test_a_registry_push_with_padding_around_the_equals_is_understood(): void
    {
        $device = $this->device(['ip_address' => null, 'model' => null]);

        $body = 'DeviceType = MB460, FirmVer = Ver 8.0.4.6-20230217, IPAddress = '.self::DEVICE_REPORTED_IP;

        $this->registryPush($device, $body)->assertOk();

        $capabilities = $this->capabilities($device);

        $this->assertSame('MB460', $capabilities['DeviceType'] ?? null);
        $this->assertSame('Ver 8.0.4.6-20230217', $capabilities['FirmVer'] ?? null, 'a value with spaces must survive whole');
        $this->assertSame(self::DEVICE_REPORTED_IP, $capabilities['IPAddress'] ?? null);

        $this->assertSame(self::DEVICE_REPORTED_IP, $device->refresh()->ip_address);
    }

    /**
     * Firmware that omits `c=registry` is identified by content instead, so the
     * registration is not misfiled as a GET OPTION reply.
     */
    public function test_a_registration_without_the_c_registry_marker_is_still_recognised(): void
    {
        $device = $this->device(['ip_address' => null, 'model' => null]);

        $response = $this->push($device->serial_number, '&table=options', $this->registryBody());

        $response->assertOk();
        $this->assertSame('OK', $response->getContent());

        $this->assertSame(
            DeviceCapabilityService::SOURCE_REGISTRY,
            DB::table('biometric_device_capabilities')
                ->where('biometric_device_id', $device->id)
                ->where('capability_key', 'IPAddress')
                ->value('source')
        );

        $this->assertSame(self::DEVICE_REPORTED_IP, $device->refresh()->ip_address);
    }

    // ── 4. malformed input degrades, never throws ───────────────────

    /**
     * A garbled registration must cost us this round's data and nothing else.
     *
     * A 500 on /iclock/cdata is not a quiet failure: the device is actively
     * polling this endpoint and will keep re-sending the same unparseable body.
     *
     * @dataProvider malformedBodies
     */
    public function test_a_malformed_registry_body_does_not_error(string $label, string $body): void
    {
        $device = $this->device(['ip_address' => self::RECORD_IP]);

        $response = $this->registryPush($device, $body);

        $response->assertOk();
        $this->assertSame('OK', $response->getContent(), "{$label}: the device must not be told to retry");

        // Nothing was invented from an unparseable body, and the record stands.
        $this->assertSame(self::RECORD_IP, $device->refresh()->ip_address);
        $this->assertNoPunchesRecorded("malformed registry ({$label})");
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function malformedBodies(): array
    {
        return [
            'empty body' => ['empty body', ''],
            'whitespace only' => ['whitespace only', "  \r\n\t  "],
            'no key=value pairs' => ['no key=value pairs', 'this is not a registration payload at all'],
            'separators only' => ['separators only', ',,,&&&'],
            'values with no keys' => ['values with no keys', '=1,=2,='],
            'truncated mid-pair' => ['truncated mid-pair', 'DeviceType=MB460,IPAddre'],
            'binary noise' => ['binary noise', "\x00\x01\x02\xff\xfe DeviceType\x00"],
            'absurd key length' => ['absurd key length', str_repeat('K', 5000).'=1'],
            'html error page' => ['html error page', '<html><body>proxy error</body></html>'],
        ];
    }

    /**
     * A body that parses but carries nothing we recognise leaves the record
     * untouched rather than writing an empty capability row over it.
     */
    public function test_a_registry_body_with_no_recognised_fields_changes_nothing(): void
    {
        $device = $this->device(['ip_address' => self::RECORD_IP, 'model' => 'MB460']);

        $response = $this->registryPush($device, 'SomethingElse=1,Unrelated=2');

        $response->assertOk();
        $this->assertSame('OK', $response->getContent());

        $device->refresh();
        $this->assertSame(self::RECORD_IP, $device->ip_address);
        $this->assertSame('MB460', $device->model);
        $this->assertSame([], $this->capabilities($device));
    }

    // ── 5. BIODATA / ATTPHOTO stay skipped (matrix §1) ──────────────

    /**
     * BIODATA is deliberately not stored: the `Type` → modality mapping is
     * model-dependent and unverified on our hardware (our only ADMS unit reports
     * `FvFunOn=0` / `PvFunOn=0` and has never sent one), and a mislabelled
     * template is not inert — TemplateRoamingService would replay a row marked
     * `fingerprint` at real hardware. Skipping loses data we cannot use; guessing
     * corrupts data we can.
     *
     * What must never regress is the *manner* of the skip: accepted, answered
     * `OK`, and never fed to the attendance parser.
     */
    public function test_a_biodata_push_is_skipped_without_creating_attendance(): void
    {
        $device = $this->device();

        $body = "PIN=4301\tNo=0\tIndex=0\tValid=1\tDuress=0\tType=9\tMajorVer=53\tMinorVer=0\tFormat=0\tTmp=SGVsbG8gd29ybGQ=\r\n"
            ."PIN=4302\tNo=0\tIndex=0\tValid=1\tDuress=0\tType=2\tMajorVer=53\tMinorVer=0\tFormat=0\tTmp=QW5vdGhlcg==";

        $response = $this->push($device->serial_number, '&table=BIODATA', $body);

        $response->assertOk();
        $this->assertSame('OK', $response->getContent(), 'a skipped table must not make the device retry forever');

        $this->assertNoPunchesRecorded('BIODATA push');
        $this->assertSame(0, DB::table('biometric_templates')->count(), 'an undecodable template must not be stored');
        $this->assertSame(0, DB::table('biometric_oper_logs')->count());
    }

    /**
     * ATTPHOTO has no storage and is no longer even invited (matrix §3 removed
     * `ATTPHOTOStamp` from the handshake), but a device already mid-sync can
     * still send one. It must be absorbed, not parsed as punches.
     */
    public function test_an_attphoto_push_is_skipped_without_creating_attendance(): void
    {
        $device = $this->device();

        $body = "PIN=4301\tSN=".$device->serial_number."\tsize=2048\tCMD=uploadphoto\r\n"
            .base64_encode(random_bytes(64));

        $response = $this->push($device->serial_number, '&table=ATTPHOTO', $body);

        $response->assertOk();
        $this->assertSame('OK', $response->getContent());

        $this->assertNoPunchesRecorded('ATTPHOTO push');
        $this->assertSame(0, DB::table('biometric_oper_logs')->count());
    }

    /**
     * The guard rail underneath both: a table we have never heard of is skipped
     * too. This is the fallthrough that used to send everything to the
     * attendance parser, and it is what makes the two tests above hold for
     * tables nobody has written a case for yet.
     */
    public function test_an_unknown_table_is_skipped_rather_than_parsed_as_attendance(): void
    {
        $device = $this->device();

        $response = $this->push($device->serial_number, '&table=SOMETHINGNEW', "4301\t2026-07-15 09:00:00\t0\t1");

        $response->assertOk();
        $this->assertSame('OK', $response->getContent());

        $this->assertNoPunchesRecorded('unknown table push');
    }

    /**
     * And the counterpart that must NOT change: a real ATTLOG push still stages
     * a punch. Every skip above is only safe while this stays true — a dispatch
     * that swallowed attendance would pass all of them.
     */
    public function test_an_attlog_push_still_stages_a_punch(): void
    {
        $device = $this->device();

        $this->push($device->serial_number, '&table=ATTLOG', "4301\t2026-07-15 09:00:00\t0\t1")->assertOk();

        $this->assertSame(1, DB::table('biometric_att_logs')->where('user_pin', '4301')->count());
    }
}
