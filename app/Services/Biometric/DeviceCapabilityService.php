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
     */
    public const CAPABILITY_KEYS = [
        // Identity / firmware
        'DeviceName',
        'FWVersion',
        'Platform',
        'IPAddress',
        'MACAddress',
        // Feature flags
        'WorkCode',
        'LockCount',
        // Live counts
        'UserCount',
        'FPCount',
        'FaceCount',
        'AttLogCount',
        'TransactionCount',
        // Maxima (paired with the counts above)
        'MaxUserCount',
        'MaxAttLogCount',
        'MaxFingerCount',
        'MaxFaceCount',
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
     * A capability snapshot older than this is shown as stale in the UI.
     */
    public const STALE_AFTER_HOURS = 24;

    public const SOURCE_INFO = 'info';

    public const SOURCE_GET_OPTION = 'get_option';

    public const SOURCE_REGISTRY = 'registry';

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
     * @return array<string, string> the key/value pairs actually understood
     */
    public function parseOptionResponse(BiometricDevice $device, string $raw): array
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

        switch ($command->command_type) {
            case 'GET_OPTION':
                $raw = $payload['keys'] ?? $payload['options'] ?? [];
                $raw = is_string($raw) ? explode(',', $raw) : (is_array($raw) ? $raw : []);
                $keys = array_filter(array_map(fn ($key) => trim((string) $key), $raw));
                break;

            case 'SET_OPTION':
                $key = trim((string) ($payload['key'] ?? ''));
                if ($key !== '') {
                    $keys = [$key];
                }
                break;
        }

        if ($keys === []) {
            $keys = ['CMD:'.$command->command_type];
        }

        $rows = [];

        foreach ($keys as $key) {
            $rows[$key] = null;
        }

        $this->persist($device, $rows, self::SOURCE_GET_OPTION, true);
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
     *   'flags'       => ['work_code' => bool|null],
     *   'supported_keys'   => string[],      // keys the device answered
     *   'unsupported_keys' => string[],      // keys it rejected with -1/-1004
     *   'options'     => [                   // every stored key, raw
     *       '<Key>' => ['value'=>string|null,'unsupported'=>bool,
     *                   'source'=>'info'|'get_option'|'registry',
     *                   'probed_at'=>string|null],  // ISO-8601
     *   ],
     *   'probed_at'   => string|null,        // ISO-8601, device-level
     *   'is_stale'    => bool,               // never probed, or older than STALE_AFTER_HOURS
     *   'has_data'    => bool,               // false => never probed, render an empty state
     * ]
     *
     * `percent` is null whenever either side of the ratio is unknown; a meter
     * must render "—" rather than 0% in that case. `supported` is false only
     * when the device explicitly rejected the key, so `supported && ! known`
     * means "never asked", which is a different empty state from "cannot".
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

        return [
            'device_id' => $device->id,
            'name' => $device->name,
            'serial_number' => $device->serial_number,
            'identity' => [
                'device_name' => $this->value($options, 'DeviceName'),
                'device_type' => $this->value($options, 'DeviceType'),
                'platform' => $this->value($options, 'Platform'),
                'firmware' => $this->value($options, 'FWVersion') ?? $this->value($options, 'FirmVer'),
                'mac_address' => $this->value($options, 'MACAddress'),
                'ip_address' => $this->value($options, 'IPAddress'),
                'record_ip_address' => $device->ip_address,
                'record_model' => $device->model,
            ],
            'capacity' => [
                'users' => $this->meter($options, 'Users', 'UserCount', 'MaxUserCount'),
                'fingerprints' => $this->meter($options, 'Fingerprints', 'FPCount', 'MaxFingerCount'),
                'faces' => $this->meter($options, 'Faces', 'FaceCount', 'MaxFaceCount'),
                'attendance' => $this->meter($options, 'Attendance records', 'AttLogCount', 'MaxAttLogCount'),
            ],
            'counters' => [
                'transactions' => $this->intValue($options, 'TransactionCount'),
                'locks' => $this->intValue($options, 'LockCount'),
            ],
            'flags' => [
                'work_code' => $this->boolValue($options, 'WorkCode'),
            ],
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
            $matched = preg_match_all(
                '/([A-Za-z0-9_~.\-]+)=(.*?)(?=(?:\s+[A-Za-z0-9_~.\-]+=)|$)/',
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
     * @param  array<string, array{value: string|null, unsupported: bool}>  $options
     */
    private function value(array $options, string $key): ?string
    {
        if (! isset($options[$key]) || $options[$key]['unsupported']) {
            return null;
        }

        $value = $options[$key]['value'];

        return $this->isMeaningful($value) ? $value : null;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function intValue(array $options, string $key): ?int
    {
        $value = $this->value($options, $key);

        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function boolValue(array $options, string $key): ?bool
    {
        $value = $this->intValue($options, $key);

        return $value === null ? null : $value > 0;
    }

    /**
     * Build one capacity meter. `supported` is false only when the device
     * explicitly rejected the key — that is how "this unit has no face engine"
     * reaches the UI.
     *
     * @param  array<string, mixed>  $options
     * @return array{label: string, used: int|null, max: int|null, percent: float|null, supported: bool, known: bool}
     */
    private function meter(array $options, string $label, string $countKey, string $maxKey): array
    {
        $supported = ! (($options[$countKey]['unsupported'] ?? false) || ($options[$maxKey]['unsupported'] ?? false));

        $used = $this->intValue($options, $countKey);
        $max = $this->intValue($options, $maxKey);

        $percent = null;

        if ($used !== null && $max !== null && $max > 0) {
            $percent = round(min(100, ($used / $max) * 100), 1);
        }

        return [
            'label' => $label,
            'used' => $used,
            'max' => $max,
            'percent' => $percent,
            'supported' => $supported,
            'known' => $used !== null || $max !== null,
        ];
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
