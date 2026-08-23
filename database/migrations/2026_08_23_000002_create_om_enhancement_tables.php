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
        // 1. Add escalation fields to om_incidents
        if (Schema::hasTable('om_incidents')) {
            Schema::table('om_incidents', function (Blueprint $table) {
                if (! Schema::hasColumn('om_incidents', 'reported_by')) {
                    $table->unsignedBigInteger('reported_by')->nullable()->after('description');
                }
                if (! Schema::hasColumn('om_incidents', 'escalation_level')) {
                    $table->unsignedTinyInteger('escalation_level')->default(0)->after('reported_by');
                }
                if (! Schema::hasColumn('om_incidents', 'escalated_at')) {
                    $table->timestamp('escalated_at')->nullable()->after('escalation_level');
                }
                if (! Schema::hasColumn('om_incidents', 'escalated_by')) {
                    $table->unsignedBigInteger('escalated_by')->nullable()->after('escalated_at');
                }
                if (! Schema::hasColumn('om_incidents', 'escalation_notes')) {
                    $table->text('escalation_notes')->nullable()->after('escalated_by');
                }
            });
        }

        // 2. Add assignment/verification fields to om_work_orders
        if (Schema::hasTable('om_work_orders')) {
            Schema::table('om_work_orders', function (Blueprint $table) {
                if (! Schema::hasColumn('om_work_orders', 'reported_by')) {
                    $table->unsignedBigInteger('reported_by')->nullable()->after('description');
                }
                if (! Schema::hasColumn('om_work_orders', 'assigned_by')) {
                    $table->unsignedBigInteger('assigned_by')->nullable()->after('reported_by');
                }
                if (! Schema::hasColumn('om_work_orders', 'verified_by')) {
                    $table->unsignedBigInteger('verified_by')->nullable()->after('assigned_by');
                }
                if (! Schema::hasColumn('om_work_orders', 'verified_at')) {
                    $table->timestamp('verified_at')->nullable()->after('verified_by');
                }
            });
        }

        // 3. Add issue reporting fields to om_equipment_status
        if (Schema::hasTable('om_equipment_status')) {
            Schema::table('om_equipment_status', function (Blueprint $table) {
                if (! Schema::hasColumn('om_equipment_status', 'reported_by')) {
                    $table->unsignedBigInteger('reported_by')->nullable()->after('last_ping_at');
                }
                if (! Schema::hasColumn('om_equipment_status', 'issue_description')) {
                    $table->text('issue_description')->nullable()->after('reported_by');
                }
                if (! Schema::hasColumn('om_equipment_status', 'issue_reported_at')) {
                    $table->timestamp('issue_reported_at')->nullable()->after('issue_description');
                }
            });
        }

        // 4. Incident Photos Table
        if (! Schema::hasTable('om_incident_photos')) {
            Schema::create('om_incident_photos', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('incident_id');
                $table->string('photo_path');
                $table->unsignedBigInteger('uploaded_by')->nullable();
                $table->timestamps();

                $table->index('incident_id');
                $table->index('uploaded_by');
            });
        }

        // 5. Work Order Photos Table
        if (! Schema::hasTable('om_work_order_photos')) {
            Schema::create('om_work_order_photos', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('work_order_id');
                $table->string('photo_path');
                $table->unsignedBigInteger('uploaded_by')->nullable();
                $table->timestamps();

                $table->index('work_order_id');
                $table->index('uploaded_by');
            });
        }

        // 6. Incident Escalations Table
        if (! Schema::hasTable('om_incident_escalations')) {
            Schema::create('om_incident_escalations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('incident_id');
                $table->unsignedTinyInteger('level');
                $table->unsignedBigInteger('escalated_by')->nullable();
                $table->text('notes')->nullable();
                $table->timestamp('escalated_at');
                $table->timestamps();

                $table->index('incident_id');
                $table->index('escalated_by');
            });
        }

        // 7. Activity Logs Table (generic audit trail for O&M entities)
        if (! Schema::hasTable('om_activity_logs')) {
            Schema::create('om_activity_logs', function (Blueprint $table) {
                $table->id();
                $table->string('entity_type'); // incident, work_order, equipment, shift_log
                $table->unsignedBigInteger('entity_id');
                $table->string('action'); // created, status_changed, escalated, verified, photo_added, etc.
                $table->unsignedBigInteger('user_id')->nullable();
                $table->text('notes')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['entity_type', 'entity_id']);
                $table->index('user_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('om_activity_logs');
        Schema::dropIfExists('om_incident_escalations');
        Schema::dropIfExists('om_work_order_photos');
        Schema::dropIfExists('om_incident_photos');

        if (Schema::hasTable('om_incidents')) {
            Schema::table('om_incidents', function (Blueprint $table) {
                $columns = ['reported_by', 'escalation_level', 'escalated_at', 'escalated_by', 'escalation_notes'];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('om_incidents', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('om_work_orders')) {
            Schema::table('om_work_orders', function (Blueprint $table) {
                $columns = ['reported_by', 'assigned_by', 'verified_by', 'verified_at'];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('om_work_orders', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('om_equipment_status')) {
            Schema::table('om_equipment_status', function (Blueprint $table) {
                $columns = ['reported_by', 'issue_description', 'issue_reported_at'];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('om_equipment_status', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};