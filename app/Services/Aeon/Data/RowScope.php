<?php

declare(strict_types=1);

namespace App\Services\Aeon\Data;

use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Schema;

/**
 * Enforces row-level security for Aeon data queries in DBEDC Guardian.
 * Supports "own", "department", and "all" access scopes based on Spatie roles & permissions.
 */
class RowScope
{
    /**
     * Apply row-level scoping to an Eloquent/Query Builder.
     */
    public function apply(Builder $query, string $table, int|string|null $userId): Builder
    {
        if ($userId === null) {
            return $query;
        }

        $user = User::find($userId);
        if (! $user) {
            return $query;
        }

        // Super Administrator bypass
        if ($user->hasRole('Super Administrator') || $user->hasRole('Managing Director') || $user->hasRole('Project Director')) {
            return $query;
        }

        $cols = Schema::getColumnListing($table);

        // Check for personal ownership columns
        $hasUserId = in_array('user_id', $cols, true);
        $hasEmployeeId = in_array('employee_id', $cols, true);
        $hasDepartmentId = in_array('department_id', $cols, true);

        // Department-level scope for HODs / Department Heads
        if ($user->hasRole(['Department Head', 'Manager', 'Incharge']) && $hasDepartmentId && ! empty($user->department_id)) {
            $query->where($table.'.department_id', $user->department_id);
            return $query;
        }

        // Standard user: restricted to own data for user-specific tables (leaves, petty cash, tasks, attendances)
        if ($hasUserId) {
            $query->where($table.'.user_id', $user->id);
        } elseif ($hasEmployeeId && ! empty($user->employee_id)) {
            $query->where($table.'.employee_id', $user->employee_id);
        }

        return $query;
    }
}
