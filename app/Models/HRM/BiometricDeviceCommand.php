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

    /**
     * Commands that destroy data on the device, mapped to what each one destroys.
     *
     * This is the command-layer twin of `DeviceCapabilityService::SETTINGS_CATALOGUE`'s
     * per-key `dangerous` flag: the settings layer gates a strand-the-device option
     * behind `confirm_dangerous`, and a wipe deserves the same treatment. Keeping the
     * list here rather than in a controller means the queuing layer, the UI catalogue
     * endpoint and command history all read one definition.
     *
     * The distinction being drawn is *irreversible loss of data on the unit*, not
     * "alarming". `REBOOT` is disruptive but destroys nothing and is deliberately
     * absent; `CLEAR_LOG` is present because punches the device has not yet pushed
     * are gone the moment it runs.
     *
     * @var array<string, string>
     */
    public const DESTRUCTIVE_COMMAND_TYPES = [
        'CLEAR_DATA' => 'Erases all users and all biometric templates on the device.',
        'CLEAR_LOG' => 'Erases attendance logs on the device, including punches it has not yet pushed to this server.',
        'CLEAR_PHOTO' => 'Erases attendance capture photos stored on the device.',
        // Called out at more length than the rest because this application cannot
        // undo it. Fingerprints we hold can be pushed back with DATA UPDATE
        // FINGERTMP; face templates cannot be written back at all (see
        // TemplateRoamingService::FACE_REASON), so wiping biometrics from a unit
        // that holds an enrolled face destroys that enrolment permanently — the
        // person must physically re-enrol.
        'CLEAR_BIODATA' => 'Erases all biometric templates on the device. Fingerprints can be restored from this server; face enrolments CANNOT — they are lost permanently.',
        'DELETE_USER' => 'Removes a user, and that user\'s templates, from the device.',
        'DELETE_FINGERTMP' => 'Removes stored fingerprint template(s) for one PIN from the device.',
    ];

    /**
     * Commands whose emitted string has never been acked by any device of ours,
     * mapped to the reason confidence is limited.
     *
     * SIX commands are hardware-verified in production, all on the real MB460
     * (`AF6P231260266`) and all `Return=0`: `INFO`, `GET_OPTION`, `SET_OPTION`,
     * `QUERY_USERINFO`, `CHECK_ATTLOG`, `CHECK`. Everything listed here is
     * inference from documentation, so a `-1002` (syntax) or `-1004`
     * (unsupported) ack on one of these means "our string, or this model" — not a
     * device fault. Surfacing that distinction is the difference between an admin
     * filing a hardware ticket and an admin reporting a protocol gap.
     *
     * This list is the live-probe worklist. Delete an entry the day a device acks
     * it with `Return=0`.
     *
     * @var array<string, string>
     */
    public const HARDWARE_UNVERIFIED_COMMAND_TYPES = [
        'UPDATE_FINGERTMP' => 'Documented across independent implementations, never sent to one of our devices. Tab-separated on the evidence of the one implementation whose source states the string, but no device has confirmed the separator.',
        'DELETE_FINGERTMP' => 'Composed from the documented DATA/DELETE grammar and an attested FINGERTMP PIN/FID addressing; single-source overall.',
        // The capture half of roaming, and the highest-value entry on this worklist:
        // until a device answers one of these, `biometric_templates` stays empty and
        // there is nothing for UPDATE_FINGERTMP to restore. Note that Return=0 alone
        // does NOT verify it — the templates arrive as a separate push, so this entry
        // is only deleted once rows actually land.
        'QUERY_FINGERTMP' => 'Documented verbatim in a ZKTeco distributor command guide and implemented as a first-class endpoint by one reference package, but never sent to one of our devices. Tab-separated between PIN and FID by analogy with the hardware-verified DATA QUERY ATTLOG; the guide itself renders separators inconsistently.',
        'CLEAR_PHOTO' => 'Single-source. Follows the established CLEAR <NOUN> grammar but has not been acked.',
        'CLEAR_BIODATA' => 'Single-source. Follows the established CLEAR <NOUN> grammar but has not been acked.',
        'GET_USERINFO' => 'Legacy alias now emitting DATA QUERY USERINFO; the corrected string is unproven on the alias path.',
        // Long-standing in this codebase, which is not the same as verified. The
        // string is also separator-suspect: it is space-separated while every
        // reference implementation tab-separates USERINFO, so a Name containing a
        // space is the case that would expose it. Being long-standing is why it
        // has not been changed; it is not why it should read as settled.
        'ADD_USER' => 'Space-separated while reference implementations tab-separate DATA UPDATE USERINFO. Long-standing here but never acked Return=0, and unverified in both string and separator.',
        'UPDATE_USER' => 'Same emitted string, and the same unverified separator, as ADD_USER.',
        'REBOOT' => 'Long-standing in this codebase but not part of the verified six.',
    ];

    /**
     * Commands deliberately NOT implemented, and why. Read this before adding one.
     *
     * - `Shell <cmd>` — arbitrary OS command execution on the terminal. A reference
     *   implementation does expose it, which makes it real rather than theoretical,
     *   and that is exactly why it stays out: it converts any flaw in the command
     *   queue into remote code execution on a device sitting on the office LAN.
     *   Matrix §2 says do not expose it. There is no payload validation that makes
     *   this safe, so there is no "careful" version to add later.
     *
     * - `AC_UNLOCK` — door release, access-control models only. Three reasons to
     *   leave it out, any one sufficient. (1) Our unit is an attendance terminal
     *   with no lock relay, so the command is dead surface that can only ever return
     *   -1004. (2) ADMS delivery is a FIFO poll queue with 30-120 s latency and no
     *   cancel, so a door release fires up to two minutes after the click, long
     *   after whoever pressed it has stopped watching the door — a physical-security
     *   hazard, not a convenience. (3) Unlocking a door is an action that needs its
     *   own authorisation model and audit trail, which is a feature, not a row in a
     *   generic command queue.
     *
     * - `PutFile` / `GetFile` — file and firmware transfer over a protocol we have
     *   no way to test. The downside of getting a firmware write wrong is a bricked
     *   terminal recoverable only by physical service, and there is no offsetting
     *   need: nothing in this application wants to move files onto a device.
     *
     * - `LOG` — appears in one reference implementation, so the verb is probably
     *   real, but it buys nothing. Operation and error logs already arrive
     *   unprompted as `table=OPERLOG` / `table=errorlog` pushes, which this server
     *   consumes. A command whose reply format is undocumented and whose content we
     *   already receive is surface area without a use case.
     */
    public const DELIBERATELY_UNIMPLEMENTED = ['Shell', 'AC_UNLOCK', 'PutFile', 'GetFile', 'LOG'];

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
        'biometric_device_id' => 'integer',
        // markAsSent() does `$this->retry_count + 1`. That happened to work on a
        // MySQL string because PHP coerces, but the model should not depend on
        // the coercion — and the value is emitted to the admin UI as JSON.
        'retry_count' => 'integer',
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
                // NOTE (separator): this one is space-separated while UPDATE_FINGERTMP
                // below is tab-separated, and that inconsistency is deliberate rather
                // than an oversight. Reference implementations tab-separate USERINFO too
                // ('DATA UPDATE USERINFO PIN=%s'."\t".'Name=%s'."\t"…), so this string is
                // probably wrong — but it is long-standing, it is not on the
                // hardware-verified list either way, and changing a command that may be
                // working in production is a bigger risk than leaving it. FINGERTMP has
                // never been sent to anything, so it costs nothing to start it on the
                // better-evidenced form. Probe USERINFO on real hardware before touching
                // it: a Name containing a space is the case that would expose the bug.
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
                //
                // SEPARATOR: TAB, not space. This is the one detail worth being exact
                // about, because getting it wrong fails *silently*. If the device's
                // parser splits strictly on \t, then a space-separated
                // `PIN=1024 FID=3 Size=16 Valid=1 TMP=…` arrives as a single field whose
                // value is the whole remainder: PIN still reads 1024 (leading digits),
                // FID falls back to 0, Size to 0, and TMP is empty — a Return=0 ack for
                // an enrolment that never landed. Three lines of evidence all point the
                // same way and none point at spaces:
                //   1. The only reference implementation whose source states this string
                //      literally builds it tab-separated:
                //      'DATA UPDATE FINGERTMP PIN=%s'."\t".'FID=%s'."\t".'Size=%d'
                //      ."\t".'Valid=%s'."\t".'TMP=%s'  (shadow046/zkteco-adms).
                //   2. A ZKTeco distributor's PUSH command guide writes the sibling
                //      payload command as `DATA UPDATE BIOPHOTO PIN=1\tContent=…`.
                //   3. Our OWN hardware-verified multi-field command is tab-separated:
                //      CHECK_ATTLOG emits `DATA QUERY ATTLOG StartTime=…\tEndTime=…` and
                //      the MB460 acks it Return=0. That is the only separator evidence
                //      we have from a real device, and it says tab.
                // `DATA UPDATE USERINFO` above still uses spaces; it is untouched here
                // because it may be working in production and is not part of this
                // change's remit — see the note on that case.
                //
                // TMP must be the last field: it is the only value that can be long, so
                // anything a device truncates falls off the end of the payload rather
                // than corrupting a field the parser needs.
                $tmp = preg_replace('/\s+/', '', (string) ($payload['template'] ?? ''));
                $command .= 'DATA UPDATE FINGERTMP';
                $command .= ' PIN='.($payload['pin'] ?? '');
                // FID is the finger index (0-9), and it is now the REAL one. Capture
                // parses FID out of the template push and `biometric_templates` keys
                // uniquely on the finger slot, so a person with two enrolled fingers
                // produces two of these commands with two different FIDs. The 0 here
                // is only the last-resort default for a payload that carries none —
                // see TemplateRoamingService::FALLBACK_FINGER_INDEX. It used to be
                // what EVERY restore sent, which is how a two-finger enrolment came
                // back as one finger.
                $command .= "\tFID=".($payload['fid'] ?? 0);
                $command .= "\tSize=".($payload['size'] ?? strlen($tmp));
                $command .= "\tValid=".($payload['valid'] ?? 1);
                $command .= "\tTMP=".$tmp;
                break;

            case 'DELETE_FINGERTMP':
                // UNVERIFIED AGAINST HARDWARE. Matrix §2 marks `DATA DELETE FINGERTMP`
                // `[?]` — single-source — and no device of ours has ever acked one.
                // Also listed in HARDWARE_UNVERIFIED_COMMAND_TYPES; keep it there until
                // a Return=0 arrives.
                //
                // What IS defensible about the string, piece by piece:
                //   - `DATA DELETE <NOUN>` is the established grammar and is proven here
                //     by `DATA DELETE USERINFO PIN=` (implemented, matrix §2).
                //   - `FINGERTMP` as the noun, addressed by PIN and FID, is attested by
                //     the reference implementation that builds both
                //     `DATA QUERY FINGERTMP PIN=%s\tFID=%s` and
                //     `DATA UPDATE FINGERTMP PIN=%s\tFID=%s\t…` (shadow046/zkteco-adms).
                //     The DELETE verb applied to that same noun/addressing is the
                //     inference; the noun and its fields are not.
                //   - Tab separator, for exactly the reasons spelled out on
                //     UPDATE_FINGERTMP above. A delete that mis-parses its FID deletes
                //     the wrong finger, so the same care applies.
                //
                // Expect -1004 on some models. That is a capability answer, not a bug.
                $command .= 'DATA DELETE FINGERTMP PIN='.($payload['pin'] ?? '');
                // FID is deliberately omitted when absent rather than sent as an empty
                // or zero value: `FID=` is a syntax error and `FID=0` would silently
                // delete only the first finger when the caller asked for all of them.
                $fid = $payload['fid'] ?? null;
                if ($fid !== null && $fid !== '') {
                    $command .= "\tFID=".$fid;
                }
                break;

            case 'CLEAR_PHOTO':
                // Targeted wipe: attendance capture photos only, leaving users,
                // templates and punches alone. Strictly safer than the `CLEAR DATA`
                // we already expose, which takes everything.
                //
                // UNVERIFIED AGAINST HARDWARE, `[?]` in matrix §2. The confidence here
                // is grammatical rather than empirical: `CLEAR <NOUN>` is proven on this
                // codebase by `CLEAR LOG` and `CLEAR DATA`, and PHOTO is the noun the
                // device itself uses for this data (`table=ATTPHOTO` pushes, matrix §1).
                // A single-word command has no field-separator risk, which is why these
                // two are worth adding while a multi-field face write is not.
                //
                // Flagged in DESTRUCTIVE_COMMAND_TYPES — this erases data on the unit.
                $command .= 'CLEAR PHOTO';
                break;

            case 'CLEAR_BIODATA':
                // Targeted wipe: biometric templates only, leaving the user roster and
                // attendance logs intact. Same `[?]`/grammatical confidence as
                // CLEAR PHOTO above; BIODATA is the device's own noun for the unified
                // biometric table (matrix §1).
                //
                // Flagged in DESTRUCTIVE_COMMAND_TYPES, and it is the most dangerous
                // entry in that list: fingerprints can be pushed back with
                // DATA UPDATE FINGERTMP, but face enrolments cannot be written back at
                // all (TemplateRoamingService::FACE_REASON), so this permanently
                // destroys every face on the unit. The production MB460 holds one.
                $command .= 'CLEAR BIODATA';
                break;

            case 'QUERY_FINGERTMP':
                // Ask the device to send us its fingerprint templates — the CAPTURE
                // half of biometric roaming, and the half that has never fired.
                //
                // Why this command exists. Template capture was entirely passive: we
                // waited for an unprompted `table=templatev10` push. The production
                // MB460 (AF6P231260266) holds 26 fingerprints across 13 employees and
                // has logged 13 "Enroll FP" operations in OPERLOG, and it has never
                // once pushed a template. `biometric_templates` has always been empty,
                // so the restore path (`DATA UPDATE FINGERTMP`) had nothing to restore
                // and the backup everyone believed in did not exist. This command is
                // the pull that does not depend on the device volunteering anything.
                //
                // UNVERIFIED AGAINST HARDWARE. Listed in
                // HARDWARE_UNVERIFIED_COMMAND_TYPES; delete that entry the day an
                // MB460 acks one Return=0. What IS defensible, piece by piece:
                //   1. A ZKTeco distributor's TA Push SDK command guide documents the
                //      string verbatim, as `C:12345:DATA QUERY FINGERTMP PIN=1 FID=1`,
                //      described as retrieving fingerprint data from the device.
                //   2. shadow046/zkteco-adms — the same package this codebase already
                //      cites for the tab-separated `DATA UPDATE FINGERTMP` form —
                //      ships a first-class `commands/fingertmp-query` endpoint
                //      alongside its `fingertmp-update` one.
                //   3. `DATA QUERY <NOUN>` is hardware-verified on our own MB460
                //      TWICE: `DATA QUERY USERINFO` and `DATA QUERY ATTLOG` both come
                //      back Return=0. FINGERTMP is the device's own noun, attested by
                //      the UPDATE/DELETE siblings.
                //
                // SEPARATOR: TAB between PIN and FID, one space after the verb. The
                // distributor guide above renders it with a space, but that same guide
                // writes the sibling as `DATA UPDATE BIOPHOTO PIN=1\tContent=…` and
                // states outright that `\t` in its listings means a tab — so its
                // separators are rendered inconsistently and it is not evidence for
                // space. The evidence that IS from hardware points the other way:
                // `DATA QUERY ATTLOG StartTime=…\tEndTime=…` is the only multi-field
                // DATA QUERY any device of ours has acked, it is the closest possible
                // sibling of this command, and it is tab-separated. Same reasoning as
                // UPDATE_FINGERTMP, and the same silent failure mode if it is wrong.
                //
                // ADDRESSING. All three forms are emitted from one case:
                //   - no PIN  → `DATA QUERY FINGERTMP`, i.e. every template on the
                //     unit. This is the form that fills an empty table in one shot,
                //     and it is the direct analogue of the bare `DATA QUERY USERINFO`
                //     that is hardware-verified here as a full roster dump. It is the
                //     default precisely because the problem being solved is "we hold
                //     nothing at all".
                //   - PIN only → every finger for that person.
                //   - PIN + FID → one finger slot.
                // FID is only emitted alongside a PIN: a finger index with no person
                // to address addresses nothing, so a payload carrying FID alone
                // degrades to the full dump rather than emitting a command whose
                // meaning we would be inventing.
                //
                // THE RESULT ARRIVES AS A PUSH, NOT IN THE ACK — same as
                // QUERY_USERINFO (matrix §1/§2). A Return=0 here means "query
                // accepted", NOT "templates received". What proves this worked is
                // rows appearing in `biometric_templates`, via
                // BiometricProcessingService::processTemplateUpload().
                $command .= 'DATA QUERY FINGERTMP';
                $pin = $payload['pin'] ?? null;
                if ($pin !== null && $pin !== '') {
                    $command .= ' PIN='.$pin;

                    $fid = $payload['fid'] ?? null;
                    if ($fid !== null && $fid !== '') {
                        $command .= "\tFID=".$fid;
                    }
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
     * Does this command type irreversibly destroy data on the device?
     *
     * The three catalogues above (destructive, hardware-unverified, deliberately
     * unimplemented) are only worth having if something can read them, so each
     * gets a static lookup plus an instance convenience. The queuing layer, the
     * UI catalogue endpoint and command history then all answer "is this
     * dangerous?" from the same definition instead of each keeping a list.
     */
    public static function isDestructiveType(?string $commandType): bool
    {
        return isset(self::DESTRUCTIVE_COMMAND_TYPES[(string) $commandType]);
    }

    /**
     * What a destructive command destroys, phrased for an admin who is about to
     * confirm it. Null for anything non-destructive.
     */
    public static function destructiveWarningFor(?string $commandType): ?string
    {
        return self::DESTRUCTIVE_COMMAND_TYPES[(string) $commandType] ?? null;
    }

    public function isDestructive(): bool
    {
        return self::isDestructiveType($this->command_type);
    }

    public function destructiveWarning(): ?string
    {
        return self::destructiveWarningFor($this->command_type);
    }

    /**
     * Has any device of ours ever acked this command type with Return=0?
     *
     * False here does NOT mean the command is broken — it means a -1002 or -1004
     * ack is ambiguous between "this model cannot" and "our string is wrong", and
     * the UI should say so rather than blaming the hardware.
     */
    public static function isHardwareUnverifiedType(?string $commandType): bool
    {
        return isset(self::HARDWARE_UNVERIFIED_COMMAND_TYPES[(string) $commandType]);
    }

    public static function hardwareUnverifiedReasonFor(?string $commandType): ?string
    {
        return self::HARDWARE_UNVERIFIED_COMMAND_TYPES[(string) $commandType] ?? null;
    }

    public function isHardwareUnverified(): bool
    {
        return self::isHardwareUnverifiedType($this->command_type);
    }

    public function hardwareUnverifiedReason(): ?string
    {
        return self::hardwareUnverifiedReasonFor($this->command_type);
    }

    /**
     * Commands that must never be queued, whatever the caller asks for.
     *
     * `Shell` is the one that matters: it is arbitrary OS command execution on a
     * terminal sitting on the office LAN, and no payload validation makes that
     * safe. The rest are refused for the reasons in DELIBERATELY_UNIMPLEMENTED.
     * Exposed as a check so the queuing layer can reject at the door rather than
     * storing a row that quietly emits `UNKNOWN` later.
     */
    public static function isDeliberatelyUnimplementedType(?string $commandType): bool
    {
        return in_array((string) $commandType, self::DELIBERATELY_UNIMPLEMENTED, true);
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
