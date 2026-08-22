<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('personal_access_tokens')) {
            return;
        }

        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            try {
                // Drop index if needed or directly modify column
                DB::statement('ALTER TABLE `personal_access_tokens` MODIFY COLUMN `tokenable_id` VARCHAR(50) NOT NULL');
            } catch (\Throwable $e) {
                // Fallback for different MySQL modes
                Schema::table('personal_access_tokens', function (Blueprint $table) {
                    $table->string('tokenable_id', 50)->change();
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('personal_access_tokens')) {
            return;
        }

        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            try {
                DB::statement('ALTER TABLE `personal_access_tokens` MODIFY COLUMN `tokenable_id` BIGINT UNSIGNED NOT NULL');
            } catch (\Throwable $e) {}
        }
    }
};
