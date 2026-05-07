<?php

namespace App\Models\HRM;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Employee Model
 *
 * Represents an employee in the HRM system.
 * This is the aggregate root for all HRM operations.
 * All HRM features operate on Employee, not User directly.
 */
class Employee extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'employee_number',
        'hire_date',
        'onboarding_status',
        'onboarding_completed_at',
        'department_id',
        'designation_id',
        'attendance_type_id',
        'report_to_employee_id',
        'employment_type',
        'status',
        'probation_end_date',
        'contract_end_date',
        'notes',
    ];

    protected $casts = [
        'hire_date' => 'date',
        'onboarding_completed_at' => 'datetime',
        'probation_end_date' => 'date',
        'contract_end_date' => 'date',
    ];

    /**
     * Valid onboarding statuses
     */
    public const ONBOARDING_PENDING = 'pending';

    public const ONBOARDING_IN_PROGRESS = 'in_progress';

    public const ONBOARDING_COMPLETED = 'completed';

    /**
     * Valid employment statuses
     */
    public const STATUS_ACTIVE = 'active';

    public const STATUS_ON_LEAVE = 'on_leave';

    public const STATUS_TERMINATED = 'terminated';

    public const STATUS_RESIGNED = 'resigned';

    /**
     * Valid employment types
     */
    public const TYPE_FULL_TIME = 'full-time';

    public const TYPE_PART_TIME = 'part-time';

    public const TYPE_CONTRACT = 'contract';

    public const TYPE_INTERN = 'intern';

    // ============================================
    // RELATIONSHIPS
    // ============================================

    /**
     * Get the user associated with this employee.
     * Employee → User (indirect access to authentication)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the department this employee belongs to.
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Get the designation of this employee.
     */
    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class);
    }

    /**
     * Get the attendance type for this employee.
     */
    public function attendanceType(): BelongsTo
    {
        return $this->belongsTo(AttendanceType::class);
    }

    /**
     * Get the supervisor (reporting manager) of this employee.
     */
    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'report_to_employee_id');
    }

    /**
     * Get all subordinates (employees reporting to this employee).
     */
    public function subordinates(): HasMany
    {
        return $this->hasMany(Employee::class, 'report_to_employee_id');
    }

    /**
     * Get all attendance records for this employee.
     */
    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    /**
     * Get all leave records for this employee.
     */
    public function leaves(): HasMany
    {
        return $this->hasMany(Leave::class);
    }

    /**
     * Get all payroll records for this employee.
     */
    public function payrolls(): HasMany
    {
        return $this->hasMany(Payroll::class);
    }

    /**
     * Get all onboarding processes for this employee.
     */
    public function onboardings(): HasMany
    {
        return $this->hasMany(Onboarding::class);
    }

    /**
     * Get all offboarding processes for this employee.
     */
    public function offboardings(): HasMany
    {
        return $this->hasMany(Offboarding::class);
    }

    /**
     * Get all performance reviews for this employee.
     */
    public function performanceReviews(): HasMany
    {
        return $this->hasMany(PerformanceReview::class);
    }

    /**
     * Get all training enrollments for this employee.
     */
    public function trainingEnrollments(): HasMany
    {
        return $this->hasMany(TrainingEnrollment::class);
    }

    // ============================================
    // SCOPES
    // ============================================

    /**
     * Scope to get only active employees.
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope to get only onboarded employees.
     */
    public function scopeOnboarded($query)
    {
        return $query->where('onboarding_status', self::ONBOARDING_COMPLETED)
            ->whereNotNull('onboarding_completed_at');
    }

    /**
     * Scope to get employees by department.
     */
    public function scopeInDepartment($query, $departmentId)
    {
        return $query->where('department_id', $departmentId);
    }

    /**
     * Scope to get employees by employment type.
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('employment_type', $type);
    }

    // ============================================
    // HELPER METHODS
    // ============================================

    /**
     * Check if employee has completed onboarding.
     */
    public function isOnboarded(): bool
    {
        return $this->onboarding_status === self::ONBOARDING_COMPLETED
            && $this->onboarding_completed_at !== null;
    }

    /**
     * Check if employee is currently active.
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Check if employee can access HRM features.
     * Requires both onboarded status and active status.
     */
    public function canAccessHrm(): bool
    {
        return $this->isOnboarded() && $this->isActive();
    }

    /**
     * Check if employee is in probation period.
     */
    public function isInProbation(): bool
    {
        return $this->probation_end_date !== null
            && $this->probation_end_date->isFuture();
    }

    /**
     * Check if employee is on contract.
     */
    public function isOnContract(): bool
    {
        return $this->employment_type === self::TYPE_CONTRACT;
    }

    /**
     * Get full name from associated user.
     */
    public function getFullName(): string
    {
        return $this->user->name ?? 'N/A';
    }

    /**
     * Get email from associated user.
     */
    public function getEmail(): ?string
    {
        return $this->user->email ?? null;
    }

    /**
     * Mark employee onboarding as completed.
     */
    public function completeOnboarding(): bool
    {
        $this->onboarding_status = self::ONBOARDING_COMPLETED;
        $this->onboarding_completed_at = now();

        return $this->save();
    }

    /**
     * Activate employee status.
     */
    public function activate(): bool
    {
        $this->status = self::STATUS_ACTIVE;

        return $this->save();
    }

    /**
     * Deactivate employee (terminate/resign).
     */
    public function deactivate(string $reason = self::STATUS_TERMINATED): bool
    {
        $this->status = $reason;

        return $this->save();
    }
}
