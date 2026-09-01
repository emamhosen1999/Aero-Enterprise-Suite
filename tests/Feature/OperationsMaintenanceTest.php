<?php

namespace Tests\Feature;

use App\Models\OmAsset;
use App\Models\OmDefect;
use App\Models\OmIncident;
use App\Models\OmShiftLog;
use App\Models\OmTollRecord;
use App\Models\OmTollShiftAudit;
use App\Models\OmWorkOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationsMaintenanceTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_om_dashboard_returns_successful_data(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/om/dashboard');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'stats' => [
                    'today_toll_revenue',
                    'etc_vehicle_ratio',
                    'active_incidents_count',
                    'open_work_orders_count',
                    'open_defects_count',
                ],
            ]);
    }

    public function test_can_create_defect_with_sla_target(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/om/defects', [
                'title' => 'Test Pothole on Ch 14+250',
                'distress_type' => 'pothole',
                'chainage' => 'Ch 14+250',
                'direction' => 'northbound',
                'severity' => 'critical',
                'description' => 'Test defect description for SLA verification',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('om_defects', [
            'title' => 'Test Pothole on Ch 14+250',
            'distress_type' => 'pothole',
            'sla_hours' => 4,
        ]);
    }

    public function test_can_convert_defect_to_work_order(): void
    {
        $defect = OmDefect::create([
            'defect_number' => 'DEF-TEST-' . rand(1000, 9999),
            'title' => 'Guardrail Collision Test',
            'distress_type' => 'guardrail_crash_damage',
            'chainage' => 'Ch 22+800',
            'direction' => 'southbound',
            'severity' => 'high',
            'sla_hours' => 24,
            'sla_due_at' => now()->addHours(24),
            'status' => 'reported',
            'reported_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/om/defects/{$defect->id}/convert-to-wo", [
                'title' => 'Emergency Repair for Guardrail Test',
                'category' => 'guardrail',
                'assigned_to' => 'Roadside Crew Alpha',
                'estimated_cost' => 35000,
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('om_work_orders', [
            'defect_id' => $defect->id,
            'category' => 'guardrail',
            'estimated_cost' => 35000,
        ]);

        $this->assertEquals('work_order_created', $defect->fresh()->status);
    }

    public function test_can_create_incident_and_update_timeline(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/om/incidents', [
                'title' => 'Vehicle Fire on Shoulder Test',
                'incident_type' => 'vehicle_fire',
                'chainage' => 'Ch 31+400',
                'direction' => 'northbound',
                'severity' => 'critical',
                'dispatched_unit' => 'Fire Unit 1',
                'description' => 'Test incident creation',
            ]);

        $response->assertStatus(200);
        $incidentId = $response->json('incident.id');

        // Update to on_scene
        $statusRes = $this->actingAs($this->user)
            ->postJson("/om/incidents/{$incidentId}/status", [
                'status' => 'on_scene',
            ]);

        $statusRes->assertStatus(200);
        $this->assertDatabaseHas('om_incidents', [
            'id' => $incidentId,
            'status' => 'on_scene',
        ]);
    }

    public function test_can_submit_toll_shift_reconciliation_audit(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/om/toll-operations/audit', [
                'plaza_name' => 'Main Toll Plaza (Ch 0+000)',
                'shift_date' => now()->toDateString(),
                'shift_type' => 'morning',
                'system_calculated_total' => 100000.00,
                'cash_declared_by_collectors' => 20000.00,
                'etc_automatic_revenue' => 80000.00,
                'bank_deposit_reference' => 'TEST-DEP-001',
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('om_toll_shift_audits', [
            'plaza_name' => 'Main Toll Plaza (Ch 0+000)',
            'variance_amount' => 0.00,
            'audit_status' => 'verified_matched',
        ]);
    }

    public function test_mobile_field_endpoints(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/om/field/overview');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'active_incidents',
                    'assigned_work_orders',
                    'open_defects',
                ],
            ]);
    }
}
