<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensure Employee Onboarded Middleware
 *
 * This middleware enforces that users must have a completed employee onboarding
 * before accessing HRM features. This is a critical architectural guard.
 *
 * Usage: Apply to all HRM routes
 * Route::middleware(['auth', 'employee.onboarded'])->group(...)
 */
class EnsureEmployeeOnboarded
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // User must be authenticated
        if (! $user) {
            return redirect()->route('login')
                ->with('error', 'You must be logged in to access this feature.');
        }

        // Check if user has an employee record
        if (! $user->isEmployee()) {
            abort(403, 'You must be onboarded as an employee to access HRM features. Please contact your HR department.');
        }

        // Check if employee has completed onboarding
        $employee = $user->employee;

        if (! $employee->canAccessHrm()) {
            $message = 'Your employee onboarding is not complete';

            if ($employee->onboarding_status === 'pending') {
                $message .= ' (Status: Pending)';
            } elseif ($employee->onboarding_status === 'in_progress') {
                $message .= ' (Status: In Progress)';
            }

            if ($employee->status !== 'active') {
                $message .= '. Your employment status is: '.$employee->status;
            }

            $message .= '. Please contact HR for assistance.';

            abort(403, $message);
        }

        // All checks passed, allow access
        return $next($request);
    }
}
