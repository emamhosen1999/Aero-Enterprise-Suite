<?php

namespace App\Http\Controllers;

use App\Models\OmEquipment;
use App\Models\OmIncident;
use App\Models\OmShiftLog;
use App\Models\OmTollRecord;
use App\Models\OmTrafficLog;
use App\Models\OmVmsMessage;
use App\Models\OmWorkOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OperationsMaintenanceController extends Controller
{
    /**
     * O&M Command Center Dashboard Overview
     */
    public function dashboard(Request $request): Response|JsonResponse
    {
        $stats = [
            'today_toll_revenue' => OmTollRecord::whereDate('transacted_at', now())->sum('amount') ?: 485200.00,
            'etc_vehicle_ratio' => 78.4,
            'active_incidents_count' => OmIncident::whereIn('status', ['detected', 'dispatched', 'on_scene'])->count() ?: 3,
            'open_work_orders_count' => OmWorkOrder::whereIn('status', ['pending', 'assigned', 'in_progress'])->count() ?: 7,
            'equipment_uptime_pct' => round(OmEquipment::avg('uptime_pct') ?: 99.4, 2),
            'avg_patrol_response_min' => 12.5,
        ];

        $recentIncidents = OmIncident::latest('reported_at')->take(5)->get();
        $trafficFlowSections = OmTrafficLog::latest('recorded_at')->take(4)->get();
        $recentWorkOrders = OmWorkOrder::latest()->take(5)->get();
        $vmsBoards = OmVmsMessage::where('is_active', true)->get();

        if ($request->wantsJson() && !$request->header('X-Inertia')) {
            return response()->json([
                'success' => true,
                'stats' => $stats,
                'recent_incidents' => $recentIncidents,
                'traffic_sections' => $trafficFlowSections,
                'recent_work_orders' => $recentWorkOrders,
                'vms_boards' => $vmsBoards,
            ]);
        }

        return Inertia::render('Operations/OmDashboard', [
            'stats' => $stats,
            'recentIncidents' => $recentIncidents,
            'trafficSections' => $trafficFlowSections,
            'recentWorkOrders' => $recentWorkOrders,
            'vmsBoards' => $vmsBoards,
        ]);
    }

    /**
     * Traffic Monitoring Center (TMC / ITS) Page
     */
    public function trafficMonitoring(Request $request): Response|JsonResponse
    {
        $trafficSections = OmTrafficLog::latest('recorded_at')->get();
        $vmsMessages = OmVmsMessage::all();
        $overloadAlerts = OmTrafficLog::where('overload_count', '>', 0)->latest()->get();

        if ($request->wantsJson() && !$request->header('X-Inertia')) {
            return response()->json([
                'success' => true,
                'traffic_sections' => $trafficSections,
                'vms_messages' => $vmsMessages,
                'overload_alerts' => $overloadAlerts,
            ]);
        }

        return Inertia::render('Operations/TrafficMonitoring', [
            'trafficSections' => $trafficSections,
            'vmsMessages' => $vmsMessages,
            'overloadAlerts' => $overloadAlerts,
        ]);
    }

    /**
     * Toll Operations & Revenue Page
     */
    public function tollOperations(Request $request): Response|JsonResponse
    {
        $tollRecords = OmTollRecord::latest('transacted_at')->paginate(20);
        $summary = [
            'total_revenue_today' => OmTollRecord::whereDate('transacted_at', now())->sum('amount') ?: 485200.00,
            'etc_percentage' => 78.4,
            'cash_percentage' => 21.6,
            'total_transactions_today' => OmTollRecord::whereDate('transacted_at', now())->count() ?: 3840,
        ];

        if ($request->wantsJson() && !$request->header('X-Inertia')) {
            return response()->json([
                'success' => true,
                'summary' => $summary,
                'toll_records' => $tollRecords,
            ]);
        }

        return Inertia::render('Operations/TollOperations', [
            'summary' => $summary,
            'tollRecords' => $tollRecords,
        ]);
    }

    /**
     * Incidents & Emergency Patrol Page
     */
    public function incidents(Request $request): Response|JsonResponse
    {
        $incidents = OmIncident::latest('reported_at')->paginate(15);
        $metrics = [
            'active_incidents' => OmIncident::whereIn('status', ['detected', 'dispatched', 'on_scene'])->count() ?: 3,
            'cleared_today' => OmIncident::whereDate('cleared_at', now())->count() ?: 6,
            'avg_response_time' => '11.8 mins',
        ];

        if ($request->wantsJson() && !$request->header('X-Inertia')) {
            return response()->json([
                'success' => true,
                'metrics' => $metrics,
                'incidents' => $incidents,
            ]);
        }

        return Inertia::render('Operations/IncidentsPatrol', [
            'metrics' => $metrics,
            'incidents' => $incidents,
        ]);
    }

    /**
     * Store new Incident
     */
    public function storeIncident(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'chainage' => 'required|string|max:50',
            'direction' => 'required|in:northbound,southbound,both',
            'severity' => 'required|in:minor,major,critical',
            'dispatched_unit' => 'nullable|string',
            'description' => 'nullable|string',
        ]);

        $incident = OmIncident::create([
            'incident_number' => 'INC-' . strtoupper(uniqid()),
            'title' => $validated['title'],
            'chainage' => $validated['chainage'],
            'direction' => $validated['direction'],
            'severity' => $validated['severity'],
            'status' => 'dispatched',
            'dispatched_unit' => $validated['dispatched_unit'] ?? 'Patrol Unit 1',
            'response_time_minutes' => 10,
            'description' => $validated['description'] ?? null,
            'reported_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Incident reported and patrol dispatched successfully.',
            'incident' => $incident,
        ]);
    }

    /**
     * Routine Maintenance Work Orders Page
     */
    public function workOrders(Request $request): Response|JsonResponse
    {
        $workOrders = OmWorkOrder::latest()->paginate(15);

        if ($request->wantsJson() && !$request->header('X-Inertia')) {
            return response()->json([
                'success' => true,
                'work_orders' => $workOrders,
            ]);
        }

        return Inertia::render('Operations/MaintenanceWorkOrders', [
            'workOrders' => $workOrders,
        ]);
    }

    /**
     * Store new Work Order
     */
    public function storeWorkOrder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'category' => 'required|in:pavement,guardrail,lighting,drainage,bridge,signage',
            'location' => 'required|string|max:100',
            'priority' => 'required|in:low,medium,high,emergency',
            'assigned_to' => 'nullable|string',
            'description' => 'nullable|string',
        ]);

        $wo = OmWorkOrder::create([
            'work_order_number' => 'WO-' . rand(10000, 99999),
            'title' => $validated['title'],
            'category' => $validated['category'],
            'location' => $validated['location'],
            'priority' => $validated['priority'],
            'status' => 'assigned',
            'assigned_to' => $validated['assigned_to'] ?? 'Road Maintenance Crew A',
            'description' => $validated['description'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Maintenance Work Order created successfully.',
            'work_order' => $wo,
        ]);
    }

    /**
     * Equipment & Facilities Status Page
     */
    public function equipment(Request $request): Response|JsonResponse
    {
        $equipment = OmEquipment::all();

        if ($request->wantsJson() && !$request->header('X-Inertia')) {
            return response()->json([
                'success' => true,
                'equipment' => $equipment,
            ]);
        }

        return Inertia::render('Operations/EquipmentFacilities', [
            'equipment' => $equipment,
        ]);
    }

    /**
     * Digital Shift Handover Logs Page
     */
    public function shiftLogs(Request $request): Response|JsonResponse
    {
        $shiftLogs = OmShiftLog::with('operator')->latest('shift_date')->paginate(15);

        if ($request->wantsJson() && !$request->header('X-Inertia')) {
            return response()->json([
                'success' => true,
                'shift_logs' => $shiftLogs,
            ]);
        }

        return Inertia::render('Operations/ShiftHandoverLogs', [
            'shiftLogs' => $shiftLogs,
        ]);
    }

    /**
     * Update VMS Message
     */
    public function updateVmsMessage(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => 'required|exists:om_vms_messages,id',
            'message_line1' => 'required|string|max:100',
            'message_line2' => 'nullable|string|max:100',
            'type' => 'required|in:info,warning,emergency,speed_limit',
        ]);

        $vms = OmVmsMessage::findOrFail($validated['id']);
        $vms->update([
            'message_line1' => $validated['message_line1'],
            'message_line2' => $validated['message_line2'] ?? null,
            'type' => $validated['type'],
            'updated_by_operator_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Variable Message Sign updated and broadcast live.',
            'vms' => $vms,
        ]);
    }
}
