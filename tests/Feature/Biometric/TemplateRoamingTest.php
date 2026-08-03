<?php

namespace Tests\Feature\Biometric;

use App\Models\HRM\BiometricDevice;
use App\Models\HRM\BiometricDeviceCommand;
use App\Models\User;
use App\Services\Biometric\TemplateRoamingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Biometric roaming write-back (docs/zkteco-adms-capability-matrix.md §2).
 *
 * Templates have always been captured and never restorable, so replacing a unit
 * meant re-enrolling every person by hand. These tests pin:
 *
 *  - the exact `DATA UPDATE FINGERTMP` / `DATA DELETE FINGERTMP` wire strings,
 *  - that a restore queues one command per template and no more,
 *  - that templates the target already holds are skipped with a stated reason,
 *  - that face templates are refused rather than pushed with a guessed verb,
 *  - that an inactive or non-ADMS target is refused the same way
 *    initiateLogDownload() refuses one,
 *  - that the per-call command cap holds,
 *  - that the UI read model never carries template_data.
 */
class TemplateRoamingTest extends TestCase
{
    use RefreshDatabase;

    private function service(): TemplateRoamingService
    {
        return app(TemplateRoamingService::class);
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

    private function command(BiometricDevice $device, string $type, ?array $payload = null): BiometricDeviceCommand
    {
        return BiometricDeviceCommand::create([
            'biometric_device_id' => $device->id,
            'command_type' => $type,
            'payload' => $payload,
            'status' => 'pending',
        ]);
    }

    /**
     * A stored template row. Mirrors what processTemplateUpload() actually
     * writes — note finger_index is left NULL, because capture never sets it.
     */
    private function template(User $user, BiometricDevice $source, array $overrides = []): int
    {
        return DB::table('biometric_templates')->insertGetId(array_merge([
            'user_id' => $user->id,
            'biometric_device_id' => $source->id,
            'device_user_id' => (string) $user->employee_id,
            'template_type' => 'fingerprint',
            'finger_index' => null,
            'template_data' => 'VGVtcGxhdGVCbG9i',
            'template_size' => 16,
            'template_version' => 'templatev10',
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    // ──────────────────────────────────────────────────────────────
    //  Task 1 — command vocabulary
    // ──────────────────────────────────────────────────────────────

    public function test_update_fingertmp_emits_the_documented_string(): void
    {
        $device = $this->device();

        $command = $this->command($device, 'UPDATE_FINGERTMP', [
            'pin' => '1024',
            'fid' => 3,
            'size' => 16,
            'valid' => 1,
            'template' => 'VGVtcGxhdGVCbG9i',
        ]);

        $this->assertSame(
            "C:{$command->id}:DATA UPDATE FINGERTMP PIN=1024 FID=3 Size=16 Valid=1 TMP=VGVtcGxhdGVCbG9i",
            $command->toAdmsString()
        );
    }

    public function test_update_fingertmp_defaults_fid_size_and_valid(): void
    {
        $device = $this->device();

        $command = $this->command($device, 'UPDATE_FINGERTMP', [
            'pin' => '7',
            'template' => 'QUJDRA==',
        ]);

        // FID falls back to slot 0 (finger_index is not captured — schema gap),
        // Size is derived from the template, Valid defaults to 1 (enrolled).
        $this->assertSame(
            "C:{$command->id}:DATA UPDATE FINGERTMP PIN=7 FID=0 Size=8 Valid=1 TMP=QUJDRA==",
            $command->toAdmsString()
        );
    }

    public function test_update_fingertmp_strips_whitespace_from_the_template(): void
    {
        $device = $this->device();

        // Captured templates can carry newlines: processTemplateUpload()'s regex
        // allows \s inside TMP. A newline inside the command would truncate it as
        // far as the device is concerned.
        $command = $this->command($device, 'UPDATE_FINGERTMP', [
            'pin' => '9',
            'fid' => 0,
            'template' => "QUJD\nRA ==\t",
        ]);

        $emitted = $command->toAdmsString();

        $this->assertStringNotContainsString("\n", $emitted);
        $this->assertStringNotContainsString("\t", $emitted);
        $this->assertSame(
            "C:{$command->id}:DATA UPDATE FINGERTMP PIN=9 FID=0 Size=8 Valid=1 TMP=QUJDRA==",
            $emitted
        );
    }

    public function test_delete_fingertmp_emits_pin_and_optional_fid(): void
    {
        $device = $this->device();

        $allFingers = $this->command($device, 'DELETE_FINGERTMP', ['pin' => '1024']);
        $oneFinger = $this->command($device, 'DELETE_FINGERTMP', ['pin' => '1024', 'fid' => 2]);

        $this->assertSame(
            "C:{$allFingers->id}:DATA DELETE FINGERTMP PIN=1024",
            $allFingers->toAdmsString()
        );
        $this->assertSame(
            "C:{$oneFinger->id}:DATA DELETE FINGERTMP PIN=1024 FID=2",
            $oneFinger->toAdmsString()
        );

        // `FID=` on its own is a syntax error; omission must mean "all fingers".
        $this->assertStringNotContainsString('FID=', $allFingers->toAdmsString());
    }

    public function test_existing_command_strings_are_untouched(): void
    {
        $device = $this->device();

        $reboot = $this->command($device, 'REBOOT');
        $deleteUser = $this->command($device, 'DELETE_USER', ['pin' => '7']);

        $this->assertSame("C:{$reboot->id}:REBOOT", $reboot->toAdmsString());
        $this->assertSame("C:{$deleteUser->id}:DATA DELETE USERINFO PIN=7", $deleteUser->toAdmsString());
    }

    // ──────────────────────────────────────────────────────────────
    //  Task 2 — restore
    // ──────────────────────────────────────────────────────────────

    public function test_restore_queues_one_command_per_template(): void
    {
        $source = $this->device(['serial_number' => 'AF6P231260266']);
        $target = $this->device(['name' => 'Replacement MB460']);

        $alice = User::factory()->create(['employee_id' => 1001]);
        $bob = User::factory()->create(['employee_id' => 1002]);

        $this->template($alice, $source, ['template_data' => 'QUFB']);
        $this->template($bob, $source, ['template_data' => 'QkJC']);

        $result = $this->service()->restoreTemplatesToDevice($target);

        $this->assertSame(2, $result['queued']);
        $this->assertSame(0, $result['skipped']);
        $this->assertSame(2, $result['users']);
        $this->assertSame([], $result['reasons']);

        $commands = BiometricDeviceCommand::where('biometric_device_id', $target->id)->get();
        $this->assertCount(2, $commands);

        foreach ($commands as $command) {
            $this->assertSame('UPDATE_FINGERTMP', $command->command_type);
            $this->assertSame('pending', $command->status);
            $this->assertSame($source->id, $command->payload['source_device_id']);
        }

        $strings = $commands->map->toAdmsString()->all();
        $this->assertContains(
            'C:'.$commands[0]->id.':DATA UPDATE FINGERTMP PIN=1001 FID=0 Size=4 Valid=1 TMP=QUFB',
            $strings
        );
        $this->assertContains(
            'C:'.$commands[1]->id.':DATA UPDATE FINGERTMP PIN=1002 FID=0 Size=4 Valid=1 TMP=QkJC',
            $strings
        );

        // Nothing was queued at the device the templates came from.
        $this->assertSame(0, BiometricDeviceCommand::where('biometric_device_id', $source->id)->count());
    }

    public function test_restore_can_be_scoped_to_specific_users(): void
    {
        $source = $this->device();
        $target = $this->device();

        $alice = User::factory()->create(['employee_id' => 2001]);
        $bob = User::factory()->create(['employee_id' => 2002]);

        $this->template($alice, $source);
        $this->template($bob, $source);

        $result = $this->service()->restoreTemplatesToDevice($target, [$alice->id]);

        $this->assertSame(1, $result['queued']);
        $this->assertSame(1, $result['users']);
        $this->assertSame(
            '2001',
            BiometricDeviceCommand::where('biometric_device_id', $target->id)->sole()->payload['pin']
        );
    }

    public function test_templates_already_on_the_target_are_skipped_with_a_reason(): void
    {
        $source = $this->device();
        $target = $this->device();

        $alice = User::factory()->create(['employee_id' => 3001]);
        $bob = User::factory()->create(['employee_id' => 3002]);

        $this->template($alice, $source);
        $this->template($bob, $source);
        // Alice is already enrolled on the target — restoring would be a no-op.
        $this->template($alice, $target);

        $result = $this->service()->restoreTemplatesToDevice($target);

        // Alice is skipped twice: once for the source copy the target already has,
        // once for the target's own row.
        $this->assertSame(1, $result['queued']);
        $this->assertSame(2, $result['skipped']);
        $this->assertSame(2, $result['reasons']['already_on_device']);

        $this->assertSame(
            '3002',
            BiometricDeviceCommand::where('biometric_device_id', $target->id)->sole()->payload['pin']
        );
    }

    public function test_the_same_finger_held_by_two_devices_queues_once(): void
    {
        $old = $this->device();
        $spare = $this->device();
        $target = $this->device();

        $alice = User::factory()->create(['employee_id' => 4001]);

        $this->template($alice, $old, ['template_data' => 'T0xE', 'updated_at' => now()->subDay()]);
        $this->template($alice, $spare, ['template_data' => 'TkVX', 'updated_at' => now()]);

        $result = $this->service()->restoreTemplatesToDevice($target);

        $this->assertSame(1, $result['queued']);
        $this->assertSame(1, $result['skipped']);
        $this->assertSame(1, $result['reasons']['duplicate_template']);

        // The freshest enrolment wins.
        $command = BiometricDeviceCommand::where('biometric_device_id', $target->id)->sole();
        $this->assertSame('TkVX', $command->payload['template']);
        $this->assertSame($spare->id, $command->payload['source_device_id']);
    }

    public function test_face_templates_are_never_pushed_with_a_guessed_verb(): void
    {
        $source = $this->device();
        $target = $this->device();

        $alice = User::factory()->create(['employee_id' => 5001]);

        $this->template($alice, $source, [
            'template_type' => 'face',
            'template_version' => 'facetmpv10',
        ]);

        $result = $this->service()->restoreTemplatesToDevice($target);

        $this->assertSame(0, $result['queued']);
        $this->assertSame(1, $result['skipped']);
        $this->assertSame(1, $result['reasons'][TemplateRoamingService::FACE_REASON]);
        $this->assertSame(0, BiometricDeviceCommand::count());
    }

    public function test_empty_and_oversized_templates_are_refused(): void
    {
        $source = $this->device();
        $target = $this->device();

        $blank = User::factory()->create(['employee_id' => 6001]);
        $huge = User::factory()->create(['employee_id' => 6002]);

        $this->template($blank, $source, ['template_data' => "   \n\t "]);
        $this->template($huge, $source, [
            'template_data' => str_repeat('A', TemplateRoamingService::MAX_TEMPLATE_BYTES + 1),
        ]);

        $result = $this->service()->restoreTemplatesToDevice($target);

        $this->assertSame(0, $result['queued']);
        $this->assertSame(2, $result['skipped']);
        $this->assertSame(1, $result['reasons']['empty_template']);
        $this->assertSame(1, $result['reasons']['template_too_large']);
    }

    public function test_the_per_call_command_cap_is_enforced(): void
    {
        $source = $this->device();
        $target = $this->device();

        $user = User::factory()->create(['employee_id' => 7000]);

        $cap = TemplateRoamingService::MAX_COMMANDS_PER_RESTORE;
        $rows = [];
        for ($i = 0; $i < $cap + 5; $i++) {
            $rows[] = [
                'user_id' => $user->id,
                'biometric_device_id' => $source->id,
                'device_user_id' => (string) (8000 + $i),
                'template_type' => 'fingerprint',
                'finger_index' => null,
                'template_data' => 'QUFB',
                'template_size' => 4,
                'template_version' => 'templatev10',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        DB::table('biometric_templates')->insert($rows);

        $result = $this->service()->restoreTemplatesToDevice($target);

        $this->assertSame($cap, $result['queued']);
        $this->assertSame(5, $result['skipped']);
        $this->assertSame(5, $result['reasons']['cap_reached']);
        $this->assertSame(
            $cap,
            BiometricDeviceCommand::where('biometric_device_id', $target->id)->count()
        );
    }

    // ──────────────────────────────────────────────────────────────
    //  Target guards — same shape as initiateLogDownload()
    // ──────────────────────────────────────────────────────────────

    public function test_an_inactive_target_is_refused(): void
    {
        $target = $this->device(['is_active' => false]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Device is inactive.');

        $this->service()->restoreTemplatesToDevice($target);
    }

    public function test_a_non_adms_target_is_refused(): void
    {
        $target = $this->device(['protocol' => 'webhook']);

        try {
            $this->service()->restoreTemplatesToDevice($target);
            $this->fail('A non-ADMS target must be refused.');
        } catch (\InvalidArgumentException $e) {
            $this->assertSame('Template restore is only supported for ADMS devices.', $e->getMessage());
        }

        $this->assertSame(0, BiometricDeviceCommand::count());
    }

    public function test_delete_refuses_a_non_adms_or_inactive_target_and_an_empty_pin(): void
    {
        $service = $this->service();

        try {
            $service->deleteTemplateFromDevice($this->device(['is_active' => false]), '1');
            $this->fail('An inactive target must be refused.');
        } catch (\InvalidArgumentException $e) {
            $this->assertSame('Device is inactive.', $e->getMessage());
        }

        try {
            $service->deleteTemplateFromDevice($this->device(['protocol' => 'webhook']), '1');
            $this->fail('A non-ADMS target must be refused.');
        } catch (\InvalidArgumentException $e) {
            $this->assertSame('Template delete is only supported for ADMS devices.', $e->getMessage());
        }

        try {
            $service->deleteTemplateFromDevice($this->device(), '   ');
            $this->fail('An empty PIN must be refused.');
        } catch (\InvalidArgumentException $e) {
            $this->assertSame('A device PIN is required.', $e->getMessage());
        }

        $this->assertSame(0, BiometricDeviceCommand::count());
    }

    public function test_delete_queues_a_pending_command_and_keeps_our_stored_copy(): void
    {
        $device = $this->device();
        $alice = User::factory()->create(['employee_id' => 9001]);
        $this->template($alice, $device);

        $command = $this->service()->deleteTemplateFromDevice($device, '9001', 1);

        $this->assertSame('DELETE_FINGERTMP', $command->command_type);
        $this->assertSame('pending', $command->status);
        $this->assertSame($device->id, $command->biometric_device_id);
        $this->assertSame(
            "C:{$command->id}:DATA DELETE FINGERTMP PIN=9001 FID=1",
            $command->toAdmsString()
        );

        // Our copy is the backup that makes roaming possible; deleting on the
        // device must not destroy it.
        $this->assertDatabaseHas('biometric_templates', [
            'user_id' => $alice->id,
            'biometric_device_id' => $device->id,
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    //  listTemplates
    // ──────────────────────────────────────────────────────────────

    public function test_list_templates_returns_the_ui_read_model_without_template_data(): void
    {
        $source = $this->device(['name' => 'Gate A', 'serial_number' => 'AF6P231260266']);
        $alice = User::factory()->create(['employee_id' => 4242, 'name' => 'Alice Rahman']);

        $this->template($alice, $source, ['template_data' => 'U0VDUkVU', 'template_size' => 8]);

        $rows = $this->service()->listTemplates();

        $this->assertCount(1, $rows);
        $row = $rows->first();

        $this->assertSame([
            'id', 'user_id', 'user_name', 'employee_id', 'pin', 'template_type',
            'finger_index', 'template_size', 'template_version', 'source_device_id',
            'source_device_name', 'source_device_serial', 'captured_at', 'updated_at',
            'restorable', 'not_restorable_reason',
        ], array_keys($row));

        $this->assertSame('Alice Rahman', $row['user_name']);
        $this->assertSame('4242', $row['pin']);
        $this->assertSame('fingerprint', $row['template_type']);
        $this->assertSame(8, $row['template_size']);
        $this->assertSame('Gate A', $row['source_device_name']);
        $this->assertSame('AF6P231260266', $row['source_device_serial']);
        $this->assertTrue($row['restorable']);
        // finger_index is never captured today — the UI must be able to see that.
        $this->assertNull($row['finger_index']);
        $this->assertNotNull($row['captured_at']);

        // The payload itself must never reach a browser.
        $this->assertArrayNotHasKey('template_data', $row);
        $this->assertStringNotContainsString('U0VDUkVU', json_encode($rows->all()));
    }

    public function test_list_templates_filters_by_user_and_device_and_flags_face_rows(): void
    {
        $deviceA = $this->device();
        $deviceB = $this->device();

        $alice = User::factory()->create(['employee_id' => 5101]);
        $bob = User::factory()->create(['employee_id' => 5102]);

        $this->template($alice, $deviceA);
        $this->template($bob, $deviceB);
        $this->template($bob, $deviceB, [
            'template_type' => 'face',
            'template_version' => 'facetmpv10',
        ]);

        $service = $this->service();

        $this->assertCount(3, $service->listTemplates());
        $this->assertCount(1, $service->listTemplates($alice->id));
        $this->assertCount(2, $service->listTemplates(null, $deviceB->id));
        $this->assertCount(0, $service->listTemplates($alice->id, $deviceB->id));

        $face = $service->listTemplates($bob->id)->firstWhere('template_type', 'face');
        $this->assertFalse($face['restorable']);
        $this->assertSame(TemplateRoamingService::FACE_REASON, $face['not_restorable_reason']);
    }
}
