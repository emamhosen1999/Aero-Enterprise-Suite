<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 2026_06_04_164754 added the 'downloaded' punch_status only for MySQL — it is a
 * no-op on every other driver. On SQLite (used by the test suite) the original
 * ENUM survives as a CHECK constraint that rejects 'downloaded', so the capture
 * half of the biometric download flow cannot even be exercised there.
 *
 * Relax punch_status to a plain string on non-MySQL drivers. MySQL is left
 * completely untouched: its ENUM already carries 'downloaded'.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            return;
        }

        Schema::table('biometric_att_logs', function (Blueprint $table) {
            $table->string('punch_status')->default('processed')->change();
        });
    }

    public function down(): void
    {
        // Intentionally irreversible: restoring the narrower constraint would
        // orphan any existing 'downloaded' rows.
    }
};
