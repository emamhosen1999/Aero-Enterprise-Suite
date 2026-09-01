<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OmLaneClosurePermit extends Model
{
    use HasFactory;

    protected $fillable = [
        'permit_number',
        'work_order_id',
        'title',
        'chainage_from',
        'chainage_to',
        'direction',
        'lanes_closed',
        'scheduled_start',
        'scheduled_end',
        'actual_start',
        'actual_end',
        'status',
        'requested_by',
        'approved_by',
        'traffic_control_plan',
        'vms_alert_active',
        'safety_cones_deployed',
        'traffic_marshals_deployed',
        'flashing_arrow_board_present',
        'safety_checklist_notes',
    ];

    protected $casts = [
        'scheduled_start' => 'datetime',
        'scheduled_end' => 'datetime',
        'actual_start' => 'datetime',
        'actual_end' => 'datetime',
        'vms_alert_active' => 'boolean',
        'flashing_arrow_board_present' => 'boolean',
        'safety_cones_deployed' => 'integer',
        'traffic_marshals_deployed' => 'integer',
    ];

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(OmWorkOrder::class, 'work_order_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
