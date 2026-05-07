<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the employees table as the aggregate root for HRM operations.
     * This table represents employee records separate from user authentication.
     */
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();

            // Link to user for authentication (nullable to support pre-user employee records)
            $table->foreignId('user_id')
                ->nullable()
                ->unique()
                ->constrained('users')
                ->onDelete('set null')
                ->comment('User account for authentication (nullable for onboarding in progress)');

            // Core employee information
            $table->string('employee_number')
                ->unique()
                ->comment('Unique employee identifier');

            $table->date('hire_date')
                ->comment('Date employee was hired');

            // Onboarding tracking
            $table->string('onboarding_status')
                ->default('pending')
                ->comment('Onboarding status: pending, in_progress, completed');

            $table->timestamp('onboarding_completed_at')
                ->nullable()
                ->comment('Timestamp when onboarding was completed');

            // Organizational relationships
            $table->foreignId('department_id')
                ->nullable()
                ->constrained('departments')
                ->onDelete('set null')
                ->comment('Department this employee belongs to');

            $table->foreignId('designation_id')
                ->nullable()
                ->constrained('designations')
                ->onDelete('set null')
                ->comment('Job designation/position');

            $table->foreignId('attendance_type_id')
                ->nullable()
                ->constrained('attendance_types')
                ->onDelete('set null')
                ->comment('Attendance tracking type for this employee');

            // Reporting structure
            $table->foreignId('report_to_employee_id')
                ->nullable()
                ->constrained('employees')
                ->onDelete('set null')
                ->comment('Supervisor/manager employee ID');

            // Employment details
            $table->string('employment_type')
                ->default('full-time')
                ->comment('Employment type: full-time, part-time, contract, intern');

            $table->string('status')
                ->default('active')
                ->comment('Employment status: active, on_leave, terminated, resigned');

            // Contract/probation tracking
            $table->date('probation_end_date')
                ->nullable()
                ->comment('End date of probation period');

            $table->date('contract_end_date')
                ->nullable()
                ->comment('End date of contract (for contract employees)');

            // Additional information
            $table->text('notes')
                ->nullable()
                ->comment('Additional notes about the employee');

            // Standard timestamps
            $table->timestamps();
            $table->softDeletes();

            // Indexes for performance
            $table->index('employee_number', 'idx_employee_number');
            $table->index('status', 'idx_employee_status');
            $table->index('onboarding_status', 'idx_onboarding_status');
            $table->index('employment_type', 'idx_employment_type');
            $table->index(['department_id', 'status'], 'idx_dept_status');
            $table->index(['designation_id', 'status'], 'idx_designation_status');
            $table->index('hire_date', 'idx_hire_date');
        });

        // Add index for reporting hierarchy queries
        Schema::table('employees', function (Blueprint $table) {
            $table->index('report_to_employee_id', 'idx_reporting_manager');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
