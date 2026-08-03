<?php

namespace App\Models\HRM;

use App\Services\Biometric\DeviceCapabilityService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BiometricDeviceCommand extends Model
{
    use HasFactory;

    /**
     * Command has been queued but not yet handed to the device.
     */
    public const STATUS_PENDING = 'pending';

    /**
     * Command was written into a /iclock/getrequest response; the device has it.
     */
    public const STATUS_SENT = 'sent';

    /**
     * Device acked with Return=0.
     */
    public const STATUS_EXECUTED = 'executed';

    /**
     * Device acked with a genuine error (syntax, file, generic failure).
     */
    public const STATUS_FAILED = 'failed';

    /**
     * Device acked with -1004 (or -1): the model simply cannot do this.
     * Deliberately NOT folded into `failed` — "this unit has no face engine"
     * is a permanent capability fact we want to surface and cache, whereas
     * `failed` implies something worth retrying.
     */
    public const STATUS_UNSUPPORTED = 'unsupported';

    /**
     * Every status the column may hold. Kept here so the migration that widens
     * the MySQL ENUM and any UI filter have one authoritative list.
     */
    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_SENT,
        self::STATUS_EXECUTED,
        self::STATUS_FAILED,
        self::STATUS_UNSUPPORTED,
    ];

    /**
     * ADMS acknowledgement return codes (`ID=<id>&Return=<code>&CMD=<cmd>`).
     *
     * Source: ZKTeco PUSH SDK protocol notes, consolidated in
     * docs/zkteco-adms-capability-matrix.md §4. Anything not listed here is
     * treated as a generic failure.
     *
     * @var array<string, array{label: string, ok: bool, unsupported: bool}>
     */
    public const RETURN_CODES = [
        '0' => ['label' => 'Success', 'ok' => true, 'unsupported' => false],
        '-1' => ['label' => 'Unsupported or no data', 'ok' => false, 'unsupported' => true],
        '-2' => ['label' => 'File error', 'ok' => false, 'unsupported' => false],
        '-1002' => ['label' => 'Syntax error', 'ok' => false, 'unsupported' => false],
        '-1004' => ['label' => 'Not supported on this model', 'ok' => false, 'unsupported' => true],
    ];

    protected $fillable = [
        'biometric_device_id',
        'command_type',
        'payload',
        'status',
        'retry_count',
        'return_code',
        'error_message',
        'sent_at',
        'executed_at',
        'scheduled_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'sent_at' => 'datetime',
        'executed_at' => 'datetime',
        'scheduled_at' => 'datetime',
    ];

    /**
     * Relationship with the device
     */
    public function device()
    {
        return $this->belongsTo(BiometricDevice::class, 'biometric_device_id');
    }

    /**
     * Convert command to ADMS protocol string format
     * Format: C:ID:COMMAND
     */
    public function toAdmsString(): string
    {
        $command = "C:{$this->id}:";
        $payload = $this->payload ?? [];

        switch ($this->command_type) {
            case 'REBOOT':
                $command .= 'REBOOT';
                break;

            case 'SET_TIME':
                $timestamp = isset($payload['time']) ? strtotime($payload['time']) : time();
                $command .= "SET OPTIONS DateTime={$timestamp}";
                break;

            case 'ADD_USER':
            case 'UPDATE_USER':
                $command .= 'DATA UPDATE USERINFO';
                $command .= ' PIN='.($payload['pin'] ?? '');
                $command .= ' Name='.($payload['name'] ?? '');
                if (isset($payload['card'])) {
                    $command .= ' Card='.$payload['card'];
                }
                if (isset($payload['privilege'])) {
                    $command .= ' Pri='.$payload['privilege'];
                }
                break;

            case 'DELETE_USER':
                $command .= 'DATA DELETE USERINFO PIN='.($payload['pin'] ?? '');
                break;

            case 'CLEAR_LOG':
                $command .= 'CLEAR LOG';
                break;

            case 'CLEAR_DATA':
                $command .= 'CLEAR DATA';
                break;

            case 'GET_USERINFO':
            case 'QUERY_USERINFO':
                // `GET_USERINFO` used to emit the bare string `GET USERINFO`, which appears
                // in no reference ADMS implementation; at least one notes devices actively
                // reject the shorthand verbs. The documented form for pulling the roster is
                // `DATA QUERY USERINFO`. This command type has never been exposed in the UI,
                // so the old string was never exercised against hardware — this is a
                // correction to something that could not have worked, not a behaviour change
                // anyone could have depended on. The enum value is kept because rows may
                // already exist in biometric_device_commands with command_type=GET_USERINFO.
                $command .= 'DATA QUERY USERINFO';
                $pin = $payload['pin'] ?? null;
                if ($pin !== null && $pin !== '') {
                    $command .= ' PIN='.$pin;
                }
                break;

            case 'INFO':
                $command .= 'INFO';
                break;

            case 'CHECK':
                $command .= 'CHECK';
                break;

            case 'GET_OPTION':
                $command .= 'GET OPTION FROM '.implode(',', $this->optionKeysFromPayload($payload));
                break;

            case 'SET_OPTION':
                // One key per command, deliberately not batched. A batched
                // `SET OPTION a=1,b=2` comes back as a single Return code, so one
                // -1004 from one unsupported key tells us nothing about which key the
                // device rejected — and -1004 is precisely the per-model capability
                // signal we are trying to record. Commands are a cheap queued row, so
                // we buy unambiguous attribution for effectively nothing.
                $key = trim((string) ($payload['key'] ?? ''));
                if ($key === '') {
                    $command .= 'UNKNOWN';
                    break;
                }
                $command .= 'SET OPTION '.$key.'='.($payload['value'] ?? '');
                break;

            case 'UPDATE_FINGERTMP':
                // Write a fingerprint template back to a device — the missing half of
                // biometric roaming (docs/zkteco-adms-capability-matrix.md §2, marked
                // [D]: documented across independent implementations, NOT yet verified
                // against our own hardware). Until an MB460 has acked one of these with
                // Return=0, treat a -1002/-1004 here as "the string is wrong or the model
                // cannot do it", not as a device fault.
                //
                // Field order follows the documented form. Size is the byte length of the
                // template as sent, and Valid=1 marks the finger as enrolled/usable
                // (0 would register it as a duress finger on models that support that).
                // Some implementations tab-separate these; we use single spaces to match
                // `DATA UPDATE USERINFO` above, which our hardware demonstrably accepts.
                //
                // TMP must be the last field: it is the only value that can be long, so
                // anything a device truncates falls off the end of the payload rather
                // than corrupting a field the parser needs.
                $tmp = preg_replace('/\s+/', '', (string) ($payload['template'] ?? ''));
                $command .= 'DATA UPDATE FINGERTMP';
                $command .= ' PIN='.($payload['pin'] ?? '');
                // FID is the finger index (0-9). biometric_templates.finger_index is
                // nullable and is never populated on capture, so the caller passes 0
                // when it is unknown — see TemplateRoamingService::FALLBACK_FINGER_INDEX.
                $command .= ' FID='.($payload['fid'] ?? 0);
                $command .= ' Size='.($payload['size'] ?? strlen($tmp));
                $command .= ' Valid='.($payload['valid'] ?? 1);
                $command .= ' TMP='.$tmp;
                break;

            case 'DELETE_FINGERTMP':
                // Matrix §2 marks this `[?]` — single-source. Lower confidence than the
                // update verb above; expect -1004 on some models.
                $command .= 'DATA DELETE FINGERTMP PIN='.($payload['pin'] ?? '');
                // FID is deliberately omitted when absent rather than sent as an empty
                // or zero value: `FID=` is a syntax error and `FID=0` would silently
                // delete only the first finger when the caller asked for all of them.
                $fid = $payload['fid'] ?? null;
                if ($fid !== null && $fid !== '') {
                    $command .= ' FID='.$fid;
                }
                break;

            case 'CHECK_ATTLOG':
                $startTime = $payload['start_time'] ?? '2000-01-01 00:00:00';
                $endTime = $payload['end_time'] ?? now()->addDay()->format('Y-m-d H:i:s');
                $command .= "DATA QUERY ATTLOG StartTime={$startTime}\tEndTime={$endTime}";
                break;

            default:
                $command .= 'UNKNOWN';
                break;
        }

        return $command;
    }

    /**
     * Normalise the option keys carried by a GET_OPTION payload.
     *
     * Accepts `keys` as an array or as an already-comma-joined string, because
     * both shapes turn up depending on whether the command was queued from JSON
     * or from a form post. Falls back to the full probe set so a GET_OPTION with
     * an empty payload is still a useful command rather than a malformed one.
     *
     * @param  array<string, mixed>  $payload
     * @return array<int, string>
     */
    protected function optionKeysFromPayload(array $payload): array
    {
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

        return $keys !== [] ? $keys : DeviceCapabilityService::CAPABILITY_KEYS;
    }

    /**
     * Decode an ADMS acknowledgement return code into something the rest of the
     * application can branch on, instead of collapsing every non-zero code into
     * a generic failure.
     *
     * `unsupported` is the load-bearing flag: -1004 ("not supported on this
     * model") and -1 ("unsupported or no data") are how a device tells us it
     * lacks a face engine, a lock relay, or a given option key. That is a
     * capability fact to cache, not an error to retry.
     *
     * @return array{code: string, ok: bool, label: string, unsupported: bool, known: bool}
     */
    public static function decodeReturnCode(?string $returnCode): array
    {
        $code = trim((string) $returnCode);

        if (isset(self::RETURN_CODES[$code])) {
            return [
                'code' => $code,
                'ok' => self::RETURN_CODES[$code]['ok'],
                'label' => self::RETURN_CODES[$code]['label'],
                'unsupported' => self::RETURN_CODES[$code]['unsupported'],
                'known' => true,
            ];
        }

        // Unknown codes are a failure, never a success: a device that answers
        // something we do not recognise has not demonstrably done the work.
        return [
            'code' => $code,
            'ok' => false,
            'label' => $code === '' ? 'No return code' : "Unknown device return code ({$code})",
            'unsupported' => false,
            'known' => false,
        ];
    }

    /**
     * Decode this command's own recorded return code.
     *
     * @return array{code: string, ok: bool, label: string, unsupported: bool, known: bool}
     */
    public function returnCodeMeaning(): array
    {
        return self::decodeReturnCode($this->return_code);
    }

    public function isUnsupported(): bool
    {
        return $this->status === self::STATUS_UNSUPPORTED;
    }

    /**
     * Mark command as sent
     */
    public function markAsSent(): void
    {
        $this->update([
            'status' => 'sent',
            'sent_at' => now(),
            'retry_count' => $this->retry_count + 1,
        ]);
    }

    /**
     * Mark command as executed
     */
    public function markAsExecuted(string $returnCode = '0'): void
    {
        $meaning = self::decodeReturnCode($returnCode);

        if ($meaning['ok']) {
            $status = self::STATUS_EXECUTED;
        } elseif ($meaning['unsupported']) {
            $status = self::STATUS_UNSUPPORTED;
        } else {
            $status = self::STATUS_FAILED;
        }

        $this->update([
            'status' => $status,
            'return_code' => $returnCode,
            // Surface the decoded meaning so command history reads
            // "Not supported on this model" rather than a bare -1004.
            'error_message' => $meaning['ok'] ? null : $meaning['label'],
            'executed_at' => now(),
        ]);
    }

    /**
     * Mark command as failed
     */
    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
            'executed_at' => now(),
        ]);
    }

    /**
     * Scope for pending commands
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for scheduled commands that are due
     */
    public function scopeDue($query)
    {
        return $query->where('status', 'pending')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now());
    }

    /**
     * Scope for a specific device
     */
    public function scopeForDevice($query, $deviceId)
    {
        return $query->where('biometric_device_id', $deviceId);
    }

    /**
     * Scope for oldest commands first
     */
    public function scopeOldest($query)
    {
        return $query->orderBy('created_at', 'asc');
    }

    /**
     * Check if command is scheduled
     */
    public function isScheduled(): bool
    {
        return ! is_null($this->scheduled_at);
    }

    /**
     * Check if command is due for execution
     */
    public function isDue(): bool
    {
        return $this->isScheduled() && $this->scheduled_at->lte(now());
    }
}
