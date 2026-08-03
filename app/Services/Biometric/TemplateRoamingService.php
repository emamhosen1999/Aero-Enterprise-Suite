<?php

namespace App\Services\Biometric;

use App\Models\HRM\BiometricDevice;
use App\Models\HRM\BiometricDeviceCommand;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Biometric roaming — the write-back half.
 *
 * `BiometricProcessingService::processTemplateUpload()` captures fingerprint
 * (`table=templatev10`) and face (`table=facetmpv10`) templates into
 * `biometric_templates`. Nothing has ever been able to push them back, so a dead
 * or replaced unit means every enrolled person walks to the device and re-enrols
 * by hand even though we are holding their template.
 *
 * This service closes that loop by queueing `DATA UPDATE FINGERTMP` commands
 * (docs/zkteco-adms-capability-matrix.md §2). That command is marked `[D]` in the
 * matrix — documented consistently across independent implementations, but not
 * yet demonstrated on our own hardware. Until an MB460 acks one with `Return=0`,
 * a restore is a *plausible* write, not a proven one. The UI wording and anyone
 * reading command history should treat -1002/-1004 on these commands as
 * "our string or this model", not as a device fault.
 *
 * Face templates are deliberately NOT restorable here — see FACE_REASON.
 */
class TemplateRoamingService
{
    /**
     * Maximum commands a single restore call may queue.
     *
     * This is not a database concern, it is a queue-drain concern.
     * `/iclock/getrequest` hands the device exactly ONE command per poll
     * (BiometricWebhookController::getRequest -> fetchNextPendingCommand), the
     * queue is strictly FIFO on created_at, and ZKTeco units poll every 30-120 s.
     * So N queued templates is N polls: 200 commands is roughly 1.5-7 hours during
     * which *nothing else* — no REBOOT, no SET_TIME, no CHECK_ATTLOG — can reach
     * that device.
     *
     * 200 is chosen to sit comfortably above the real fleet (the production MB460
     * holds 26 fingerprints across 13 employees, and a 10-finger enrolment for 20
     * people is still only 200) while making "restore all 3000 users" impossible
     * to trigger by accident. A larger job is a legitimate need, but it belongs in
     * a paced background job, not in one click that silently wedges a device for
     * a day.
     */
    public const MAX_COMMANDS_PER_RESTORE = 200;

    /**
     * Refuse to push a template this large (bytes, after whitespace is stripped).
     *
     * ZKTeco does not publish a command-length limit and our own transport imposes
     * none — the ADMS string is the entire HTTP response body of a getrequest, so
     * PHP and the device's own command buffer are the only ceilings. Every
     * reference implementation sends a fingerprint template as ONE
     * `DATA UPDATE FINGERTMP`; none chunk it, and no chunking/continuation syntax
     * is documented anywhere. So: no chunking here either.
     *
     * A base64 ZK v10 fingerprint template runs roughly 600 B - 2.5 KB. 8 KB is
     * generous headroom for that, while still refusing a blob that is almost
     * certainly a face/BIODATA payload misfiled as a fingerprint — which the
     * device would silently truncate into a corrupt enrolment.
     *
     * ASSUMPTION, unverified on hardware: one command carries a whole template.
     */
    public const MAX_TEMPLATE_BYTES = 8192;

    /**
     * FID sent when we do not know which finger a template belongs to.
     *
     * SCHEMA GAP: `biometric_templates.finger_index` exists and is nullable, but
     * `processTemplateUpload()` never writes it — the capture regex does not even
     * look for FID, and its updateOrInsert key is (device_user_id, device,
     * template_type), so a second finger overwrites the first rather than being
     * stored alongside it. Every row we hold today therefore has finger_index NULL
     * and at most one finger per person per device.
     *
     * Consequence: restores land on finger slot 0. Anyone who enrolled index and
     * thumb gets one finger back and must re-enrol the other. Fixing this properly
     * needs a capture-side change plus a migration widening the uniqueness key to
     * include finger_index — neither of which this service owns.
     */
    public const FALLBACK_FINGER_INDEX = 0;

    /**
     * Why face templates are captured but never restored.
     *
     * The matrix documents `DATA UPDATE FINGERTMP` `[D]` for fingerprints and
     * nothing at all for faces. `table=facetmpv10` and `table=BIODATA` are
     * device->server directions only; `CLEAR BIODATA` is `[?]`. There is no
     * documented server->device face-write verb — inventing one
     * (`DATA UPDATE FACE`, `DATA UPDATE BIODATA Type=2`, …) would produce a
     * command that queues cleanly, reports "queued" in the UI, and then either
     * returns -1002 or is silently ignored by the unit. That failure mode is worse
     * than no feature: an admin would decommission the old device believing the
     * faces were saved.
     *
     * So face rows are listed (so the gap is visible) and skipped on restore with
     * this reason. Revisit only with a device ack proving the syntax.
     */
    public const FACE_REASON = 'face_write_back_not_documented';

    /**
     * Read model for the roaming UI.
     *
     * `template_data` is never selected. It is a multi-kilobyte base64 blob of
     * somebody's biometric; it has no business crossing an HTTP boundary into a
     * browser, and shipping it would turn an admin screen into a template
     * exfiltration endpoint. Sizes and identifiers are enough to decide what to
     * restore.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function listTemplates(?int $userId = null, ?int $deviceId = null): Collection
    {
        $query = DB::table('biometric_templates as t')
            ->leftJoin('users as u', 'u.id', '=', 't.user_id')
            ->leftJoin('biometric_devices as d', 'd.id', '=', 't.biometric_device_id')
            ->select([
                't.id',
                't.user_id',
                'u.name as user_name',
                'u.employee_id',
                't.device_user_id',
                't.template_type',
                't.finger_index',
                't.template_size',
                't.template_version',
                't.biometric_device_id',
                'd.name as device_name',
                'd.serial_number as device_serial',
                't.created_at',
                't.updated_at',
            ]);

        if ($userId !== null) {
            $query->where('t.user_id', $userId);
        }

        if ($deviceId !== null) {
            $query->where('t.biometric_device_id', $deviceId);
        }

        return $query
            ->orderBy('t.user_id')
            ->orderBy('t.template_type')
            ->orderBy('t.finger_index')
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'user_id' => $row->user_id === null ? null : (int) $row->user_id,
                'user_name' => $row->user_name,
                'employee_id' => $row->employee_id,
                // The device-side PIN. This is what a restore command carries.
                'pin' => (string) $row->device_user_id,
                'template_type' => $row->template_type,
                'finger_index' => $row->finger_index === null ? null : (int) $row->finger_index,
                'template_size' => $row->template_size === null ? null : (int) $row->template_size,
                'template_version' => $row->template_version,
                'source_device_id' => $row->biometric_device_id === null ? null : (int) $row->biometric_device_id,
                'source_device_name' => $row->device_name,
                'source_device_serial' => $row->device_serial,
                'captured_at' => $row->created_at,
                'updated_at' => $row->updated_at,
                // Surfaced so the UI can grey out face rows with a reason rather
                // than offering a restore that cannot work.
                'restorable' => $row->template_type === 'fingerprint',
                'not_restorable_reason' => $row->template_type === 'fingerprint' ? null : self::FACE_REASON,
            ])
            ->values();
    }

    /**
     * Queue one `DATA UPDATE FINGERTMP` per stored fingerprint template.
     *
     * An empty `$userIds` means every user we hold a template for. Templates the
     * target device already has are skipped, as are face/palm templates and
     * anything that cannot be sent safely; every skip is counted under a reason so
     * the UI can explain a restore that queued fewer commands than expected.
     *
     * @param  array<int, int|string>  $userIds
     * @return array{queued: int, skipped: int, users: int, reasons: array<string, int>}
     *
     * @throws \InvalidArgumentException when the target cannot receive commands
     */
    public function restoreTemplatesToDevice(BiometricDevice $target, array $userIds = []): array
    {
        // Same guards, same order, same exception type as
        // BiometricProcessingService::initiateLogDownload(). A restore is a write
        // onto hardware, so it is refused loudly rather than queued into a device
        // that will never poll for it.
        if (! $target->is_active) {
            throw new \InvalidArgumentException('Device is inactive.');
        }

        if (! $target->isAdms()) {
            throw new \InvalidArgumentException('Template restore is only supported for ADMS devices.');
        }

        $reasons = [];
        $skipped = 0;
        $skip = function (string $reason) use (&$reasons, &$skipped) {
            $reasons[$reason] = ($reasons[$reason] ?? 0) + 1;
            $skipped++;
        };

        $userIds = array_values(array_filter(array_map('intval', $userIds)));

        $candidates = DB::table('biometric_templates')
            ->select([
                'id', 'user_id', 'biometric_device_id', 'device_user_id',
                'template_type', 'finger_index', 'template_data', 'template_size',
            ])
            ->when($userIds !== [], fn ($q) => $q->whereIn('user_id', $userIds))
            // Newest first so that when two source devices hold the same finger
            // for the same person, the freshest enrolment wins the de-duplication
            // below.
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get();

        // Everything the target already holds, keyed the same way we key the
        // commands we are about to queue. A template captured *from* this device
        // is in here too, which is exactly right: restoring it would be a no-op.
        $onTarget = DB::table('biometric_templates')
            ->where('biometric_device_id', $target->id)
            ->get(['device_user_id', 'template_type', 'finger_index'])
            ->map(fn ($row) => $this->slotKey(
                (string) $row->device_user_id,
                (string) $row->template_type,
                $row->finger_index
            ))
            ->flip();

        /** @var array<string, object> $selected */
        $selected = [];

        foreach ($candidates as $row) {
            if ($row->template_type !== 'fingerprint') {
                // Face and palm: captured, listed, never pushed. See FACE_REASON.
                $skip($row->template_type === 'face' ? self::FACE_REASON : 'template_type_not_restorable');

                continue;
            }

            $template = preg_replace('/\s+/', '', (string) $row->template_data);

            if ($template === '') {
                $skip('empty_template');

                continue;
            }

            if (strlen($template) > self::MAX_TEMPLATE_BYTES) {
                $skip('template_too_large');

                continue;
            }

            $key = $this->slotKey((string) $row->device_user_id, 'fingerprint', $row->finger_index);

            if ($onTarget->has($key)) {
                $skip('already_on_device');

                continue;
            }

            if (isset($selected[$key])) {
                // Same person, same finger slot, held by a second source device.
                // Pushing both would queue two commands that write the same slot.
                $skip('duplicate_template');

                continue;
            }

            $row->normalised_template = $template;
            $selected[$key] = $row;
        }

        // Deterministic queue order: by person, then finger slot. The dedupe pass
        // above ran newest-first, which is the wrong order to hand to a device.
        $queueable = array_values($selected);
        usort($queueable, function ($a, $b) {
            return [$a->user_id, $a->finger_index ?? self::FALLBACK_FINGER_INDEX, $a->id]
                <=> [$b->user_id, $b->finger_index ?? self::FALLBACK_FINGER_INDEX, $b->id];
        });

        if (count($queueable) > self::MAX_COMMANDS_PER_RESTORE) {
            foreach (array_slice($queueable, self::MAX_COMMANDS_PER_RESTORE) as $ignored) {
                $skip('cap_reached');
            }
            $queueable = array_slice($queueable, 0, self::MAX_COMMANDS_PER_RESTORE);
        }

        $queued = [];

        DB::transaction(function () use ($queueable, $target, &$queued) {
            foreach ($queueable as $row) {
                $fid = $row->finger_index ?? self::FALLBACK_FINGER_INDEX;
                $size = strlen($row->normalised_template);

                $command = BiometricDeviceCommand::create([
                    'biometric_device_id' => $target->id,
                    'command_type' => 'UPDATE_FINGERTMP',
                    'payload' => [
                        'pin' => (string) $row->device_user_id,
                        'fid' => (int) $fid,
                        'size' => $size,
                        'valid' => 1,
                        'template' => $row->normalised_template,
                        // Provenance, for the audit trail on the command row itself.
                        'template_id' => (int) $row->id,
                        'source_device_id' => (int) $row->biometric_device_id,
                    ],
                    'status' => BiometricDeviceCommand::STATUS_PENDING,
                ]);

                $queued[] = [
                    'command_id' => $command->id,
                    'template_id' => (int) $row->id,
                    'user_id' => (int) $row->user_id,
                    'pin' => (string) $row->device_user_id,
                    'fid' => (int) $fid,
                    'size' => $size,
                    'source_device_id' => (int) $row->biometric_device_id,
                ];
            }
        });

        // Writing biometric data onto hardware is an auditable act. One bounded
        // summary line (capped at MAX_COMMANDS_PER_RESTORE entries) records who
        // asked, which device receives it, and exactly which template landed in
        // which finger slot.
        Log::info('Biometric template restore queued', [
            'device_id' => $target->id,
            'device_serial' => $target->serial_number,
            'requested_by' => auth()->id(),
            'requested_user_ids' => $userIds,
            'queued' => count($queued),
            'skipped' => $skipped,
            'reasons' => $reasons,
            'commands' => $queued,
            // The command itself is [D], not [V]: queued is not delivered, and
            // delivered is not accepted. Only a Return=0 ack proves the restore.
            'confidence' => 'DATA UPDATE FINGERTMP is documented but unverified on our hardware',
        ]);

        return [
            'queued' => count($queued),
            'skipped' => $skipped,
            'users' => count(array_unique(array_column($queued, 'user_id'))),
            'reasons' => $reasons,
        ];
    }

    /**
     * Queue a `DATA DELETE FINGERTMP` for one PIN.
     *
     * A null `$fid` omits the field, which the documented form reads as "all
     * fingers for this PIN". That verb is `[?]` in the matrix — single-source —
     * so a -1004 ack is an expected outcome on some models, not a bug.
     *
     * Nothing is removed from `biometric_templates`: our copy is the backup that
     * makes roaming possible, and the point of deleting on the device is usually
     * to move a person to another unit.
     *
     * @throws \InvalidArgumentException when the target cannot receive commands
     */
    public function deleteTemplateFromDevice(BiometricDevice $target, string $pin, ?int $fid = null): BiometricDeviceCommand
    {
        if (! $target->is_active) {
            throw new \InvalidArgumentException('Device is inactive.');
        }

        if (! $target->isAdms()) {
            throw new \InvalidArgumentException('Template delete is only supported for ADMS devices.');
        }

        $pin = trim($pin);

        if ($pin === '') {
            throw new \InvalidArgumentException('A device PIN is required.');
        }

        $payload = ['pin' => $pin];

        if ($fid !== null) {
            $payload['fid'] = $fid;
        }

        $command = BiometricDeviceCommand::create([
            'biometric_device_id' => $target->id,
            'command_type' => 'DELETE_FINGERTMP',
            'payload' => $payload,
            'status' => BiometricDeviceCommand::STATUS_PENDING,
        ]);

        Log::info('Biometric template delete queued', [
            'device_id' => $target->id,
            'device_serial' => $target->serial_number,
            'requested_by' => auth()->id(),
            'command_id' => $command->id,
            'pin' => $pin,
            'fid' => $fid,
            'scope' => $fid === null ? 'all_fingers' : 'single_finger',
        ]);

        return $command;
    }

    /**
     * Identity of a finger slot on a device: PIN + template type + effective FID.
     *
     * finger_index is normalised through FALLBACK_FINGER_INDEX so a stored NULL
     * and a stored 0 collide — they must, because both are pushed as `FID=0`.
     */
    private function slotKey(string $pin, string $type, mixed $fingerIndex): string
    {
        $fid = $fingerIndex === null ? self::FALLBACK_FINGER_INDEX : (int) $fingerIndex;

        return $pin.'|'.$type.'|'.$fid;
    }
}
