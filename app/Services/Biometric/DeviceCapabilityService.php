<?php

namespace App\Services\Biometric;

use App\Models\HRM\BiometricDevice;
use App\Models\HRM\BiometricDeviceCommand;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Capability discovery for ZKTeco ADMS/PUSH terminals.
 *
 * The application previously had none: it could not ask a device what it is,
 * what it supports, how full it is, or what firmware it runs, and it could write
 * exactly one device-internal setting (the clock). This service is the storage
 * and vocabulary layer for that — parsing `INFO` / `GET OPTION` replies and the
 * `table=options&c=registry` push into per-device key/value rows, and recording
 * which keys a given model rejected with -1004.
 *
 * See docs/zkteco-adms-capability-matrix.md for the protocol ground truth and
 * per-row source confidence.
 *
 * Nothing here throws on malformed device input. Real terminals are inconsistent
 * about separators, casing and whether they echo the command back; a garbled
 * reply must degrade to "we learned nothing this round", never to a 500 on an
 * endpoint the hardware is actively polling.
 */
class DeviceCapabilityService
{
    /**
     * Keys worth asking for via `GET OPTION FROM <k1>,<k2>,…`.
     *
     * Matrix §2. Live counts are paired with their maxima so the UI can render
     * capacity headroom, which is the single most useful thing to show an admin
     * and is currently absent everywhere.
     *
     * ── Why both `Max…` and `~Max…` are asked for ────────────────────────────
     * Matrix §2 lists the unprefixed spellings, which is what every reference
     * implementation uses. A real MB460 (SN AF6P231260266, FW 8.0.4.6-20230217)
     * answered `INFO` with the *`~`-prefixed* SDK parameter names instead —
     * `~MaxUserCount`, `~DeviceName`, `~Platform` — and returned the MAC under
     * plain `MAC`, not `MACAddress`. Neither spelling is universal, so the probe
     * asks for both and the snapshot resolver (see indexOptions()) accepts
     * either. A key the firmware does not know is answered per-key with -1004
     * and recorded as unsupported, which is a cheap and informative outcome; the
     * alternative — guessing one spelling — cost us a completely blank
     * capabilities screen against the first real device we pointed this at.
     *
     * Residual risk: some firmware rejects a whole `GET OPTION` when any one key
     * is unknown, rather than per key. If that is ever observed, split the probe
     * into two commands (plain set, `~` set) in the queuing layer rather than
     * dropping either spelling from here.
     */
    public const CAPABILITY_KEYS = [
        // Identity / firmware — documented spellings first…
        'DeviceName',
        'FWVersion',
        'Platform',
        'IPAddress',
        'MACAddress',
        // …then the spellings a real MB460 actually answers to.
        '~DeviceName',
        '~Platform',
        '~SerialNumber',
        'MAC',
        // Feature flags. The `…FunOn` group is how a device says an engine is
        // absent rather than merely empty (matrix §4b).
        'WorkCode',
        'FingerFunOn',
        'FaceFunOn',
        'FvFunOn',
        'PvFunOn',
        // Live counts (the device answers these unprefixed).
        //
        // `AttLogCount` and `TransactionCount` are both asked for because they
        // are not interchangeable across models and at least one unit answers
        // only the second: the MB460 (SN AF6P231260266) omits `AttLogCount`
        // from a successful reply that requested it, and carries its
        // attendance-record count as `TransactionCount = 1009`. See the
        // attendance meter in snapshot() for how the two are reconciled.
        'UserCount',
        'FPCount',
        'FaceCount',
        'FvCount',
        'PvCount',
        'AttLogCount',
        'TransactionCount',
        'LockCount',
        // Maxima (paired with the counts above)
        'MaxUserCount',
        'MaxAttLogCount',
        'MaxFingerCount',
        'MaxFaceCount',
        '~MaxUserCount',
        '~MaxAttLogCount',
        '~MaxFingerCount',
        '~MaxFaceCount',
    ];

    /**
     * Registration fields pushed on `table=options&c=registry` (matrix §1).
     */
    public const REGISTRY_KEYS = [
        'DeviceType',
        'FirmVer',
        'IPAddress',
        'MACAddress',
        'Platform',
    ];

    /**
     * Writable device-internal settings (matrix §4b).
     *
     * Shape, per key:
     *   group     — display grouping, e.g. 'Biometric tuning'
     *   label     — human label for a form row
     *   type      — 'bool' | 'int' | 'string' | 'time'  (value type on the wire;
     *               ZK booleans are transmitted as 0/1, not true/false)
     *   dangerous — true when a bad value can strand the unit
     *   help      — one-line explanation, safe to render as helper text
     *
     * `dangerous` is not decoration. NetworkOn, TCPPort, UDPPort, DeviceID and
     * the auto-power-off schedule can take a device off a customer LAN where the
     * only recovery is physically walking to it. The consuming layer must gate
     * these behind an explicit confirmation that names the risk, and must never
     * offer them in a bulk/multi-device action.
     *
     * @var array<string, array{group: string, label: string, type: string, dangerous: bool, help: string}>
     */
    public const SETTINGS_CATALOGUE = [
        // ── Biometric tuning ────────────────────────────────────────────────
        'MThreshold' => [
            'group' => 'Biometric tuning',
            'label' => '1:N match threshold',
            'type' => 'int',
            'dangerous' => false,
            'help' => 'Identification strictness. Raise it if the device accepts the wrong person, lower it if staff have to punch twice.',
        ],
        'VThreshold' => [
            'group' => 'Biometric tuning',
            'label' => '1:1 verify threshold',
            'type' => 'int',
            'dangerous' => false,
            'help' => 'Strictness when the user has already entered a PIN.',
        ],
        'EThreshold' => [
            'group' => 'Biometric tuning',
            'label' => 'Enrolment threshold',
            'type' => 'int',
            'dangerous' => false,
            'help' => 'Minimum quality accepted when registering a new fingerprint.',
        ],
        'FingerFunOn' => [
            'group' => 'Biometric tuning',
            'label' => 'Fingerprint enabled',
            'type' => 'bool',
            'dangerous' => false,
            'help' => 'Whether the fingerprint sensor is in use.',
        ],
        'FaceFunOn' => [
            'group' => 'Biometric tuning',
            'label' => 'Face recognition enabled',
            'type' => 'bool',
            'dangerous' => false,
            'help' => 'Unsupported on units with no face engine.',
        ],
        'Must1To1' => [
            'group' => 'Biometric tuning',
            'label' => 'Require PIN before biometric',
            'type' => 'bool',
            'dangerous' => false,
            'help' => 'Forces 1:1 verification. Slower to use, much harder to spoof.',
        ],
        'MSpeed' => [
            'group' => 'Biometric tuning',
            'label' => 'Match speed',
            'type' => 'int',
            'dangerous' => false,
            'help' => 'Trades matching speed against accuracy.',
        ],
        'ShowScore' => [
            'group' => 'Biometric tuning',
            'label' => 'Show match score',
            'type' => 'bool',
            'dangerous' => false,
            'help' => 'Displays the raw match score on screen. Useful when tuning thresholds.',
        ],

        // ── Attendance rules ────────────────────────────────────────────────
        'AlarmReRec' => [
            'group' => 'Attendance rules',
            'label' => 'Duplicate punch window (minutes)',
            'type' => 'int',
            'dangerous' => false,
            'help' => 'Device-side suppression of repeat punches. Setting this stops the device sending punches the server would only discard.',
        ],
        'WorkCode' => [
            'group' => 'Attendance rules',
            'label' => 'Work code prompt',
            'type' => 'int',
            'dangerous' => false,
            'help' => 'Whether the device asks for a work code at punch time.',
        ],
        'AlarmAttLog' => [
            'group' => 'Attendance rules',
            'label' => 'Warn when attendance log is nearly full',
            'type' => 'int',
            'dangerous' => false,
            'help' => 'Remaining-record count at which the device starts warning.',
        ],
        'AlarmOpLog' => [
            'group' => 'Attendance rules',
            'label' => 'Warn when operation log is nearly full',
            'type' => 'int',
            'dangerous' => false,
            'help' => 'Remaining-record count at which the device starts warning.',
        ],

        // ── Access control ──────────────────────────────────────────────────
        'LockOn' => [
            'group' => 'Access control',
            'label' => 'Lock open duration',
            'type' => 'int',
            'dangerous' => false,
            'help' => 'How long the door relay stays open. Access-control models only.',
        ],
        'AntiPassbackOn' => [
            'group' => 'Access control',
            'label' => 'Anti-passback',
            'type' => 'int',
            'dangerous' => false,
            'help' => 'Blocks a second entry without an intervening exit.',
        ],
        'OnlyPINCard' => [
            'group' => 'Access control',
            'label' => 'PIN + card only',
            'type' => 'bool',
            'dangerous' => false,
            'help' => 'Disables biometric verification for door release.',
        ],
        'MustEnroll' => [
            'group' => 'Access control',
            'label' => 'Enrolled users only',
            'type' => 'bool',
            'dangerous' => false,
            'help' => 'Rejects anyone without a registered template.',
        ],

        // ── Display / UX ────────────────────────────────────────────────────
        'VoiceOn' => [
            'group' => 'Display and UX',
            'label' => 'Voice prompts',
            'type' => 'bool',
            'dangerous' => false,
            'help' => 'Spoken confirmation on a successful punch.',
        ],
        'TOState' => [
            'group' => 'Display and UX',
            'label' => 'Status-key timeout (seconds)',
            'type' => 'int',
            'dangerous' => false,
            'help' => 'How long a manually selected in/out state stays selected.',
        ],
        'TOMenu' => [
            'group' => 'Display and UX',
            'label' => 'Menu timeout (seconds)',
            'type' => 'int',
            'dangerous' => false,
            'help' => 'Idle time before the on-device menu closes itself.',
        ],
        'NewLng' => [
            'group' => 'Display and UX',
            'label' => 'Interface language',
            'type' => 'int',
            'dangerous' => false,
            'help' => 'Language code. Values are model-specific.',
        ],

        // ── Power ───────────────────────────────────────────────────────────
        'IdleMinute' => [
            'group' => 'Power',
            'label' => 'Idle timeout (minutes)',
            'type' => 'int',
            'dangerous' => false,
            'help' => 'Idle time before the idle action below is taken.',
        ],
        'IdlePower' => [
            'group' => 'Power',
            'label' => 'Idle action',
            'type' => 'int',
            'dangerous' => false,
            'help' => 'What the device does when idle: sleep or power off.',
        ],
        'AutoPowerOn' => [
            'group' => 'Power',
            'label' => 'Scheduled power-on time',
            'type' => 'time',
            'dangerous' => false,
            'help' => 'Time of day the device wakes itself.',
        ],
        'AutoPowerOff' => [
            'group' => 'Power',
            'label' => 'Scheduled power-off time',
            'type' => 'time',
            'dangerous' => true,
            'help' => 'DANGER: a device powered off on a schedule stops reporting and cannot be woken remotely. Recovery needs physical access.',
        ],
        'AutoPowerSuspend' => [
            'group' => 'Power',
            'label' => 'Scheduled suspend time',
            'type' => 'time',
            'dangerous' => true,
            'help' => 'DANGER: a suspended device stops polling the server and cannot be woken remotely.',
        ],

        // ── Network — the strand-the-device group ───────────────────────────
        'NetworkOn' => [
            'group' => 'Network',
            'label' => 'Network enabled',
            'type' => 'bool',
            'dangerous' => true,
            'help' => 'DANGER: switching this off takes the device off the network permanently. It cannot be switched back on remotely.',
        ],
        'TCPPort' => [
            'group' => 'Network',
            'label' => 'TCP service port',
            'type' => 'int',
            'dangerous' => true,
            'help' => 'DANGER: a wrong port makes the device unreachable. Recovery needs physical access to the keypad.',
        ],
        'UDPPort' => [
            'group' => 'Network',
            'label' => 'UDP service port',
            'type' => 'int',
            'dangerous' => true,
            'help' => 'DANGER: a wrong port makes the device unreachable. Recovery needs physical access to the keypad.',
        ],
        'DeviceID' => [
            'group' => 'Network',
            'label' => 'Device ID',
            'type' => 'int',
            'dangerous' => true,
            'help' => 'DANGER: changing the device ID can break the server association and orphan the unit.',
        ],
        'HiSpeedNet' => [
            'group' => 'Network',
            'label' => 'High-speed networking',
            'type' => 'bool',
            'dangerous' => false,
            'help' => 'Enables the faster link mode where the hardware supports it.',
        ],
    ];

    /**
     * The value under a maximum key is a literal count of records.
     */
    public const MAX_UNIT_RAW = 'raw';

    /**
     * The value under a maximum key is a number in an undetermined unit. It is
     * stored and shown as a raw option, but never used as the denominator of a
     * capacity meter.
     */
    public const MAX_UNIT_UNKNOWN = 'unknown';

    /**
     * Declared unit for every maximum key we may read, by literal spelling
     * (lower-cased; the `~` is part of the spelling and part of the meaning).
     *
     * ── Why this table exists ────────────────────────────────────────────────
     * The `~Max…` values a real ZKTeco MB460 returns are demonstrably NOT record
     * counts. From SN AF6P231260266, FW 8.0.4.6-20230217, push 2.0.33S:
     *
     *     FPCount = 26      but   ~MaxFingerCount = 20
     *     UserCount = 13    but   ~MaxUserCount   = 20
     *     ~MaxAttLogCount = 10          (the model is specced near 100,000)
     *     ~MaxFaceCount   = 1500        (the model is specced at exactly 1,500)
     *     ~MaxPvCount     = 0           (real zero — PvFunOn = 0, no palm engine)
     *
     * So some of these are scaled and some are not, and the scale is not even
     * consistent between them: a x1000 reading of `~MaxAttLogCount` gives 10,000
     * against a published spec near 100,000, so no single multiplier explains
     * the set. Applying one anyway would put a fabricated denominator on a
     * capacity-planning screen. Applying none, and dividing raw, renders
     * "26 / 20 = 130% full" on a device that is in fact almost empty — worse
     * than showing nothing, because an admin would act on it.
     *
     * The rule, therefore: **a maximum is only ever used as a denominator when
     * its unit is declared RAW here.** Anything undeclared, or declared UNKNOWN,
     * yields `max = null` / `percent = null`, and the UI renders the live count
     * with "headroom unknown". Showing "unknown" is strictly better than showing
     * a wrong number. resolveMax() then applies two further gates that can only
     * ever remove information, never add it (a value of 0, and used > max).
     *
     * ── Promoting an entry to RAW ────────────────────────────────────────────
     * Do not do it from a single device. It needs either vendor confirmation of
     * the unit, or two devices of different published capacity whose values
     * track that capacity 1:1. Record which it was in the marker below.
     *
     *   [verified] — checked against this real MB460 and its published spec
     *   [assumed]  — no device of ours has answered this key yet
     */
    private const MAX_KEY_UNITS = [
        // Unprefixed spellings, matrix §2. Every reference implementation treats
        // these as literal counts and our own captured GET OPTION replies
        // (UserCount=137 / MaxUserCount=3000) are consistent with that.
        // [assumed] — no ZKTeco unit of ours has answered these in the field yet.
        'maxusercount' => self::MAX_UNIT_RAW,
        'maxfingercount' => self::MAX_UNIT_RAW,
        'maxfacecount' => self::MAX_UNIT_RAW,
        'maxattlogcount' => self::MAX_UNIT_RAW,

        // `~` SDK parameter spellings, as returned by INFO on the MB460 above.
        // [verified] 1500 is exactly the MB460's published 1,500-face capacity.
        '~maxfacecount' => self::MAX_UNIT_RAW,
        // [verified] not a raw count — 20 against UserCount 13 would read as 65%
        // full on a unit specced in the thousands.
        '~maxusercount' => self::MAX_UNIT_UNKNOWN,
        // [verified] not a raw count — 20 is below the live FPCount of 26.
        '~maxfingercount' => self::MAX_UNIT_UNKNOWN,
        // [verified] not a raw count — 10 against a spec near 100,000.
        '~maxattlogcount' => self::MAX_UNIT_UNKNOWN,
        // [assumed] returned by the same device (2000 / 10 / 0) with no count to
        // pair against and no published figure to check, so they stay unknown.
        '~maxuserphotocount' => self::MAX_UNIT_UNKNOWN,
        '~maxfvcount' => self::MAX_UNIT_UNKNOWN,
        '~maxpvcount' => self::MAX_UNIT_UNKNOWN,
    ];

    /**
     * Engine-presence flags, mapped to the snapshot flag they surface as.
     *
     * `FvFunOn = 0` with `FvCount = 0` means "this model has no finger-vein
     * engine"; `FaceFunOn = 1` with `FaceCount = 0` means "it has one and nobody
     * is enrolled". Those are different sentences on screen, and only the device
     * can tell them apart.
     */
    private const ENGINE_FLAGS = [
        'fingerprint' => 'FingerFunOn',
        'face' => 'FaceFunOn',
        'finger_vein' => 'FvFunOn',
        'palm_vein' => 'PvFunOn',
        'user_photo' => 'PhotoFunOn',
    ];

    /**
     * A capability snapshot older than this is shown as stale in the UI.
     */
    public const STALE_AFTER_HOURS = 24;

    public const SOURCE_INFO = 'info';

    public const SOURCE_GET_OPTION = 'get_option';

    public const SOURCE_REGISTRY = 'registry';

    /**
     * The device rejected a `SET OPTION <key>=<value>` write with -1004/-1.
     *
     * The key is unavailable exactly as a rejected `GET OPTION` key is, and both
     * are recorded with `is_unsupported = true` — but they are not the same
     * event, and a row that says `get_option` when nothing was ever read is a
     * lie told to whoever debugs this next. It matters concretely: the settings
     * form writes with SET OPTION and the probe reads with GET OPTION, so
     * "which of the two did the device refuse?" is the first question asked when
     * a setting will not stick.
     */
    public const SOURCE_SET_OPTION = 'set_option';

    /**
     * The device rejected a whole COMMAND with -1004/-1, not a named key.
     *
     * These rows are the `CMD:<VERB>` pseudo-keys — a unit that cannot do `INFO`
     * at all — and they never came from reading or writing an option, so neither
     * option source describes them.
     */
    public const SOURCE_COMMAND = 'command';

    /**
     * The device was asked for this key in a `GET OPTION` that it answered with
     * `Return=0`, and simply left the key out of the reply.
     *
     * This is a different fact from -1004 and deserves its own word. -1004 is
     * the device saying "I do not have that"; a silent omission is the device
     * saying nothing at all while claiming success — observed on a real MB460
     * (SN AF6P231260266), which dropped `MThreshold` from an otherwise complete
     * seven-key reply. Both end up flagged unavailable so the UI stops offering
     * the key, but the source tells the UI which sentence to print: "not
     * supported on this model (answered -1004)" versus "the device ignored this
     * key when probed". Recording it at all is what stops such a key reading as
     * "never probed" forever, no matter how many times an admin clicks Probe.
     */
    public const SOURCE_OMITTED = 'omitted';

    /**
     * Values a device sends to mean "I do not know" — never allowed to overwrite
     * something an administrator typed in by hand.
     */
    private const MEANINGLESS_VALUES = ['', '-', '0.0.0.0', '00:00:00:00:00:00', 'null', 'none', 'unknown', 'n/a'];

    /**
     * Parse and persist the reply to an `INFO` command.
     *
     * @return array<string, string> the key/value pairs actually understood
     */
    public function parseInfoResponse(BiometricDevice $device, string $raw): array
    {
        $pairs = $this->parsePairs($raw);

        if ($pairs === []) {
            // Some firmware answers INFO with a bare positional CSV whose field
            // order is model-dependent and undocumented (matrix §2 marks INFO
            // [D], not [V]). Guessing the order would silently write wrong
            // capacity numbers into an admin-facing meter, so the raw string is
            // parked under a reserved key for a human to look at instead.
            $trimmed = trim($raw);

            if ($trimmed !== '') {
                $this->persist($device, ['~RawInfo' => $trimmed], self::SOURCE_INFO);
            }

            Log::warning('Biometric capability: INFO reply had no key=value pairs', [
                'device_id' => $device->id,
                'serial' => $device->serial_number,
                'raw' => mb_substr($trimmed, 0, 500),
            ]);

            return [];
        }

        $this->persist($device, $pairs, self::SOURCE_INFO);
        $this->touchProbedAt($device);

        return $pairs;
    }

    /**
     * Parse and persist the reply to a `GET OPTION FROM …` command.
     *
     * Keys that were asked for and are missing from a successful reply are
     * reconciled as unavailable — see reconcileRequestedKeys().
     *
     * @param  BiometricDeviceCommand|null  $command  the originating probe, when
     *                                                the caller already knows it
     * @return array<string, string> the key/value pairs actually understood
     */
    public function parseOptionResponse(BiometricDevice $device, string $raw, ?BiometricDeviceCommand $command = null): array
    {
        $pairs = $this->parsePairs($raw);

        if ($pairs === []) {
            Log::warning('Biometric capability: GET OPTION reply had no key=value pairs', [
                'device_id' => $device->id,
                'serial' => $device->serial_number,
                'raw' => mb_substr(trim($raw), 0, 500),
            ]);

            return [];
        }

        $this->persist($device, $pairs, self::SOURCE_GET_OPTION);
        $this->reconcileRequestedKeys($device, $pairs, $command);
        $this->touchProbedAt($device);

        return $pairs;
    }

    /**
     * Persist the registration payload (`table=options&c=registry`): DeviceType,
     * FirmVer, IPAddress, MACAddress, Platform.
     *
     * Where a field maps onto a real column on biometric_devices it is copied
     * across so administrators stop typing it by hand — but only into a column
     * that is currently blank. A device-reported value never clobbers something
     * a human entered: DHCP churn and firmware quirks would otherwise let a
     * terminal quietly rewrite the record an admin curated. The device's own
     * answer is always kept in the capability table regardless, so the UI can
     * show a mismatch without the write having happened.
     *
     * @param  array<string, mixed>  $fields
     */
    public function recordRegistry(BiometricDevice $device, array $fields): void
    {
        $pairs = [];

        foreach (self::REGISTRY_KEYS as $key) {
            $value = $this->findCaseInsensitive($fields, $key);

            if ($value !== null) {
                $pairs[$key] = $value;
            }
        }

        if ($pairs === []) {
            Log::warning('Biometric capability: registry push carried no recognised fields', [
                'device_id' => $device->id,
                'serial' => $device->serial_number,
                'received' => array_keys($fields),
            ]);

            return;
        }

        $this->persist($device, $pairs, self::SOURCE_REGISTRY);

        $columnMap = [
            'DeviceType' => 'model',
            'IPAddress' => 'ip_address',
        ];

        $updates = [];

        foreach ($columnMap as $field => $column) {
            if (! array_key_exists($field, $pairs) || ! $this->isMeaningful($pairs[$field])) {
                continue;
            }

            if ($this->isMeaningful($device->getAttribute($column))) {
                continue; // admin-set (or previously learned) — leave it alone
            }

            $updates[$column] = $pairs[$field];
        }

        foreach ($updates as $column => $value) {
            $device->setAttribute($column, $value);
        }

        $device->setAttribute('capabilities_probed_at', now());
        $device->save();
    }

    /**
     * Record that a device rejected a command, when the rejection was a
     * capability answer rather than an error.
     *
     * -1004 ("not supported on this model") and -1 ("unsupported / no data") are
     * the only per-model capability signal the protocol gives us. For an option
     * command the rejected key(s) come from the payload; for anything else the
     * command verb itself is recorded under a `CMD:` prefix, so a unit that
     * cannot do `INFO` is remembered as such and is not asked again on a loop.
     *
     * The row's `source` names the command that was actually refused —
     * `get_option` for a read, `set_option` for a write, `command` for a
     * verb-level `CMD:` row. It used to say `get_option` for all three, which
     * put "the device would not read this key" on a row created because the
     * device would not *write* it.
     *
     * This does not change what the UI does with these rows. `source` is
     * consumed as a single discriminator — `source === 'omitted'` separates a
     * silent omission from an explicit -1004 (BiometricPanel.jsx readKeyState) —
     * and every other value, recognised or not, falls through to the -1004
     * wording. Which is the true sentence for all three of these: the device
     * answered a rejection code out loud.
     */
    public function markUnsupported(BiometricDeviceCommand $command, string $returnCode): void
    {
        $meaning = BiometricDeviceCommand::decodeReturnCode($returnCode);

        if (! $meaning['unsupported']) {
            return;
        }

        $device = $command->device;

        if (! $device) {
            Log::warning('Biometric capability: unsupported ack for a command with no device', [
                'command_id' => $command->id,
                'return_code' => $returnCode,
            ]);

            return;
        }

        $payload = is_array($command->payload) ? $command->payload : [];
        $keys = [];
        $source = self::SOURCE_COMMAND;

        switch ($command->command_type) {
            case 'GET_OPTION':
                $raw = $payload['keys'] ?? $payload['options'] ?? [];
                $raw = is_string($raw) ? explode(',', $raw) : (is_array($raw) ? $raw : []);
                $keys = array_filter(array_map(fn ($key) => trim((string) $key), $raw));
                $source = self::SOURCE_GET_OPTION;
                break;

            case 'SET_OPTION':
                $key = trim((string) ($payload['key'] ?? ''));
                if ($key !== '') {
                    $keys = [$key];
                }
                $source = self::SOURCE_SET_OPTION;
                break;
        }

        if ($keys === []) {
            // No named key survived the payload, so what was refused is the verb
            // itself — and the row is about the command, not about a read or a
            // write of an option.
            $keys = ['CMD:'.$command->command_type];
            $source = self::SOURCE_COMMAND;
        }

        $rows = [];

        foreach ($keys as $key) {
            $rows[$key] = null;
        }

        $this->persist($device, $rows, $source, true);
        $this->touchProbedAt($device);
    }

    /**
     * UI-facing read model for one device's capabilities.
     *
     * Documented shape — another agent renders this blind, so it is stable:
     *
     * [
     *   'device_id'   => int,
     *   'name'        => string,
     *   'serial_number' => string|null,
     *   'identity'    => [
     *       'device_name'  => string|null,   // GET OPTION DeviceName
     *       'device_type'  => string|null,   // registry DeviceType
     *       'platform'     => string|null,
     *       'firmware'     => string|null,   // FWVersion, falling back to FirmVer
     *       'mac_address'  => string|null,
     *       'ip_address'   => string|null,   // device-reported, may differ from the record
     *       'record_ip_address' => string|null, // what is stored on biometric_devices
     *       'record_model' => string|null,
     *   ],
     *   'capacity'    => [                   // one entry per meter, same shape each
     *       'users'        => ['label'=>string,'used'=>int|null,'max'=>int|null,
     *                          'percent'=>float|null,'supported'=>bool,'known'=>bool],
     *       'fingerprints' => ...same...,
     *       'faces'        => ...same...,
     *       'attendance'   => ...same...,
     *   ],
     *   'counters'    => ['transactions' => int|null, 'locks' => int|null],
     *   'flags'       => ['work_code' => bool|null, …engine flags…],
     *   'supported_keys'   => string[],      // keys the device answered
     *   'unsupported_keys' => string[],      // keys it rejected with -1/-1004,
     *                                        // or silently omitted from a
     *                                        // successful reply
     *   'options'     => [                   // every stored key, raw
     *       '<Key>' => ['value'=>string|null,'unsupported'=>bool,
     *                   'source'=>'info'|'get_option'|'set_option'|'registry'
     *                             |'omitted'|'command',
     *                   'probed_at'=>string|null],  // ISO-8601
     *   ],
     *   'probed_at'   => string|null,        // ISO-8601, device-level
     *   'is_stale'    => bool,               // never probed, or older than STALE_AFTER_HOURS
     *   'has_data'    => bool,               // false => never probed, render an empty state
     * ]
     *
     * `capacity.attendance.used` reads `AttLogCount`, falling back to
     * `TransactionCount` on models that do not answer the first (the MB460 does
     * not). On such a device that number therefore appears twice — once as the
     * attendance meter and once under `counters.transactions` — which is
     * correct rather than duplicated: they are the same underlying store, and
     * suppressing either would be the UI deciding which one the admin meant.
     *
     * `percent` is null whenever either side of the ratio is unknown; a meter
     * must render "—" rather than 0% in that case. `max` is null in that case
     * too, deliberately: consumers recompute the ratio themselves, so a
     * denominator we cannot vouch for must be withheld, not merely left out of
     * `percent`. The device's raw answer is still in `options` either way.
     *
     * An unsupported key carries `source = 'omitted'` when the device ignored it
     * in a reply it called successful, and the source of the command it actually
     * refused otherwise (`get_option`, `set_option`, or `command` for a
     * verb-level `CMD:` row). Both mean "do not offer this key"; only the
     * wording differs, and a UI that prints "-1004" over an omission is telling
     * the admin something untrue. `omitted` is the ONLY value a consumer needs
     * to branch on — every other value means "the device answered a rejection
     * code", so an unrecognised one degrades to the -1004 wording, which is true
     * of all of them.
     *
     * `supported` is false when the device explicitly rejected the key (-1004),
     * when it reported the corresponding engine off (`FvFunOn = 0`), or when it
     * reported a maximum of zero — all three mean "this unit has no such store".
     * So `supported && ! known` means "never asked", which is a different empty
     * state from "cannot".
     *
     * `flags` always carries `work_code`. The engine flags (`fingerprint`,
     * `face`, `finger_vein`, `palm_vein`, `user_photo`) appear only when the
     * device actually reported them — an absent key means "never told us",
     * which is not the same as false, and inventing a null would erase that.
     *
     * @return array<string, mixed>
     */
    public function snapshot(BiometricDevice $device): array
    {
        $rows = DB::table('biometric_device_capabilities')
            ->where('biometric_device_id', $device->id)
            ->get();

        $options = [];

        foreach ($rows as $row) {
            $options[$row->capability_key] = [
                'value' => $row->value,
                'unsupported' => (bool) $row->is_unsupported,
                'source' => $row->source,
                'probed_at' => $this->toIso($row->probed_at),
            ];
        }

        $supported = [];
        $unsupported = [];

        foreach ($options as $key => $option) {
            if ($option['unsupported']) {
                $unsupported[] = $key;
            } else {
                $supported[] = $key;
            }
        }

        sort($supported);
        sort($unsupported);

        $probedAt = $device->getAttribute('capabilities_probed_at');
        $probedAtIso = $this->toIso($probedAt);

        // Every lookup below goes through the normalised index, so `~Platform`
        // and `Platform`, `MAC` and `MACAddress` all resolve. Real firmware
        // picks a spelling and we do not get a vote (see CAPABILITY_KEYS).
        $index = $this->indexOptions($options);

        return [
            'device_id' => $device->id,
            'name' => $device->name,
            'serial_number' => $device->serial_number,
            'identity' => [
                'device_name' => $this->value($index, 'DeviceName'),
                'device_type' => $this->value($index, 'DeviceType'),
                'platform' => $this->value($index, 'Platform'),
                'firmware' => $this->value($index, 'FWVersion', 'FirmVer'),
                // The MB460 answers `MAC`; a registry push carries `MACAddress`.
                'mac_address' => $this->value($index, 'MACAddress', 'MAC'),
                'ip_address' => $this->value($index, 'IPAddress'),
                'record_ip_address' => $device->ip_address,
                'record_model' => $device->model,
            ],
            'capacity' => [
                'users' => $this->meter($index, 'Users', 'UserCount', 'MaxUserCount'),
                'fingerprints' => $this->meter($index, 'Fingerprints', 'FPCount', 'MaxFingerCount', 'FingerFunOn'),
                'faces' => $this->meter($index, 'Faces', 'FaceCount', 'MaxFaceCount', 'FaceFunOn'),
                // `TransactionCount` is a fallback, not a synonym: the MB460
                // never answers `AttLogCount`, so without it this meter is
                // permanently blank on that model. Documented spelling first.
                'attendance' => $this->meter($index, 'Attendance records', ['AttLogCount', 'TransactionCount'], 'MaxAttLogCount'),
            ],
            'counters' => [
                'transactions' => $this->intValue($index, 'TransactionCount'),
                'locks' => $this->intValue($index, 'LockCount'),
            ],
            'flags' => $this->flags($index),
            'supported_keys' => $supported,
            'unsupported_keys' => $unsupported,
            'options' => $options,
            'probed_at' => $probedAtIso,
            'is_stale' => $this->isStale($probedAt),
            'has_data' => $options !== [],
        ];
    }

    // ──────────────────────────────────────────────────────────────
    //  Internals
    // ──────────────────────────────────────────────────────────────

    /**
     * Liberal key=value parser for device replies.
     *
     * Real terminals separate pairs with tabs, newlines, commas, `&` or plain
     * spaces, sometimes mix them in one payload, and often prefix the reply with
     * an echo of the command verb. Values may legitimately contain `=` (base64,
     * firmware banners), so only the first `=` splits. Anything that is not a
     * `<key>=<value>` token is skipped rather than fatal.
     *
     * ── Padding around `=` ───────────────────────────────────────────────────
     * The separator is matched as `[ \t]*=[ \t]*`, so `FWVersion = Ver 8.0.4.6`
     * parses identically to `FWVersion=Ver 8.0.4.6`. Our MB460 does not pad — a
     * live probe stored 74 keys through the unpadded form, which could not have
     * happened if the wire had spaces — but the ZK option namespace is answered
     * by a dozen firmware families and the previous parser degraded to *zero*
     * pairs against a padded reply, dumping the whole payload into the `~RawInfo`
     * park with nothing else recorded. A total, silent loss is too expensive a
     * failure for a formatting difference.
     *
     * The hard part is that values legitimately contain spaces ("Ver 8.0.4.6",
     * "ZKTECO CO."), so a value cannot end at the next space. It ends at the next
     * `<key>=` boundary instead, and the lookahead that finds that boundary
     * (`(?=\s+<keychars>+[ \t]*=)`) is padded in exactly the same way as the
     * separator itself — the two must agree or a padded payload with several
     * pairs on one line would run them together. A boundary still requires a
     * literal `=` after the candidate key, so "Ver 8.0.4.6-20230217" and
     * "ZKTECO CO." contain no boundary and survive whole.
     *
     * @return array<string, string>
     */
    private function parsePairs(string $raw): array
    {
        $normalised = str_replace(["\r\n", "\r", "\t", ',', '&'], "\n", $raw);

        // Strip a leading command echo such as "GET OPTION FROM ..." or "INFO".
        $normalised = preg_replace('/^\s*(GET\s+OPTION(\s+FROM)?|SET\s+OPTION|INFO|CHECK)\b/i', '', $normalised) ?? $normalised;

        $pairs = [];

        foreach (explode("\n", $normalised) as $segment) {
            $segment = trim($segment);

            if ($segment === '' || ! str_contains($segment, '=')) {
                continue;
            }

            // A value may legitimately contain spaces ("FWVersion=Ver 6.60 Apr
            // 22 2016") *and* several pairs may share one line ("PIN=1 Name=Bob
            // Smith"), so a value runs lazily up to the next `<key>=` boundary
            // rather than to the next space. Only the first `=` splits, which
            // keeps base64 and padded values intact.
            //
            // `[ \t]*` on both sides of the `=` — in the separator AND in the
            // boundary lookahead — is what makes `Key = Value` parse the same as
            // `Key=Value`. Horizontal whitespace only: the segment was already
            // split on newlines, so allowing `\s` here would buy nothing and
            // would let a stray line break be swallowed into a key.
            $matched = preg_match_all(
                '/([A-Za-z0-9_~.\-]+)[ \t]*=[ \t]*(.*?)(?=(?:\s+[A-Za-z0-9_~.\-]+[ \t]*=)|$)/',
                $segment,
                $matches,
                PREG_SET_ORDER
            );

            if (! $matched) {
                continue;
            }

            foreach ($matches as $match) {
                $key = trim($match[1]);

                if ($key === '' || mb_strlen($key) > 64) {
                    continue;
                }

                $pairs[$key] = trim($match[2]);
            }
        }

        return $pairs;
    }

    /**
     * Upsert capability rows. Unique on (device, key), so a re-probe overwrites.
     *
     * @param  array<string, string|null>  $pairs
     */
    private function persist(BiometricDevice $device, array $pairs, string $source, bool $unsupported = false): void
    {
        if ($pairs === []) {
            return;
        }

        $now = now();
        $rows = [];

        foreach ($pairs as $key => $value) {
            $rows[] = [
                'biometric_device_id' => $device->id,
                'capability_key' => mb_substr((string) $key, 0, 64),
                'value' => $value === null ? null : (string) $value,
                'is_unsupported' => $unsupported,
                'source' => $source,
                'probed_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        try {
            DB::table('biometric_device_capabilities')->upsert(
                $rows,
                ['biometric_device_id', 'capability_key'],
                ['value', 'is_unsupported', 'source', 'probed_at', 'updated_at']
            );
        } catch (\Throwable $e) {
            // A capability probe is diagnostics. It must never take down the
            // endpoint the hardware is polling.
            Log::error('Biometric capability: failed to persist capability rows', [
                'device_id' => $device->id,
                'source' => $source,
                'keys' => array_keys($pairs),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Record the keys a probe asked for and did not get back.
     *
     * A device can answer `GET OPTION` with `Return=0` and quietly leave a key
     * out of the reply — a real MB460 does exactly that with `MThreshold`.
     * markUnsupported() never fires for those, because there is no non-zero
     * return code, so without this the key produces no row at all and the
     * snapshot reports it as `supported = true, known = false` — "never probed"
     * — for ever. The admin re-probes, sees "unknown" again, and has no way to
     * learn that the answer will never come.
     *
     * Guards, because a mis-correlated push must not erase real data:
     *  - nothing is recorded unless at least one requested key *was* answered,
     *    which is the cheapest available evidence that this payload really is
     *    that command's reply;
     *  - a key we already hold a value for is left alone. Reconciliation may
     *    only fill in unknowns, never overwrite something a device once told us
     *    (an `INFO` reply routinely carries keys a later `GET OPTION` omits).
     *
     * @param  array<string, string>  $answered
     */
    private function reconcileRequestedKeys(BiometricDevice $device, array $answered, ?BiometricDeviceCommand $command): void
    {
        $command ??= $this->latestOptionProbe($device);

        if ($command === null) {
            return;
        }

        $requested = $this->requestedOptionKeys($command);

        if ($requested === []) {
            return;
        }

        $answeredNames = [];

        foreach (array_keys($answered) as $key) {
            $answeredNames[$this->normaliseKey((string) $key)] = true;
        }

        $missing = [];

        foreach ($requested as $key) {
            $name = $this->normaliseKey($key);

            if (! isset($answeredNames[$name])) {
                $missing[$key] = $name;
            }
        }

        // Every requested key missing => this reply is almost certainly not the
        // answer to that command. Say nothing rather than condemn the lot.
        if ($missing === [] || count($missing) === count($requested)) {
            return;
        }

        $held = [];

        foreach (
            DB::table('biometric_device_capabilities')
                ->where('biometric_device_id', $device->id)
                ->where(function ($query) {
                    // Anything the device has already told us about, in either
                    // of the two ways it can: a value, or an explicit -1004.
                    // The -1004 rows carry a null value, so `whereNotNull` alone
                    // leaves them exposed and a later silent omission relabels
                    // "not supported on this model" as "the device ignored this
                    // key" — a downgrade of a harder fact to a softer one, and
                    // a sentence the UI would then print untruthfully.
                    $query->whereNotNull('value')->orWhere('is_unsupported', true);
                })
                ->pluck('capability_key') as $key
        ) {
            $held[$this->normaliseKey((string) $key)] = true;
        }

        $rows = [];

        foreach ($missing as $key => $name) {
            if (! isset($held[$name])) {
                $rows[$key] = null;
            }
        }

        if ($rows === []) {
            return;
        }

        // Worth a log line, not just a row: a model that ignores MThreshold
        // cannot have its 1:N match threshold tuned from here at all, and that
        // is a support answer someone will need.
        Log::info('Biometric capability: keys omitted from a successful GET OPTION reply', [
            'device_id' => $device->id,
            'serial' => $device->serial_number,
            'command_id' => $command->id,
            'omitted' => array_keys($rows),
        ]);

        $this->persist($device, $rows, self::SOURCE_OMITTED, true);
    }

    /**
     * The keys a `GET_OPTION` command asked for.
     *
     * Deliberately a mirror of BiometricDeviceCommand::optionKeysFromPayload(),
     * including its fallback to the full probe set for an empty payload: that
     * method is protected, and the emitted wire string is the contract this has
     * to agree with. If either changes, change both.
     *
     * @return array<int, string>
     */
    private function requestedOptionKeys(BiometricDeviceCommand $command): array
    {
        if ($command->command_type !== 'GET_OPTION') {
            return [];
        }

        $payload = is_array($command->payload) ? $command->payload : [];
        $keys = $payload['keys'] ?? $payload['options'] ?? null;

        if (is_string($keys)) {
            $keys = explode(',', $keys);
        }

        if (! is_array($keys)) {
            $keys = [];
        }

        $keys = array_values(array_unique(array_filter(
            array_map(fn ($key) => trim((string) $key), $keys),
            fn ($key) => $key !== ''
        )));

        return $keys !== [] ? $keys : self::CAPABILITY_KEYS;
    }

    /**
     * The probe a pushed option reply most plausibly belongs to.
     *
     * ADMS attaches no command id to a pushed result, so correlation is
     * "the newest dispatched GET_OPTION for this device" — the same
     * newest-command-wins assumption the push controller already makes.
     */
    private function latestOptionProbe(BiometricDevice $device): ?BiometricDeviceCommand
    {
        return BiometricDeviceCommand::query()
            ->where('biometric_device_id', $device->id)
            ->where('command_type', 'GET_OPTION')
            ->whereIn('status', [BiometricDeviceCommand::STATUS_SENT, BiometricDeviceCommand::STATUS_EXECUTED])
            ->orderByDesc('sent_at')
            ->orderByDesc('id')
            ->first();
    }

    private function touchProbedAt(BiometricDevice $device): void
    {
        // Set directly rather than via update(): capabilities_probed_at is
        // maintained by this service, not by admin form input, so it is
        // deliberately absent from BiometricDevice::$fillable.
        $device->setAttribute('capabilities_probed_at', now());
        $device->save();
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    private function findCaseInsensitive(array $fields, string $wanted): ?string
    {
        foreach ($fields as $key => $value) {
            if (is_string($key) && strcasecmp(trim($key), $wanted) === 0) {
                return is_scalar($value) ? trim((string) $value) : null;
            }
        }

        return null;
    }

    private function isMeaningful(mixed $value): bool
    {
        if ($value === null || is_array($value)) {
            return false;
        }

        return ! in_array(strtolower(trim((string) $value)), self::MEANINGLESS_VALUES, true);
    }

    /**
     * Collapse the stored option map into a lookup tolerant of the `~` prefix
     * and of casing.
     *
     * ZKTeco's SDK parameter names are `~`-prefixed for read-only device
     * descriptors (matrix §4b) and unprefixed for the rest, but which spelling a
     * given firmware answers `INFO` and `GET OPTION` with is a per-model
     * accident. `~MaxUserCount` and `MaxUserCount` are the same fact, so they
     * fold onto one lookup name — but the literal spelling that supplied the
     * winning value is kept, because the unit of a maximum depends on it
     * (MAX_KEY_UNITS).
     *
     * Where both spellings arrive, the entry that actually answered wins over
     * one that was rejected or came back empty, and the unprefixed spelling
     * breaks a tie: it is the one documented as a literal count.
     *
     * @param  array<string, array{value: string|null, unsupported: bool}>  $options
     * @return array<string, array{key: string, value: string|null, unsupported: bool}>
     */
    private function indexOptions(array $options): array
    {
        $index = [];

        foreach ($options as $key => $option) {
            $key = (string) $key;
            $name = $this->normaliseKey($key);

            if ($name === '') {
                continue;
            }

            $candidate = [
                'key' => $key,
                'value' => $option['value'] ?? null,
                'unsupported' => (bool) ($option['unsupported'] ?? false),
            ];

            if (! isset($index[$name]) || $this->entryRank($candidate) > $this->entryRank($index[$name])) {
                $index[$name] = $candidate;
            }
        }

        return $index;
    }

    private function normaliseKey(string $key): string
    {
        return strtolower(ltrim(trim($key), '~'));
    }

    /**
     * @param  array{key: string, value: string|null, unsupported: bool}  $entry
     */
    private function entryRank(array $entry): int
    {
        if ($entry['unsupported']) {
            return 0;
        }

        if (! $this->isMeaningful($entry['value'])) {
            return 1;
        }

        return str_starts_with(trim($entry['key']), '~') ? 2 : 3;
    }

    /**
     * First meaningful value among the given names, `~`-insensitively.
     *
     * @param  array<string, array{key: string, value: string|null, unsupported: bool}>  $index
     */
    private function value(array $index, string ...$names): ?string
    {
        foreach ($names as $name) {
            $entry = $index[$this->normaliseKey($name)] ?? null;

            if ($entry === null || $entry['unsupported']) {
                continue;
            }

            if ($this->isMeaningful($entry['value'])) {
                return $entry['value'];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $index
     */
    private function intValue(array $index, string $name): ?int
    {
        $value = $this->value($index, $name);

        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * @param  array<string, mixed>  $index
     */
    private function boolValue(array $index, string $name): ?bool
    {
        $value = $this->intValue($index, $name);

        return $value === null ? null : $value > 0;
    }

    /**
     * Feature flags. Engine flags are omitted rather than nulled when the device
     * never reported them — see the snapshot() docblock.
     *
     * @param  array<string, mixed>  $index
     * @return array<string, bool|null>
     */
    private function flags(array $index): array
    {
        $flags = ['work_code' => $this->boolValue($index, 'WorkCode')];

        foreach (self::ENGINE_FLAGS as $flag => $key) {
            $value = $this->boolValue($index, $key);

            if ($value !== null) {
                $flags[$flag] = $value;
            }
        }

        return $flags;
    }

    /**
     * Build one capacity meter.
     *
     * `supported` is false when the device rejected the key with -1004, when it
     * reported the engine off (`FaceFunOn = 0`), or when it reported a maximum
     * of zero — all three mean "this unit has no such store", which is how the
     * UI learns to stop offering the feature. A device that has the engine and
     * nothing enrolled is `supported = true, used = 0` instead.
     *
     * `$countKeys` may name more than one key, tried in order, so a meter can
     * survive a model that spells its count differently — see resolveCount().
     *
     * @param  array<string, mixed>  $index
     * @param  string|array<int, string>  $countKeys
     * @return array{label: string, used: int|null, max: int|null, percent: float|null, supported: bool, known: bool}
     */
    private function meter(array $index, string $label, string|array $countKeys, string $maxKey, ?string $engineKey = null): array
    {
        [$countEntry, $used] = $this->resolveCount($index, (array) $countKeys);
        $maxEntry = $index[$this->normaliseKey($maxKey)] ?? null;

        $rejected = ($countEntry['unsupported'] ?? false) || ($maxEntry['unsupported'] ?? false);

        [$max, $storeAbsent] = $this->resolveMax($maxEntry);

        // Independent sanity gate. Even a maximum whose unit we believe we know
        // is discarded when the live count exceeds it: a store cannot hold less
        // than it already holds, so the likelier explanation is that the unit is
        // not what we assumed. Consumers divide `used` by `max` themselves, so
        // the denominator has to be withheld, not just excluded from `percent` —
        // otherwise the meter renders "26 / 20, 130% full" on a device that has
        // thousands of slots free.
        if ($max !== null && $max > 0 && $used !== null && $used > $max) {
            $max = null;
        }

        $percent = null;

        if ($used !== null && $max !== null && $max > 0) {
            $percent = round(min(100, ($used / $max) * 100), 1);
        }

        $engineOn = $engineKey === null ? null : $this->boolValue($index, $engineKey);

        return [
            'label' => $label,
            'used' => $used,
            'max' => $max,
            'percent' => $percent,
            'supported' => ! $rejected && $engineOn !== false && ! $storeAbsent,
            'known' => $used !== null || $max !== null,
        ];
    }

    /**
     * Resolve a meter's live count from the first key that actually answered.
     *
     * Returns [the entry the number came from, the number], so the caller can
     * judge `supported` against the key that supplied the value rather than
     * against a preferred spelling the device may never use.
     *
     * ── Why a meter needs more than one count key ────────────────────────────
     * The attendance meter was mapped to `AttLogCount` alone, which the MB460
     * (SN AF6P231260266) does not answer: a six-key probe requesting it came
     * back `Return=0` with the other five and `AttLogCount` silently omitted,
     * the same pattern as `MThreshold`. That meter could therefore never
     * populate on this hardware however often an admin re-probed it. The unit's
     * attendance-record count is carried as `TransactionCount = 1009`.
     *
     * The order is the contract: the documented spelling is tried first so a
     * model that answers `AttLogCount` still wins, and the fallback only speaks
     * when the preferred key produced nothing usable. A key present but flagged
     * unsupported is remembered as the fallback entry — so if nothing answers,
     * the meter still reports `supported = false` rather than pretending it was
     * never asked.
     *
     * @param  array<string, array{key: string, value: string|null, unsupported: bool}>  $index
     * @param  array<int, string>  $keys
     * @return array{0: array{key: string, value: string|null, unsupported: bool}|null, 1: int|null}
     */
    private function resolveCount(array $index, array $keys): array
    {
        $fallback = null;

        foreach ($keys as $key) {
            $entry = $index[$this->normaliseKey($key)] ?? null;

            if ($entry === null) {
                continue;
            }

            $fallback ??= $entry;

            if (! $entry['unsupported'] && is_numeric($entry['value'])) {
                return [$entry, (int) $entry['value']];
            }
        }

        return [$fallback, null];
    }

    /**
     * Interpret a stored maximum, returning [usable maximum, store is absent].
     *
     * A maximum only survives as a number when MAX_KEY_UNITS declares the
     * spelling that produced it to be a literal count. Everything else — an
     * undeclared key, or one declared UNKNOWN — comes back null, and the meter
     * renders the live count with the headroom unknown. See MAX_KEY_UNITS for
     * why guessing a multiplier is not on the table.
     *
     * @param  array{key: string, value: string|null, unsupported: bool}|null  $entry
     * @return array{0: int|null, 1: bool}
     */
    private function resolveMax(?array $entry): array
    {
        if ($entry === null || $entry['unsupported'] || ! is_numeric($entry['value'])) {
            return [null, false];
        }

        $raw = (int) $entry['value'];

        if ($raw < 0) {
            // Negative "maxima" are ADMS return codes (-1 / -1004) leaking
            // through as a value, never quantities.
            return [null, false];
        }

        if ($raw === 0) {
            // Zero is the one value that survives any unit — zero thousands is
            // still zero. `~MaxPvCount = 0` alongside `PvFunOn = 0` is a device
            // saying it has no palm-vein store at all, which must read as
            // "feature absent", never as "0 of 0, 100% full".
            return [0, true];
        }

        $unit = self::MAX_KEY_UNITS[strtolower(trim($entry['key']))] ?? self::MAX_UNIT_UNKNOWN;

        return $unit === self::MAX_UNIT_RAW ? [$raw, false] : [null, false];
    }

    private function toIso(mixed $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        try {
            return Carbon::parse($value)->toIso8601String();
        } catch (\Throwable) {
            return null;
        }
    }

    private function isStale(mixed $probedAt): bool
    {
        if (empty($probedAt)) {
            return true;
        }

        try {
            return Carbon::parse($probedAt)->lt(now()->subHours(self::STALE_AFTER_HOURS));
        } catch (\Throwable) {
            return true;
        }
    }
}
