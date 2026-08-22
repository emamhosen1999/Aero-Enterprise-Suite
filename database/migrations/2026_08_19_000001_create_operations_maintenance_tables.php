<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Toll Records Table
        Schema::create('om_toll_records', function (Blueprint $table) {
            $table->id();
            $table->string('plaza_name')->default('Main Toll Plaza (Ch 0+000)');
            $table->string('lane_id');
            $table->string('vehicle_class'); // Class 1, Class 2, Heavy Truck, etc.
            $table->enum('payment_method', ['etc', 'cash', 'card', 'mobile_pay'])->default('etc');
            $table->decimal('amount', 10, 2);
            $table->timestamp('transacted_at');
            $table->timestamps();
        });

        // 2. Traffic Monitoring Logs Table (TMC / ITS)
        Schema::create('om_traffic_logs', function (Blueprint $table) {
            $table->id();
            $table->string('section_code'); // e.g., CH_0_10, CH_10_20, CH_20_35, CH_35_48
            $table->string('section_name'); // e.g., Joydevpur to Kanchan Bridge
            $table->integer('vehicle_count_per_hour')->default(0);
            $table->decimal('avg_speed_kmh', 5, 2)->default(75.0);
            $table->enum('density_status', ['free_flow', 'moderate', 'congested', 'blocked'])->default('free_flow');
            $table->integer('overspeed_count')->default(0);
            $table->integer('overload_count')->default(0);
            $table->timestamp('recorded_at');
            $table->timestamps();
        });

        // 3. Variable Message Signs (VMS) Table
        Schema::create('om_vms_messages', function (Blueprint $table) {
            $table->id();
            $table->string('vms_code'); // e.g. VMS-CH05, VMS-CH20, VMS-PLAZA-01
            $table->string('location');
            $table->string('message_line1');
            $table->string('message_line2')->nullable();
            $table->enum('type', ['info', 'warning', 'emergency', 'speed_limit'])->default('info');
            $table->boolean('is_active')->default(true);
            $table->timestamp('updated_by_operator_at')->nullable();
            $table->timestamps();
        });

        // 4. Incidents & Emergency Patrol Table
        Schema::create('om_incidents', function (Blueprint $table) {
            $table->id();
            $table->string('incident_number')->unique();
            $table->string('title');
            $table->string('chainage'); // e.g. Ch 18+400
            $table->enum('direction', ['northbound', 'southbound', 'both'])->default('northbound');
            $table->enum('severity', ['minor', 'major', 'critical'])->default('minor');
            $table->enum('status', ['detected', 'dispatched', 'on_scene', 'cleared', 'closed'])->default('detected');
            $table->string('dispatched_unit')->nullable(); // e.g. Patrol Unit 3, Heavy Wrecker 1
            $table->integer('response_time_minutes')->nullable();
            $table->text('description')->nullable();
            $table->timestamp('reported_at');
            $table->timestamp('cleared_at')->nullable();
            $table->timestamps();
        });

        // 5. Digital Shift Handover Logs Table
        Schema::create('om_shift_logs', function (Blueprint $table) {
            $table->id();
            $table->date('shift_date');
            $table->enum('shift_type', ['morning', 'evening', 'night']);
            $table->string('operator_id')->nullable();
            $table->integer('open_incidents_count')->default(0);
            $table->text('handover_notes')->nullable();
            $table->text('equipment_exceptions')->nullable();
            $table->boolean('is_acknowledged')->default(false);
            $table->timestamps();
        });

        // 6. Routine & Preventive Maintenance Work Orders Table
        Schema::create('om_work_orders', function (Blueprint $table) {
            $table->id();
            $table->string('work_order_number')->unique();
            $table->string('title');
            $table->enum('category', ['pavement', 'guardrail', 'lighting', 'drainage', 'bridge', 'signage'])->default('pavement');
            $table->string('location'); // Chainage or plaza
            $table->enum('priority', ['low', 'medium', 'high', 'emergency'])->default('medium');
            $table->enum('status', ['pending', 'assigned', 'in_progress', 'completed', 'verified'])->default('pending');
            $table->string('assigned_to')->nullable();
            $table->text('description')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        // 7. Equipment & Facilities Uptime Table
        Schema::create('om_equipment_status', function (Blueprint $table) {
            $table->id();
            $table->string('equipment_code')->unique();
            $table->string('name');
            $table->enum('category', ['cctv', 'vms', 'wim', 'etc_reader', 'generator', 'sos_box'])->default('cctv');
            $table->string('location');
            $table->enum('status', ['online', 'degraded', 'offline', 'maintenance'])->default('online');
            $table->decimal('uptime_pct', 5, 2)->default(99.80);
            $table->timestamp('last_ping_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('om_equipment_status');
        Schema::dropIfExists('om_work_orders');
        Schema::dropIfExists('om_shift_logs');
        Schema::dropIfExists('om_incidents');
        Schema::dropIfExists('om_vms_messages');
        Schema::dropIfExists('om_traffic_logs');
        Schema::dropIfExists('om_toll_records');
    }
};
