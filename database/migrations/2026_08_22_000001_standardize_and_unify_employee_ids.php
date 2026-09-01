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
        if (! Schema::hasColumn('users', 'id')) {
            return;
        }

        $mappings = [
            1   => '123',
            3   => '120',
            4   => '126',
            5   => '127',
            6   => '159',
            7   => '7',
            8   => '131',
            9   => '143',
            10  => '1231',
            11  => '1261',
            12  => '142',
            13  => '145',
            14  => '272',
            16  => '356',
            17  => '170',
            18  => '151',
            19  => '152',
            20  => '153',
            21  => '1232',
            22  => '29',
            23  => '122',
            24  => '24',
            25  => '1536',
            26  => '169',
            91  => '130',
            95  => '149',
            96  => '896',
            97  => '97',
            98  => '538',
            99  => '154',
            100 => '155',
            101 => '301',
            102 => '302',
            103 => '304',
            104 => '305',
            105 => '306',
            106 => '397',
            107 => '308',
            108 => '309',
            133 => '310',
            134 => '307',
        ];

        $tablesWithEmpIdCol = [
            'offboardings',
            'onboardings',
            'performance_reviews',
            'safety_incident_participants',
            'safety_training_participants',
        ];

        // 1. Ensure employee_id column length supports strings
        Schema::table('users', function (Blueprint $table) {
            $table->string('employee_id', 50)->nullable()->change();
        });

        // 2. Apply standardized clean Employee IDs
        foreach ($mappings as $userId => $newEmpId) {
            $user = DB::table('users')->where('id', $userId)->first();
            if ($user) {
                $oldEmpId = $user->employee_id;
                DB::table('users')->where('id', $userId)->update(['employee_id' => $newEmpId]);

                if ($oldEmpId && $oldEmpId !== $newEmpId) {
                    foreach ($tablesWithEmpIdCol as $tbl) {
                        if (Schema::hasTable($tbl) && Schema::hasColumn($tbl, 'employee_id')) {
                            try {
                                DB::table($tbl)->where('employee_id', (string) $oldEmpId)->update(['employee_id' => $newEmpId]);
                            } catch (\Throwable $e) {
                                // Skip type mismatch on non-string columns
                            }
                        }
                    }
                }
            }
        }

        // 3. Enforce Unique Index on users.employee_id
        try {
            Schema::table('users', function (Blueprint $table) {
                $table->unique('employee_id');
            });
        } catch (\Exception $e) {
            // Unique index may already exist
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['employee_id']);
        });
    }
};
