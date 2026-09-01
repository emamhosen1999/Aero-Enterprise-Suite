<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OmWorkOrderCrew extends Model
{
    use HasFactory;

    protected $fillable = [
        'work_order_id',
        'worker_or_machine_name',
        'resource_type',
        'hours_spent',
        'hourly_rate',
        'total_cost',
    ];

    protected $casts = [
        'hours_spent' => 'decimal:2',
        'hourly_rate' => 'decimal:2',
        'total_cost' => 'decimal:2',
    ];

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(OmWorkOrder::class, 'work_order_id');
    }
}
