<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations for comprehensive Operations & Maintenance (O&M) Overhaul.
     */
    public function up(): void
    {
        // 1. Assets Table (Linear Referencing & Asset Lifecycle)
        if (! Schema::hasTable('om_assets')) {
            Schema::create('om_assets', function (Blueprint $table) {
                $table->id();
                $table->string('asset_code')->unique(); // e.g., ASSET-PVMT-001, ASSET-GD-042, ASSET-BR-003, ASSET-CCTV-012
                $table->string('name');
                $table->enum('category', [
                    'pavement_civil',
                    'bridge_structure',
                    'guardrail_safety',
                    'signage_marking',
                    'drainage_slope',
                    'lighting_electrical',
                    'its_telecom',
                    'toll_equipment',
                    'building_facility',
                ])->default('pavement_civil');
                $table->string('start_chainage'); // e.g. Ch 12+000
                $table->string('end_chainage')->nullable(); // e.g. Ch 14+500 (for linear assets)
                $table->enum('direction', ['northbound', 'southbound', 'both', 'median', 'interchange', 'toll_plaza'])->default('northbound');
                $table->string('location_description')->nullable();
                $table->decimal('latitude', 10, 8)->nullable();
                $table->decimal('longitude', 11, 8)->nullable();
                $table->string('manufacturer')->nullable();
                $table->string('model_number')->nullable();
                $table->string('serial_number')->nullable();
                $table->date('installation_date')->nullable();
                $table->date('warranty_expiry')->nullable();
                $table->decimal('purchase_cost', 15, 2)->nullable();
                $table->decimal('replacement_cost', 15, 2)->nullable();
                $table->unsignedSmallInteger('expected_lifespan_years')->default(10);
                $table->unsignedTinyInteger('condition_score')->default(90); // 0-100 (PCI / Health Score)
                $table->enum('condition_grade', ['excellent', 'good', 'fair', 'poor', 'critical'])->default('good');
                $table->enum('operational_status', ['active', 'degraded', 'under_maintenance', 'out_of_service', 'decommissioned'])->default('active');
                $table->timestamp('last_inspected_at')->nullable();
                $table->text('technical_specs')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['category', 'operational_status']);
                $table->index(['start_chainage', 'direction']);
            });
        }

        // 2. Asset Condition Surveys Table
        if (! Schema::hasTable('om_asset_condition_surveys')) {
            Schema::create('om_asset_condition_surveys', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('asset_id');
                $table->date('survey_date');
                $table->unsignedTinyInteger('condition_score'); // 0-100
                $table->enum('condition_grade', ['excellent', 'good', 'fair', 'poor', 'critical']);
                $table->decimal('roughness_iri', 5, 2)->nullable(); // International Roughness Index for pavement
                $table->decimal('rutting_depth_mm', 5, 2)->nullable();
                $table->decimal('skid_resistance_sn', 5, 2)->nullable();
                $table->unsignedBigInteger('inspector_id')->nullable();
                $table->text('findings')->nullable();
                $table->text('recommendations')->nullable();
                $table->json('photo_paths')->nullable();
                $table->timestamps();

                $table->index('asset_id');
                $table->index('survey_date');
            });
        }

        // 3. Patrol Shifts Table (Scheduled Highway Patrol & Route Logs)
        if (! Schema::hasTable('om_patrol_shifts')) {
            Schema::create('om_patrol_shifts', function (Blueprint $table) {
                $table->id();
                $table->string('patrol_code')->unique(); // e.g. PTR-20260902-M01
                $table->date('patrol_date');
                $table->enum('shift_type', ['morning', 'evening', 'night']);
                $table->string('vehicle_reg_number'); // e.g. Dhaka Metro-Ga 12-3456
                $table->string('call_sign'); // e.g. Patrol Unit 1
                $table->unsignedBigInteger('lead_officer_id')->nullable();
                $table->json('crew_member_ids')->nullable();
                $table->string('assigned_zone_from')->default('Ch 0+000');
                $table->string('assigned_zone_to')->default('Ch 48+000');
                $table->decimal('start_odometer_km', 10, 2)->nullable();
                $table->decimal('end_odometer_km', 10, 2)->nullable();
                $table->decimal('fuel_liters_added', 6, 2)->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('ended_at')->nullable();
                $table->enum('status', ['scheduled', 'in_progress', 'completed', 'cancelled'])->default('scheduled');
                $table->unsignedSmallInteger('incidents_attended_count')->default(0);
                $table->unsignedSmallInteger('defects_reported_count')->default(0);
                $table->text('shift_summary')->nullable();
                $table->timestamps();

                $table->index(['patrol_date', 'shift_type']);
            });
        }

        // 4. Standardized Road Defect & Distress Catalog Table
        if (! Schema::hasTable('om_defects')) {
            Schema::create('om_defects', function (Blueprint $table) {
                $table->id();
                $table->string('defect_number')->unique(); // e.g. DEF-2026-0001
                $table->unsignedBigInteger('asset_id')->nullable();
                $table->unsignedBigInteger('patrol_shift_id')->nullable();
                $table->string('title');
                $table->enum('distress_type', [
                    'pothole',
                    'alligator_cracking',
                    'rutting_depression',
                    'ravelling_stripping',
                    'edge_dropoff',
                    'guardrail_crash_damage',
                    'signboard_damaged_missing',
                    'road_marking_faded',
                    'drain_clogged_flooding',
                    'culvert_obstruction',
                    'lighting_fixture_outage',
                    'cable_theft_cut',
                    'fence_breached',
                    'debris_illegal_dumping',
                    'expansion_joint_failure',
                    'vegetation_overgrowth',
                    'other'
                ])->default('pothole');
                $table->string('chainage'); // e.g. Ch 18+400
                $table->enum('direction', ['northbound', 'southbound', 'both', 'median', 'ramp'])->default('northbound');
                $table->enum('severity', ['low', 'medium', 'high', 'critical'])->default('medium');
                $table->unsignedSmallInteger('sla_hours')->default(24);
                $table->timestamp('sla_due_at')->nullable();
                $table->enum('status', ['reported', 'investigating', 'work_order_created', 'in_repair', 'rectified', 'verified_closed', 'rejected'])->default('reported');
                $table->unsignedBigInteger('reported_by')->nullable();
                $table->unsignedBigInteger('verified_by')->nullable();
                $table->timestamp('rectified_at')->nullable();
                $table->timestamp('verified_at')->nullable();
                $table->decimal('latitude', 10, 8)->nullable();
                $table->decimal('longitude', 11, 8)->nullable();
                $table->text('description')->nullable();
                $table->text('rectification_notes')->nullable();
                $table->json('before_photos')->nullable();
                $table->json('after_photos')->nullable();
                $table->timestamps();

                $table->index(['distress_type', 'status']);
                $table->index('chainage');
            });
        }

        // 5. Enhance om_work_orders Table with Enterprise Fields
        if (Schema::hasTable('om_work_orders')) {
            Schema::table('om_work_orders', function (Blueprint $table) {
                if (! Schema::hasColumn('om_work_orders', 'defect_id')) {
                    $table->unsignedBigInteger('defect_id')->nullable()->after('id');
                }
                if (! Schema::hasColumn('om_work_orders', 'asset_id')) {
                    $table->unsignedBigInteger('asset_id')->nullable()->after('defect_id');
                }
                if (! Schema::hasColumn('om_work_orders', 'work_type')) {
                    $table->enum('work_type', ['routine_corrective', 'preventive_scheduled', 'emergency_repair', 'periodic_rehabilitation', 'tppd_restoration'])->default('routine_corrective')->after('title');
                }
                if (! Schema::hasColumn('om_work_orders', 'contractor_name')) {
                    $table->string('contractor_name')->nullable()->after('assigned_to');
                }
                if (! Schema::hasColumn('om_work_orders', 'target_start_at')) {
                    $table->dateTime('target_start_at')->nullable()->after('completed_at');
                }
                if (! Schema::hasColumn('om_work_orders', 'target_end_at')) {
                    $table->dateTime('target_end_at')->nullable()->after('target_start_at');
                }
                if (! Schema::hasColumn('om_work_orders', 'actual_start_at')) {
                    $table->dateTime('actual_start_at')->nullable()->after('target_end_at');
                }
                if (! Schema::hasColumn('om_work_orders', 'estimated_cost')) {
                    $table->decimal('estimated_cost', 12, 2)->default(0)->after('actual_start_at');
                }
                if (! Schema::hasColumn('om_work_orders', 'actual_cost')) {
                    $table->decimal('actual_cost', 12, 2)->default(0)->after('estimated_cost');
                }
                if (! Schema::hasColumn('om_work_orders', 'requires_lane_closure')) {
                    $table->boolean('requires_lane_closure')->default(false)->after('actual_cost');
                }
                if (! Schema::hasColumn('om_work_orders', 'approved_by')) {
                    $table->unsignedBigInteger('approved_by')->nullable()->after('assigned_by');
                }
                if (! Schema::hasColumn('om_work_orders', 'approved_at')) {
                    $table->timestamp('approved_at')->nullable()->after('approved_by');
                }
                if (! Schema::hasColumn('om_work_orders', 'qc_notes')) {
                    $table->text('qc_notes')->nullable()->after('verified_at');
                }
            });
        }

        // 6. Work Zone Safety & Lane Closure Permits Table
        if (! Schema::hasTable('om_lane_closure_permits')) {
            Schema::create('om_lane_closure_permits', function (Blueprint $table) {
                $table->id();
                $table->string('permit_number')->unique(); // e.g. LCP-2026-0045
                $table->unsignedBigInteger('work_order_id')->nullable();
                $table->string('title');
                $table->string('chainage_from'); // Ch 12+000
                $table->string('chainage_to'); // Ch 13+500
                $table->enum('direction', ['northbound', 'southbound', 'both'])->default('northbound');
                $table->enum('lanes_closed', ['shoulder_only', 'slow_lane', 'fast_lane', 'slow_and_shoulder', 'full_carriageway'])->default('shoulder_only');
                $table->dateTime('scheduled_start');
                $table->dateTime('scheduled_end');
                $table->dateTime('actual_start')->nullable();
                $table->dateTime('actual_end')->nullable();
                $table->enum('status', ['requested', 'approved', 'active', 'cleared', 'rejected', 'cancelled'])->default('requested');
                $table->unsignedBigInteger('requested_by')->nullable();
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->string('traffic_control_plan')->nullable(); // description or document
                $table->boolean('vms_alert_active')->default(false);
                $table->unsignedSmallInteger('safety_cones_deployed')->default(0);
                $table->unsignedSmallInteger('traffic_marshals_deployed')->default(0);
                $table->boolean('flashing_arrow_board_present')->default(false);
                $table->text('safety_checklist_notes')->nullable();
                $table->timestamps();

                $table->index(['status', 'scheduled_start']);
            });
        }

        // 7. Work Order Materials / BOQ Table (Bill of Quantities Consumed)
        if (! Schema::hasTable('om_work_order_materials')) {
            Schema::create('om_work_order_materials', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('work_order_id');
                $table->string('item_name'); // e.g., Cold Mix Asphalt 25kg, W-Beam Guardrail 4m, Thermoplastic White Paint
                $table->string('item_code')->nullable();
                $table->string('unit'); // Bags, Meters, Liters, Nos, Tons
                $table->decimal('quantity_planned', 10, 2)->default(0);
                $table->decimal('quantity_used', 10, 2)->default(0);
                $table->decimal('unit_cost', 10, 2)->default(0);
                $table->decimal('total_cost', 12, 2)->default(0);
                $table->unsignedBigInteger('issued_from_inventory_id')->nullable();
                $table->timestamps();

                $table->index('work_order_id');
            });
        }

        // 8. Work Order Crew & Heavy Machinery Deployment Logs
        if (! Schema::hasTable('om_work_order_crews')) {
            Schema::create('om_work_order_crews', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('work_order_id');
                $table->string('worker_or_machine_name'); // e.g., Asphalt Roller CAT-02, Crew Foreman, 3x Laborers
                $table->enum('resource_type', ['internal_labor', 'contractor_labor', 'equipment_machinery', 'patrol_vehicle']);
                $table->decimal('hours_spent', 6, 2)->default(0);
                $table->decimal('hourly_rate', 10, 2)->default(0);
                $table->decimal('total_cost', 10, 2)->default(0);
                $table->timestamps();

                $table->index('work_order_id');
            });
        }

        // 9. Enhance om_incidents Table (Crash Data, TPPD Recovery, Multi-Agency)
        if (Schema::hasTable('om_incidents')) {
            Schema::table('om_incidents', function (Blueprint $table) {
                if (! Schema::hasColumn('om_incidents', 'incident_type')) {
                    $table->enum('incident_type', [
                        'vehicle_breakdown',
                        'road_traffic_collision',
                        'vehicle_fire',
                        'pedestrian_animal_trespass',
                        'cargo_spill_hazard',
                        'infrastructure_strike',
                        'adverse_weather_flood',
                        'toll_plaza_incident',
                        'other'
                    ])->default('vehicle_breakdown')->after('title');
                }
                if (! Schema::hasColumn('om_incidents', 'detection_source')) {
                    $table->enum('detection_source', ['tmc_cctv', 'patrol_unit', 'sos_call_box', 'hotline_police', 'public_call'])->default('patrol_unit')->after('incident_type');
                }
                if (! Schema::hasColumn('om_incidents', 'latitude')) {
                    $table->decimal('latitude', 10, 8)->nullable()->after('chainage');
                }
                if (! Schema::hasColumn('om_incidents', 'longitude')) {
                    $table->decimal('longitude', 11, 8)->nullable()->after('latitude');
                }
                if (! Schema::hasColumn('om_incidents', 'dispatched_at')) {
                    $table->timestamp('dispatched_at')->nullable()->after('reported_at');
                }
                if (! Schema::hasColumn('om_incidents', 'on_scene_at')) {
                    $table->timestamp('on_scene_at')->nullable()->after('dispatched_at');
                }
                if (! Schema::hasColumn('om_incidents', 'lane_cleared_at')) {
                    $table->timestamp('lane_cleared_at')->nullable()->after('on_scene_at');
                }
                if (! Schema::hasColumn('om_incidents', 'casualties_fatalities')) {
                    $table->unsignedSmallInteger('casualties_fatalities')->default(0)->after('response_time_minutes');
                }
                if (! Schema::hasColumn('om_incidents', 'casualties_injured')) {
                    $table->unsignedSmallInteger('casualties_injured')->default(0)->after('casualties_fatalities');
                }
                if (! Schema::hasColumn('om_incidents', 'vehicles_involved_count')) {
                    $table->unsignedSmallInteger('vehicles_involved_count')->default(1)->after('casualties_injured');
                }
                if (! Schema::hasColumn('om_incidents', 'has_asset_damage')) {
                    $table->boolean('has_asset_damage')->default(false)->after('vehicles_involved_count');
                }
                if (! Schema::hasColumn('om_incidents', 'asset_damage_cost_est')) {
                    $table->decimal('asset_damage_cost_est', 12, 2)->default(0)->after('has_asset_damage');
                }
                if (! Schema::hasColumn('om_incidents', 'tppd_claim_status')) {
                    $table->enum('tppd_claim_status', ['not_applicable', 'claim_prepared', 'submitted_to_police_insurance', 'settled_recovered', 'written_off'])->default('not_applicable')->after('asset_damage_cost_est');
                }
                if (! Schema::hasColumn('om_incidents', 'police_case_number')) {
                    $table->string('police_case_number')->nullable()->after('tppd_claim_status');
                }
            });
        }

        // 10. Incident Involved Vehicles & Drivers Table (TPPD Recovery)
        if (! Schema::hasTable('om_incident_vehicles')) {
            Schema::create('om_incident_vehicles', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('incident_id');
                $table->string('vehicle_reg_number'); // e.g. Dhaka Metro-Ta 11-2233
                $table->string('vehicle_type'); // Heavy Truck, Bus, Private Car, Microbus, Pickup
                $table->string('driver_name')->nullable();
                $table->string('driver_license_number')->nullable();
                $table->string('driver_phone')->nullable();
                $table->string('insurance_company')->nullable();
                $table->string('insurance_policy_number')->nullable();
                $table->boolean('towed_by_expressway_wrecker')->default(false);
                $table->decimal('towing_fee_charged', 10, 2)->default(0);
                $table->text('damage_to_vehicle_description')->nullable();
                $table->text('damage_to_expressway_asset')->nullable();
                $table->decimal('estimated_asset_repair_cost', 12, 2)->default(0);
                $table->timestamps();

                $table->index('incident_id');
            });
        }

        // 11. Toll Shift Audits Table (Cashier Reconciliation & Shortage/Surplus)
        if (! Schema::hasTable('om_toll_shift_audits')) {
            Schema::create('om_toll_shift_audits', function (Blueprint $table) {
                $table->id();
                $table->string('audit_code')->unique(); // e.g. TOLL-AUDIT-20260902-M01
                $table->string('plaza_name')->default('Main Toll Plaza (Ch 0+000)');
                $table->date('shift_date');
                $table->enum('shift_type', ['morning', 'evening', 'night']);
                $table->unsignedBigInteger('auditor_id')->nullable();
                $table->unsignedBigInteger('shift_supervisor_id')->nullable();
                $table->decimal('system_calculated_total', 15, 2)->default(0);
                $table->decimal('cash_declared_by_collectors', 15, 2)->default(0);
                $table->decimal('etc_automatic_revenue', 15, 2)->default(0);
                $table->decimal('pos_card_mfs_revenue', 15, 2)->default(0);
                $table->decimal('variance_amount', 12, 2)->default(0); // surplus (+), shortage (-)
                $table->unsignedInteger('total_vehicle_transactions')->default(0);
                $table->unsignedInteger('avc_physical_axle_count')->default(0);
                $table->unsignedInteger('exempted_vehicle_count')->default(0);
                $table->unsignedInteger('evasion_violation_count')->default(0);
                $table->string('bank_deposit_reference')->nullable();
                $table->decimal('bank_deposit_amount', 15, 2)->nullable();
                $table->enum('audit_status', ['draft', 'submitted', 'verified_matched', 'discrepancy_flagged', 'approved'])->default('draft');
                $table->text('auditor_notes')->nullable();
                $table->timestamps();

                $table->index(['shift_date', 'shift_type']);
            });
        }

        // 12. Toll Exemptions Registry Table
        if (! Schema::hasTable('om_toll_exemptions')) {
            Schema::create('om_toll_exemptions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('toll_shift_audit_id')->nullable();
                $table->string('plaza_name');
                $table->string('lane_id');
                $table->string('vehicle_reg_number');
                $table->enum('exemption_category', ['emergency_ambulance_fire', 'police_law_enforcement', 'military_convoy', 'vip_government_official', 'expressway_maintenance_fleet', 'authorized_pass_holder']);
                $table->string('authorizing_document_ref')->nullable();
                $table->string('officer_or_driver_name')->nullable();
                $table->timestamp('passed_at');
                $table->timestamps();

                $table->index('passed_at');
            });
        }

        // 13. Enhance om_shift_logs Table with Safety & Equipment Checklists
        if (Schema::hasTable('om_shift_logs')) {
            Schema::table('om_shift_logs', function (Blueprint $table) {
                if (! Schema::hasColumn('om_shift_logs', 'shift_code')) {
                    $table->string('shift_code')->nullable()->after('id');
                }
                if (! Schema::hasColumn('om_shift_logs', 'incoming_operator_id')) {
                    $table->unsignedBigInteger('incoming_operator_id')->nullable()->after('operator_id');
                }
                if (! Schema::hasColumn('om_shift_logs', 'active_lane_closures_count')) {
                    $table->unsignedSmallInteger('active_lane_closures_count')->default(0)->after('open_incidents_count');
                }
                if (! Schema::hasColumn('om_shift_logs', 'weather_condition')) {
                    $table->enum('weather_condition', ['clear', 'rain', 'heavy_fog', 'storm_high_winds'])->default('clear')->after('active_lane_closures_count');
                }
                if (! Schema::hasColumn('om_shift_logs', 'cctv_offline_count')) {
                    $table->unsignedSmallInteger('cctv_offline_count')->default(0)->after('weather_condition');
                }
                if (! Schema::hasColumn('om_shift_logs', 'vms_offline_count')) {
                    $table->unsignedSmallInteger('vms_offline_count')->default(0)->after('cctv_offline_count');
                }
                if (! Schema::hasColumn('om_shift_logs', 'wim_offline_count')) {
                    $table->unsignedSmallInteger('wim_offline_count')->default(0)->after('vms_offline_count');
                }
                if (! Schema::hasColumn('om_shift_logs', 'acknowledged_by_user_id')) {
                    $table->unsignedBigInteger('acknowledged_by_user_id')->nullable()->after('is_acknowledged');
                }
                if (! Schema::hasColumn('om_shift_logs', 'acknowledged_at')) {
                    $table->timestamp('acknowledged_at')->nullable()->after('acknowledged_by_user_id');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('om_toll_exemptions');
        Schema::dropIfExists('om_toll_shift_audits');
        Schema::dropIfExists('om_incident_vehicles');
        Schema::dropIfExists('om_work_order_crews');
        Schema::dropIfExists('om_work_order_materials');
        Schema::dropIfExists('om_lane_closure_permits');
        Schema::dropIfExists('om_defects');
        Schema::dropIfExists('om_patrol_shifts');
        Schema::dropIfExists('om_asset_condition_surveys');
        Schema::dropIfExists('om_assets');
    }
};
