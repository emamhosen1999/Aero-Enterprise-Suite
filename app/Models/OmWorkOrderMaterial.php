<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OmWorkOrderMaterial extends Model
{
    use HasFactory;

    protected $fillable = [
        'work_order_id',
        'item_name',
        'item_code',
        'unit',
        'quantity_planned',
        'quantity_used',
        'unit_cost',
        'total_cost',
        'issued_from_inventory_id',
    ];

    protected $casts = [
        'quantity_planned' => 'decimal:2',
        'quantity_used' => 'decimal:2',
        'unit_cost' => 'decimal:2',
        'total_cost' => 'decimal:2',
    ];

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(OmWorkOrder::class, 'work_order_id');
    }
}
