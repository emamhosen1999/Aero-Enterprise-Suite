<?php

namespace Tests\Feature\Settings;

use App\Models\HRM\BiometricDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Covers the server-side provisioning path for the ADMS per-device shared
 * secret (biometric_devices.adms_token).
 *
 * Before this, adms_token had no write path at all: it was in $fillable but
 * absent from store()/update() validation and had no regenerate endpoint, so
 * the EnsureAdmsDeviceAuthorized strict-auth model could only be provisioned by
 * hand-editing the database.
 *
 * Also pins the two other columns that were in $fillable but unsettable
 * (port, notes), and proves end-to-end that a token minted here actually
 * satisfies the /iclock/* middleware.
 */
class BiometricDeviceProvisioningTest extends TestCase
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

    // ---------------------------------------------------------------- Task 3

    public function test_regenerate_adms_token_persists_and_returns_the_token(): void
    {
        $device = $this->device();
        $this->assertNull($device->adms_token);

        $response = $this->actingAs($this->admin())
            ->postJson(route('biometric-devices.regenerate-adms-token', $device->id));

        $response->assertOk()->assertJsonStructure(['message', 'adms_token']);

        $token = $response->json('adms_token');
        $this->assertNotEmpty($token);

        // Persisted, and the response body matches what was stored.
        $device->refresh();
        $this->assertNotNull($device->adms_token);
        $this->assertSame($token, $device->adms_token);
    }

    public function test_regenerating_twice_produces_different_tokens(): void
    {
        $device = $this->device();
        $admin = $this->admin();

        $first = $this->actingAs($admin)
            ->postJson(route('biometric-devices.regenerate-adms-token', $device->id))
            ->assertOk()->json('adms_token');

        $second = $this->actingAs($admin)
            ->postJson(route('biometric-devices.regenerate-adms-token', $device->id))
            ->assertOk()->json('adms_token');

        $this->assertNotSame($first, $second);
        $this->assertSame($second, $device->fresh()->adms_token);
    }

    public function test_regenerate_adms_token_returns_404_for_unknown_device(): void
    {
        $this->actingAs($this->admin())
            ->postJson(route('biometric-devices.regenerate-adms-token', 999999))
            ->assertNotFound();
    }

    // ---------------------------------------------------------------- Task 1

    public function test_device_created_via_store_has_a_null_adms_token(): void
    {
        // NULL must stay meaningful: the middleware reads "no token configured"
        // as an intentional allowlist-only fallback so existing hardware keeps
        // working when strict mode is switched on. Auto-generating on create
        // would silently break every un-reconfigured device.
        $response = $this->actingAs($this->admin())
            ->postJson(route('biometric-devices.store'), [
                'name' => 'Lobby Reader',
                'serial_number' => 'SN-NO-AUTOGEN',
                'protocol' => 'adms',
            ]);

        $response->assertCreated();

        $device = BiometricDevice::where('serial_number', 'SN-NO-AUTOGEN')->firstOrFail();
        $this->assertNull($device->adms_token);
        // auth_token, by contrast, IS auto-generated — different channel, unchanged.
        $this->assertNotNull($device->auth_token);
    }

    public function test_adms_token_cannot_be_set_through_store_mass_assignment(): void
    {
        $this->actingAs($this->admin())
            ->postJson(route('biometric-devices.store'), [
                'name' => 'Sneaky',
                'serial_number' => 'SN-MASS-ASSIGN',
                'protocol' => 'adms',
                'adms_token' => 'attacker-chosen-secret',
            ])->assertCreated();

        $device = BiometricDevice::where('serial_number', 'SN-MASS-ASSIGN')->firstOrFail();
        $this->assertNull($device->adms_token);
    }

    // ---------------------------------------------------------------- Task 2

    public function test_store_persists_port_and_notes(): void
    {
        $this->actingAs($this->admin())
            ->postJson(route('biometric-devices.store'), [
                'name' => 'Warehouse Gate',
                'serial_number' => 'SN-PORT-NOTES',
                'ip_address' => '192.168.1.50',
                'port' => 4370,
                'notes' => 'Mounted by the loading bay.',
            ])->assertCreated();

        $this->assertDatabaseHas('biometric_devices', [
            'serial_number' => 'SN-PORT-NOTES',
            'port' => 4370,
            'notes' => 'Mounted by the loading bay.',
        ]);
    }

    public function test_update_persists_port_and_notes(): void
    {
        $device = $this->device(['serial_number' => 'SN-UPDATE-ME']);

        $this->actingAs($this->admin())
            ->putJson(route('biometric-devices.update', $device->id), [
                'port' => 5005,
                'notes' => 'Relocated to the east door.',
            ])->assertOk();

        $device->refresh();
        $this->assertSame(5005, (int) $device->port);
        $this->assertSame('Relocated to the east door.', $device->notes);
    }

    public function test_port_is_rejected_when_out_of_range(): void
    {
        $this->actingAs($this->admin())
            ->postJson(route('biometric-devices.store'), [
                'name' => 'Bad Port',
                'serial_number' => 'SN-BAD-PORT',
                'port' => 70000,
            ])->assertStatus(422)->assertJsonValidationErrors('port');
    }

    // ------------------------------------------------- End-to-end middleware

    public function test_provisioned_token_satisfies_the_adms_middleware(): void
    {
        // Mint a token exactly the way an admin would, then prove a device
        // presenting it is accepted by EnsureAdmsDeviceAuthorized under strict
        // mode, and that a wrong token is rejected.
        $device = $this->device();
        User::factory()->create(['employee_id' => '42']);

        $token = $this->actingAs($this->admin())
            ->postJson(route('biometric-devices.regenerate-adms-token', $device->id))
            ->assertOk()->json('adms_token');

        config(['attendance.adms_strict_auth' => true]);

        // Wrong token -> rejected with the plain-text body the device expects.
        $bad = $this->pushAttlog($device->serial_number, '&token=definitely-wrong');
        $bad->assertStatus(401);
        $this->assertSame('ERROR', $bad->getContent());
        $this->assertDatabaseMissing('biometric_att_logs', [
            'serial_number' => $device->serial_number,
        ]);

        // The provisioned token -> accepted and the punch is ingested.
        $good = $this->pushAttlog($device->serial_number, '&token='.rawurlencode($token));
        $good->assertOk();
        $this->assertSame('OK', $good->getContent());
        $this->assertDatabaseHas('biometric_att_logs', [
            'serial_number' => $device->serial_number,
            'user_pin' => '42',
        ]);
    }

    public function test_provisioned_token_is_accepted_via_header(): void
    {
        // The middleware also accepts X-Device-Token / X-ADMS-Token, which is
        // how a reverse proxy in front of the device would inject the secret.
        $device = $this->device();
        User::factory()->create(['employee_id' => '77']);

        $token = $this->actingAs($this->admin())
            ->postJson(route('biometric-devices.regenerate-adms-token', $device->id))
            ->assertOk()->json('adms_token');

        config(['attendance.adms_strict_auth' => true]);

        $uri = '/iclock/cdata?SN='.rawurlencode($device->serial_number).'&table=ATTLOG';
        $response = $this->call('POST', $uri, [], [], [], [
            'CONTENT_TYPE' => 'text/plain',
            'HTTP_X_DEVICE_TOKEN' => $token,
        ], "77\t2026-07-15 10:00:00\t0");

        $response->assertOk();
        $this->assertSame('OK', $response->getContent());
    }

    public function test_provisioning_a_token_locks_down_userinfo_enrollment(): void
    {
        // Once a secret exists, device-initiated user enrollment requires it
        // even in observe mode — this is the security win the write path unlocks.
        $device = $this->device();

        $token = $this->actingAs($this->admin())
            ->postJson(route('biometric-devices.regenerate-adms-token', $device->id))
            ->assertOk()->json('adms_token');

        config(['attendance.adms_strict_auth' => false]);

        $base = '/iclock/cdata?SN='.rawurlencode($device->serial_number).'&table=USERINFO';
        $body = "PIN=99\tName=Injected Hacker\tCard=123456\tPrivilege=0";

        $blocked = $this->call('POST', $base, [], [], [], ['CONTENT_TYPE' => 'text/plain'], $body);
        $blocked->assertStatus(401);
        $this->assertDatabaseMissing('users', ['employee_id' => '99']);

        $allowed = $this->call(
            'POST',
            $base.'&token='.rawurlencode($token),
            [], [], [],
            ['CONTENT_TYPE' => 'text/plain'],
            "PIN=99\tName=Real Employee\tCard=123456\tPrivilege=0"
        );
        $allowed->assertOk();
        $this->assertDatabaseHas('users', ['employee_id' => '99']);
    }

    private function pushAttlog(string $serial, string $query = '', string $body = "42\t2026-07-15 09:00:00\t0")
    {
        $uri = '/iclock/cdata?SN='.rawurlencode($serial).'&table=ATTLOG'.$query;

        return $this->call('POST', $uri, [], [], [], ['CONTENT_TYPE' => 'text/plain'], $body);
    }
}
