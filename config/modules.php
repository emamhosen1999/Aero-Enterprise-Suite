<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | DBEDC Guardian Module Registry
    |--------------------------------------------------------------------------
    |
    | Maps Guardian's primary sections, functional routes, primary tables,
    | and domain metadata for Aeon's knowledge base and navigation resolver.
    |
    */

    'dashboard' => [
        'name' => 'Command Center & Dashboard',
        'code' => 'dashboard',
        'route' => '/dashboard',
        'description' => 'Main expressway operations command center, summary statistics, real-time alerts, and executive metrics.',
        'keywords' => ['dashboard', 'home', 'overview', 'metrics', 'summary', 'command center'],
        'tables' => [],
    ],

    'daily_works' => [
        'name' => 'Site Daily Works & RFIs',
        'code' => 'daily_works',
        'route' => '/daily-works-unified',
        'description' => 'Site execution daily work logs, Requests for Information (RFIs), inspection files, and construction progress.',
        'keywords' => ['daily work', 'site works', 'rfi', 'inspections', 'construction log', 'execution', 'site progress'],
        'tables' => ['daily_works', 'daily_work_summaries', 'rfi_files'],
    ],

    'objections' => [
        'name' => 'RFI Objections & Resolutions',
        'code' => 'objections',
        'route' => '/workspace/objections',
        'description' => 'Technical objections raised against RFIs, engineer remarks, resolution statuses, and clearance files.',
        'keywords' => ['objections', 'rfi objections', 'remarks', 'hold points', 'qc rejection', 'objection files'],
        'tables' => ['objections', 'rfi_objections'],
    ],

    'ncrs' => [
        'name' => 'Non-Conformance Reports (NCR)',
        'code' => 'ncrs',
        'route' => '/ncrs',
        'description' => 'Quality non-conformance reports, severity classifications, corrective actions, and closeout tracking.',
        'keywords' => ['ncr', 'non conformance', 'quality violation', 'defect', 'corrective action', 'remedial work'],
        'tables' => ['ncrs'],
    ],

    'om_dashboard' => [
        'name' => 'Operations & Maintenance (O&M)',
        'code' => 'om',
        'route' => '/om/dashboard',
        'description' => 'Expressway operations dashboard, traffic monitoring center (TMC/ITS), toll plazas, and incident reports.',
        'keywords' => ['om', 'operations', 'maintenance', 'traffic monitoring', 'toll', 'tmc', 'its'],
        'tables' => ['om_incidents', 'om_work_orders', 'om_equipment'],
    ],

    'om_traffic' => [
        'name' => 'Traffic Monitoring Center',
        'code' => 'om_traffic',
        'route' => '/om/traffic-monitoring',
        'description' => 'Live expressway traffic CCTV feeds, congestion monitoring, speed telemetry, and vehicle queues.',
        'keywords' => ['traffic', 'cctv', 'cameras', 'congestion', 'traffic flow', 'speed'],
        'tables' => [],
    ],

    'om_incidents' => [
        'name' => 'Expressway Incidents',
        'code' => 'om_incidents',
        'route' => '/om/incidents',
        'description' => 'Accidents, road obstructions, vehicle breakdowns, emergency responses, and lane closures.',
        'keywords' => ['incident', 'accident', 'breakdown', 'emergency', 'lane block', 'highway patrol'],
        'tables' => ['om_incidents'],
    ],

    'attendance' => [
        'name' => 'Attendance Management',
        'code' => 'attendance',
        'route' => '/attendance',
        'description' => 'Employee daily check-ins, punch logs, present/absent counts, shift adherence, and monthly summaries.',
        'keywords' => ['attendance', 'punches', 'check in', 'present', 'absent', 'late', 'early exit', 'timesheet'],
        'tables' => ['attendances', 'attendance_logs', 'attendance_summaries'],
    ],

    'leaves' => [
        'name' => 'Leave Management',
        'code' => 'leaves',
        'route' => '/leaves',
        'description' => 'Employee leave requests, approvals, leave balances, casual leave, sick leave, and annual entitlements.',
        'keywords' => ['leave', 'vacation', 'sick leave', 'casual leave', 'holiday', 'time off', 'balance'],
        'tables' => ['leaves', 'leave_balances', 'leave_types', 'leave_settings'],
    ],

    'shifts' => [
        'name' => 'Shift & Roster Planning',
        'code' => 'shifts',
        'route' => '/attendance/shifts',
        'description' => 'Work shifts, rotation patterns, duty rosters, coverage requirements, and shift swaps.',
        'keywords' => ['shift', 'roster', 'schedule', 'shift swap', 'duty', 'rotation', 'coverage'],
        'tables' => ['shifts', 'shift_assignments', 'rosters', 'shift_swaps'],
    ],

    'biometrics' => [
        'name' => 'Biometric Devices (ADMS)',
        'code' => 'biometrics',
        'route' => '/settings/biometric-devices',
        'description' => 'Biometric device health, ADMS real-time sync logs, connection status, and device user enrollments.',
        'keywords' => ['biometric', 'device', 'fingerprint', 'face id', 'zkteco', 'adms', 'device logs', 'terminal'],
        'tables' => ['biometric_devices', 'adms_logs'],
    ],

    'employees' => [
        'name' => 'Employee Directory & Profiles',
        'code' => 'employees',
        'route' => '/employees',
        'description' => 'Staff directory, departments, designations, work locations, employee IDs, and contact info.',
        'keywords' => ['employee', 'staff', 'users', 'department', 'designation', 'team', 'personnel'],
        'tables' => ['users', 'departments', 'designations', 'work_locations'],
    ],

    'petty_cash' => [
        'name' => 'Petty Cash & Expense Ledger',
        'code' => 'petty_cash',
        'route' => '/petty-cash',
        'description' => 'Site petty cash requests, loans, voucher expenses, receipts/bill uploads, and approval workflow.',
        'keywords' => ['petty cash', 'expense', 'voucher', 'receipt', 'loan', 'reimbursement', 'ledger', 'cash'],
        'tables' => ['petty_cash_transactions', 'petty_cash_loans', 'petty_cash_categories'],
    ],

    'letters' => [
        'name' => 'Official Letters & Communications',
        'code' => 'letters',
        'route' => '/letters',
        'description' => 'Incoming and outgoing correspondence with Roads & Highways Department (RHD), consultants, and contractors.',
        'keywords' => ['letter', 'correspondence', 'rhd', 'consultant', 'communication', 'memo', 'dispatch'],
        'tables' => ['letters'],
    ],

    'tasks' => [
        'name' => 'Task Management',
        'code' => 'tasks',
        'route' => '/tasks-all',
        'description' => 'Site assignments, engineer task tracking, due dates, and completion status.',
        'keywords' => ['tasks', 'todo', 'assignments', 'due dates', 'site tasks'],
        'tables' => ['tasks'],
    ],

    'roles_permissions' => [
        'name' => 'Roles & Security Permissions',
        'code' => 'roles',
        'route' => '/admin/roles-management',
        'description' => 'Role-based access control, Spatie permissions, security audit logs, and module access control.',
        'keywords' => ['roles', 'permissions', 'access control', 'security', 'rbac', 'admin access'],
        'tables' => ['roles', 'permissions', 'model_has_roles', 'model_has_permissions'],
    ],

    'system_monitoring' => [
        'name' => 'System Health & Monitoring',
        'code' => 'monitoring',
        'route' => '/admin/system-monitoring',
        'description' => 'System error logs, database performance telemetry, queue status, and server diagnostics.',
        'keywords' => ['system monitoring', 'errors', 'logs', 'telemetry', 'health check', 'diagnostics', 'server'],
        'tables' => ['system_errors', 'request_logs'],
    ],
];
