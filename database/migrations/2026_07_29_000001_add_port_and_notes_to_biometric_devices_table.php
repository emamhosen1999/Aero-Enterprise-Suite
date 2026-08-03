<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `port` and `notes` have been listed in BiometricDevice::$fillable since the
     * model was written, but the columns were never actually created — so the
     * fields were unreachable in two ways at once (no validation rule in the
     * controller AND no column to write to). Adding validation alone would turn
     * a silently-dropped field into a hard SQL error, so the column has to land
     * with it.
     *
     * `port` is the device's TCP service port (ZKTeco defaults to 4370) and is
     * nullable because ADMS devices push to us and never need it.
     */
    public function up(): void
    {
        Schema::table('biometric_devices', function (Blueprint $table) {
            if (! Schema::hasColumn('biometric_devices', 'port')) {
                $table->unsignedSmallInteger('port')->nullable()->after('ip_address');
            }
            if (! Schema::hasColumn('biometric_devices', 'notes')) {
                $table->text('notes')->nullable()->after('config');
            }
        });
    }

    public function down(): void
    {
        Schema::table('biometric_devices', function (Blueprint $table) {
            $table->dropColumn(['port', 'notes']);
        });
    }
};
