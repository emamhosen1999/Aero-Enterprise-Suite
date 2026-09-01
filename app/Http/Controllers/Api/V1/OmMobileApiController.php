<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\OmDefect;
use App\Models\OmIncident;
use App\Models\OmPatrolShift;
use App\Models\OmWorkOrder;
use App\Services\Operations\OmDefectService;
use App\Services\Operations\OmIncidentService;
use App\Services\Operations\OmWorkOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OmMobileApiController extends Controller
{
    public function __construct(
        protected OmDefectService $defectService,
        protected OmIncidentService $incidentService,
        protected OmWorkOrderService $workOrderService
    ) {}

    /**
     * Mobile Field Operations Overview
     */
    public function fieldOverview(Request $request): JsonResponse
    {
        $userId = $request->user()?->id;

        $activeIncidents = OmIncident::whereIn('status', ['detected', 'dispatched', 'on_scene'])
            ->latest('reported_at')
            ->take(10)
            ->get();

        $assignedWorkOrders = OmWorkOrder::whereIn('status', ['assigned', 'in_progress'])
            ->latest()
            ->take(10)
            ->get();

        $openDefects = OmDefect::whereIn('status', ['reported', 'investigating', 'work_order_created', 'in_repair'])
            ->latest()
            ->take(10)
            ->get();

        $activePatrolShift = OmPatrolShift::where('status', 'in_progress')->latest()->first();

        return response()->json([
            'success' => true,
            'data' => [
                'active_patrol_shift' => $activePatrolShift,
                'active_incidents' => $activeIncidents,
                'assigned_work_orders' => $assignedWorkOrders,
                'open_defects' => $openDefects,
            ],
        ]);
    }

    /**
     * Mobile: Quick Log Roadway Defect with GPS Geotag
     */
    public function logDefect(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'distress_type' => 'required|string',
            'chainage' => 'required|string|max:50',
            'direction' => 'required|in:northbound,southbound,both,median,ramp',
            'severity' => 'required|in:low,medium,high,critical',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'description' => 'nullable|string',
            'before_photos' => 'nullable|array',
        ]);

        $defect = $this->defectService->createDefect($validated, $request->user()?->id);

        return response()->json([
            'success' => true,
            'message' => "Defect {$defect->defect_number} recorded via Mobile Field App.",
            'defect' => $defect,
        ]);
    }

    /**
     * Mobile: Update Incident On-Scene Status / Dispatch Triage
     */
    public function updateIncident(Request $request, int $id): JsonResponse
    {
        $incident = OmIncident::findOrFail($id);
        $validated = $request->validate([
            'status' => 'required|in:detected,dispatched,on_scene,cleared,closed',
            'dispatched_unit' => 'nullable|string',
            'description' => 'nullable|string',
        ]);

        $incident = $this->incidentService->updateStatus($incident, $validated['status'], $validated);

        return response()->json([
            'success' => true,
            'message' => "Incident {$incident->incident_number} updated to {$incident->status}.",
            'incident' => $incident,
        ]);
    }

    /**
     * Mobile: Work Order Progress & Evidence Upload
     */
    public function updateWorkOrder(Request $request, int $id): JsonResponse
    {
        $workOrder = OmWorkOrder::findOrFail($id);
        $action = $request->input('action', 'in_progress');

        if ($action === 'start') {
            $workOrder = $this->workOrderService->startWorkOrder($workOrder);
        } elseif ($action === 'complete') {
            $workOrder = $this->workOrderService->completeWorkOrder($workOrder, $request->all());
        }

        return response()->json([
            'success' => true,
            'message' => "Work Order {$workOrder->work_order_number} status updated.",
            'work_order' => $workOrder,
        ]);
    }
}
