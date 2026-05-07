# HRM Architecture Compliance - Implementation Plan

**Status:** Ready for Implementation  
**Estimated Effort:** 6 weeks  
**Risk Level:** HIGH - Requires database migrations and extensive refactoring  

---

## Phase 1: Foundation - Employee Model Creation (Week 1)

### 1.1 Create Employee Model

**File:** `/app/Models/HRM/Employee.php`

```php
<?php

namespace App\Models\HRM;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

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
        'employment_type', // full-time, part-time, contract, intern
        'status', // active, on_leave, terminated, resigned
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

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class);
    }

    public function attendanceType(): BelongsTo
    {
        return $this->belongsTo(AttendanceType::class);
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'report_to_employee_id');
    }

    public function subordinates(): HasMany
    {
        return $this->hasMany(Employee::class, 'report_to_employee_id');
    }

    // HRM Relationships
    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function leaves(): HasMany
    {
        return $this->hasMany(Leave::class);
    }

    public function payrolls(): HasMany
    {
        return $this->hasMany(Payroll::class);
    }

    public function onboardings(): HasMany
    {
        return $this->hasMany(Onboarding::class);
    }

    public function offboardings(): HasMany
    {
        return $this->hasMany(Offboarding::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeOnboarded($query)
    {
        return $query->where('onboarding_status', 'completed')
            ->whereNotNull('onboarding_completed_at');
    }

    // Helpers
    public function isOnboarded(): bool
    {
        return $this->onboarding_status === 'completed' && $this->onboarding_completed_at !== null;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function canAccessHrm(): bool
    {
        return $this->isOnboarded() && $this->isActive();
    }
}
```

### 1.2 Create Migration

**File:** `/database/migrations/2026_01_11_120000_create_employees_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->onDelete('set null');
            $table->string('employee_number')->unique();
            $table->date('hire_date');
            $table->string('onboarding_status')->default('pending'); // pending, in_progress, completed
            $table->timestamp('onboarding_completed_at')->nullable();
            $table->foreignId('department_id')->nullable()->constrained('departments')->onDelete('set null');
            $table->foreignId('designation_id')->nullable()->constrained('designations')->onDelete('set null');
            $table->foreignId('attendance_type_id')->nullable()->constrained('attendance_types')->onDelete('set null');
            $table->foreignId('report_to_employee_id')->nullable()->constrained('employees')->onDelete('set null');
            $table->string('employment_type')->default('full-time'); // full-time, part-time, contract, intern
            $table->string('status')->default('active'); // active, on_leave, terminated, resigned
            $table->date('probation_end_date')->nullable();
            $table->date('contract_end_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('employee_number');
            $table->index('status');
            $table->index('onboarding_status');
            $table->index(['department_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
```

### 1.3 Update User Model

Add relationship to User model:

```php
// In App\Models\User

public function employee(): HasOne
{
    return $this->hasOne(Employee::class);
}

public function isEmployee(): bool
{
    return $this->employee !== null;
}

public function canAccessHrm(): bool
{
    return $this->isEmployee() && $this->employee->canAccessHrm();
}
```

---

## Phase 2: Middleware & Guards (Week 1)

### 2.1 Create Employee Onboarding Middleware

**File:** `/app/Http/Middleware/EnsureEmployeeOnboarded.php`

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmployeeOnboarded
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('login');
        }

        // Check if user has an employee record
        if (!$user->isEmployee()) {
            abort(403, 'You must be onboarded as an employee to access this feature.');
        }

        // Check if employee is onboarded
        if (!$user->employee->canAccessHrm()) {
            abort(403, 'Your employee onboarding is not complete. Please contact HR.');
        }

        return $next($request);
    }
}
```

### 2.2 Register Middleware

Update `/app/Http/Kernel.php` or `/bootstrap/app.php` (Laravel 11):

```php
protected $middlewareAliases = [
    // ... existing
    'employee.onboarded' => \App\Http\Middleware\EnsureEmployeeOnboarded::class,
];
```

---

## Phase 3: Update HRM Models (Week 2)

### 3.1 Update All HRM Models

For each model in `/app/Models/HRM/`, replace User references with Employee references.

**Example for Attendance.php:**

```php
// BEFORE
use App\Models\User;

public function user(): BelongsTo
{
    return $this->belongsTo(User::class);
}

// AFTER
use App\Models\HRM\Employee;

public function employee(): BelongsTo
{
    return $this->belongsTo(Employee::class);
}
```

**Files to update (32 total):**
1. Attendance.php
2. AttendanceType.php
3. Department.php
4. Designation.php
5. HrDocument.php
6. Job.php
7. JobApplication.php
8. JobApplicationStageHistory.php
9. JobInterview.php
10. JobInterviewFeedback.php
11. JobOffer.php
12. KPI.php
13. KPIValue.php
14. Leave.php
15. Offboarding.php
16. OffboardingTask.php
17. Onboarding.php
18. OnboardingTask.php
19. Opportunity.php
20. Payroll.php
21. PayrollAllowance.php
22. PayrollDeduction.php
23. Payslip.php
24. PerformanceReview.php
25. PerformanceReviewTemplate.php
26. Training.php
27. TrainingAssignment.php
28. TrainingAssignmentSubmission.php
29. TrainingCategory.php
30. TrainingEnrollment.php
31. TrainingFeedback.php
32. TrainingMaterial.php
33. TrainingSession.php

### 3.2 Update Migrations

Update foreign key constraints in HRM migrations:

```php
// BEFORE
$table->foreignId('employee_id')->constrained('users');

// AFTER
$table->foreignId('employee_id')->constrained('employees');
```

**Migrations to update:**
- `2025_07_08_000001_create_onboarding_offboarding_tables.php`
- `2024_07_31_000001_create_performance_management_tables.php`
- `2025_07_08_000004_create_workplace_safety_tables.php`
- All other HRM-related migrations

---

## Phase 4: Update Controllers (Week 3)

### 4.1 Refactor HR Controllers

Replace all User queries with Employee queries and use ModulePermissionService.

**Example for PayrollController.php:**

```php
// BEFORE
use App\Models\User;

public function create()
{
    $employees = User::role('Employee')->select('id', 'name', 'employee_id', 'email')->get();
    // ...
}

// AFTER
use App\Models\HRM\Employee;
use App\Services\Module\ModulePermissionService;

protected ModulePermissionService $modulePermissionService;

public function __construct(ModulePermissionService $modulePermissionService)
{
    $this->modulePermissionService = $modulePermissionService;
}

public function create()
{
    // Check module access instead of role
    if (!$this->modulePermissionService->userCanAccessComponent('hrm', 'payroll', 'create')) {
        abort(403, 'Unauthorized access to payroll creation');
    }

    $employees = Employee::active()
        ->onboarded()
        ->with('user')
        ->get()
        ->map(fn($emp) => [
            'id' => $emp->id,
            'name' => $emp->user->name ?? 'N/A',
            'employee_number' => $emp->employee_number,
            'email' => $emp->user->email ?? null,
        ]);
    // ...
}
```

**Controllers to update (8 total):**
1. ManagersController.php
2. SafetyIncidentController.php
3. OnboardingController.php
4. WorkplaceSafetyController.php
5. PayrollController.php
6. PerformanceReviewController.php
7. TrainingController.php
8. RecruitmentController.php

### 4.2 Apply Middleware to Routes

Update `/routes/web.php` or route files:

```php
Route::middleware(['auth', 'employee.onboarded'])->prefix('hr')->group(function () {
    Route::resource('payroll', PayrollController::class);
    Route::resource('onboarding', OnboardingController::class);
    // ... all HRM routes
});
```

---

## Phase 5: Update Services (Week 4)

### 5.1 Refactor Services to Use Employee

Update all 15+ services that currently use User to use Employee instead.

**Files to update:**
1. PayrollCalculationService.php
2. LeaveApprovalService.php
3. LeaveSummaryService.php
4. BulkLeaveService.php
5. ProfileCrudService.php (if HRM-related)
6. ProfileMediaService.php (if HRM-related)
7. ProfileUpdateService.php (if HRM-related)
8. TaskCrudService.php (if HRM-related)
9. TaskNotificationService.php (if HRM-related)
10. DailyWorkImportService.php (if HRM-related)
11. DailyWorkPaginationService.php (if HRM-related)
12. Others as needed

---

## Phase 6: Create Contracts & DTOs (Week 5)

### 6.1 Create Employee Contract

**File:** `/app/Contracts/EmployeeContract.php`

```php
<?php

namespace App\Contracts;

interface EmployeeContract
{
    public function getId(): int;
    public function getEmployeeNumber(): string;
    public function getUserId(): ?int;
    public function getFullName(): string;
    public function getEmail(): ?string;
    public function isOnboarded(): bool;
    public function isActive(): bool;
    public function canAccessHrm(): bool;
}
```

### 6.2 Create Employee DTO

**File:** `/app/DTOs/EmployeeDTO.php`

```php
<?php

namespace App\DTOs;

class EmployeeDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $employeeNumber,
        public readonly ?int $userId,
        public readonly string $fullName,
        public readonly ?string $email,
        public readonly bool $isOnboarded,
        public readonly bool $isActive,
    ) {}

    public static function fromEmployee($employee): self
    {
        return new self(
            id: $employee->id,
            employeeNumber: $employee->employee_number,
            userId: $employee->user_id,
            fullName: $employee->user->name ?? 'N/A',
            email: $employee->user->email ?? null,
            isOnboarded: $employee->isOnboarded(),
            isActive: $employee->isActive(),
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'employee_number' => $this->employeeNumber,
            'user_id' => $this->userId,
            'full_name' => $this->fullName,
            'email' => $this->email,
            'is_onboarded' => $this->isOnboarded,
            'is_active' => $this->isActive,
        ];
    }
}
```

### 6.3 Create Domain Events

**File:** `/app/Events/HRM/EmployeeOnboarded.php`

```php
<?php

namespace App\Events\HRM;

use App\Models\HRM\Employee;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EmployeeOnboarded
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Employee $employee,
        public \DateTimeInterface $onboardedAt
    ) {}
}
```

---

## Phase 7: Testing (Week 6)

### 7.1 Create Architecture Tests

**File:** `/tests/Unit/Architecture/HrmEmployeeArchitectureTest.php`

```php
<?php

namespace Tests\Unit\Architecture;

use Tests\TestCase;

class HrmEmployeeArchitectureTest extends TestCase
{
    /** @test */
    public function hrm_controllers_do_not_import_user_model()
    {
        $files = glob(base_path('app/Http/Controllers/HR/*.php'));
        
        foreach ($files as $file) {
            $content = file_get_contents($file);
            $this->assertStringNotContainsString(
                'use App\Models\User;',
                $content,
                basename($file) . ' should not import User model directly'
            );
        }
    }

    /** @test */
    public function hrm_models_do_not_import_user_model()
    {
        $files = glob(base_path('app/Models/HRM/*.php'));
        
        foreach ($files as $file) {
            if (basename($file) === 'Employee.php') continue;
            
            $content = file_get_contents($file);
            $this->assertStringNotContainsString(
                'use App\Models\User;',
                $content,
                basename($file) . ' should not import User model directly'
            );
        }
    }

    /** @test */
    public function no_hardcoded_role_checks_in_hr_controllers()
    {
        $files = glob(base_path('app/Http/Controllers/HR/*.php'));
        
        foreach ($files as $file) {
            $content = file_get_contents($file);
            $this->assertStringNotContainsString(
                '->role(',
                $content,
                basename($file) . ' should not use hardcoded role checks'
            );
        }
    }
}
```

### 7.2 Create Functional Tests

**File:** `/tests/Feature/HRM/EmployeeOnboardingEnforcementTest.php`

```php
<?php

namespace Tests\Feature\HRM;

use App\Models\User;
use App\Models\HRM\Employee;
use Tests\TestCase;

class EmployeeOnboardingEnforcementTest extends TestCase
{
    /** @test */
    public function user_without_employee_record_cannot_access_hrm()
    {
        $user = User::factory()->create();
        
        $response = $this->actingAs($user)->get('/hr/payroll');
        
        $response->assertStatus(403);
        $response->assertSee('must be onboarded as an employee');
    }

    /** @test */
    public function employee_without_completed_onboarding_cannot_access_hrm()
    {
        $user = User::factory()->create();
        $employee = Employee::factory()->create([
            'user_id' => $user->id,
            'onboarding_status' => 'pending',
            'onboarding_completed_at' => null,
        ]);
        
        $response = $this->actingAs($user)->get('/hr/payroll');
        
        $response->assertStatus(403);
        $response->assertSee('onboarding is not complete');
    }

    /** @test */
    public function onboarded_employee_can_access_hrm()
    {
        $user = User::factory()->create();
        $employee = Employee::factory()->create([
            'user_id' => $user->id,
            'onboarding_status' => 'completed',
            'onboarding_completed_at' => now(),
        ]);
        
        $response = $this->actingAs($user)->get('/hr/dashboard');
        
        $response->assertStatus(200);
    }
}
```

---

## Risk Mitigation

### Database Migration Strategy

1. **Preserve existing data:**
   ```php
   // Migration to migrate existing User-based HRM data
   Schema::table('onboardings', function (Blueprint $table) {
       $table->foreignId('employee_id_new')->nullable()->after('employee_id')->constrained('employees');
   });
   
   // Data migration script
   DB::table('onboardings')->chunkById(100, function ($onboardings) {
       foreach ($onboardings as $onboarding) {
           $employee = Employee::where('user_id', $onboarding->employee_id)->first();
           if ($employee) {
               DB::table('onboardings')
                   ->where('id', $onboarding->id)
                   ->update(['employee_id_new' => $employee->id]);
           }
       }
   });
   
   // Drop old column, rename new
   Schema::table('onboardings', function (Blueprint $table) {
       $table->dropForeign(['employee_id']);
       $table->dropColumn('employee_id');
       $table->renameColumn('employee_id_new', 'employee_id');
   });
   ```

2. **Test migrations in staging first**
3. **Create rollback plan**
4. **Backup database before migration**

### Deployment Strategy

1. Deploy Employee model first (no breaking changes)
2. Deploy middleware (disabled initially via config)
3. Deploy refactored models (one at a time)
4. Deploy refactored controllers (one at a time)
5. Enable middleware last
6. Monitor for errors

---

## Success Criteria

- ✅ Employee model exists and is used throughout HRM
- ✅ Zero direct User references in HRM package
- ✅ Zero hardcoded role checks in HRM controllers
- ✅ Onboarding enforcement active on all HRM routes
- ✅ ModulePermissionService used for all authorization
- ✅ All tests passing
- ✅ Architecture tests enforcing rules

---

## Timeline Summary

| Week | Phase | Deliverables |
|------|-------|-------------|
| 1 | Foundation | Employee model, migration, middleware |
| 2 | Models | 32 HRM models refactored |
| 3 | Controllers | 8 HR controllers refactored, routes updated |
| 4 | Services | 15+ services refactored |
| 5 | Contracts | DTOs, contracts, events created |
| 6 | Testing | Architecture tests, functional tests |

**Total Effort:** 6 weeks (1 developer full-time)

---

**Document Version:** 1.0  
**Last Updated:** 2026-01-11  
**Status:** Ready for Implementation
