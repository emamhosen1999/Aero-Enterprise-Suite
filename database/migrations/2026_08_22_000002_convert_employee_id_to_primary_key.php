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
        if (Schema::hasColumn('users', 'employee_id') && !Schema::hasColumn('users', 'id')) {
            // Already converted on live database
            return;
        }

        $driver = DB::connection()->getDriverName();
        if ($driver !== 'mysql') {
            return;
        }

        $users = DB::table('users')->select('id', 'employee_id')->get();
        $idToEmpIdMap = [];
        foreach ($users as $u) {
            if ($u->employee_id) {
                $idToEmpIdMap[$u->id] = (string)$u->employee_id;
            }
        }

        $allTables = DB::select("SHOW TABLES");
        $dbName = DB::connection()->getDatabaseName();
        $tableKey = "Tables_in_" . $dbName;

        $userRelatedTables = [];

        foreach ($allTables as $tRow) {
            $tbl = $tRow->$tableKey;
            if ($tbl === 'users' || $tbl === 'migrations' || $tbl === 'password_reset_tokens' || $tbl === 'sessions' || $tbl === 'cache' || $tbl === 'cache_locks' || $tbl === 'jobs' || $tbl === 'failed_jobs') {
                continue;
            }

            $cols = Schema::getColumnListing($tbl);
            $userIdCols = [];

            foreach ($cols as $c) {
                $lc = strtolower($c);
                if ($lc === 'user_id' || $lc === 'created_by' || $lc === 'updated_by' || $lc === 'approved_by' || $lc === 'report_to' || $lc === 'manager_id' || $lc === 'supervisor_id' || $lc === 'author_id' || $lc === 'requested_by' || $lc === 'assigned_to' || $lc === 'action_by' || $lc === 'verified_by' || $lc === 'participant_id' || str_ends_with($lc, '_user_id')) {
                    $userIdCols[] = $c;
                }
            }

            if (!empty($userIdCols)) {
                $userRelatedTables[$tbl] = $userIdCols;
            }
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // Drop FK constraints referencing users(id)
        $foreignKeys = DB::select("
            SELECT TABLE_NAME, CONSTRAINT_NAME 
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
            WHERE REFERENCED_TABLE_SCHEMA = '$dbName' 
              AND REFERENCED_TABLE_NAME = 'users'
        ");

        foreach ($foreignKeys as $fk) {
            try {
                DB::statement("ALTER TABLE `{$fk->TABLE_NAME}` DROP FOREIGN KEY `{$fk->CONSTRAINT_NAME}`");
            } catch (\Exception $ex) {}
        }

        // Update foreign key values in dependent tables
        foreach ($userRelatedTables as $tbl => $cols) {
            foreach ($cols as $col) {
                try {
                    DB::statement("ALTER TABLE `{$tbl}` MODIFY COLUMN `{$col}` VARCHAR(50) NULL");
                } catch (\Exception $ex) {}

                foreach ($idToEmpIdMap as $intId => $empId) {
                    DB::table($tbl)->where($col, $intId)->update([$col => $empId]);
                }
            }
        }

        // Handle report_to on users
        if (Schema::hasColumn('users', 'report_to')) {
            DB::statement("ALTER TABLE `users` MODIFY COLUMN `report_to` VARCHAR(50) NULL");
            foreach ($idToEmpIdMap as $intId => $empId) {
                DB::table('users')->where('report_to', $intId)->update(['report_to' => $empId]);
            }
        }

        // Convert users Primary Key: drop id, set employee_id as Primary Key
        try {
            DB::statement("ALTER TABLE `users` MODIFY COLUMN `id` bigint unsigned NOT NULL");
        } catch (\Exception $ex) {}

        try {
            DB::statement("ALTER TABLE `users` DROP PRIMARY KEY");
        } catch (\Exception $ex) {}

        try {
            Schema::table('users', function ($table) {
                $table->dropUnique(['employee_id']);
            });
        } catch (\Exception $ex) {}

        if (Schema::hasColumn('users', 'id')) {
            Schema::table('users', function ($table) {
                $table->dropColumn('id');
            });
        }

        DB::statement("ALTER TABLE `users` ADD PRIMARY KEY (`employee_id`)");
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Primary key reversal not recommended in production
    }
};
