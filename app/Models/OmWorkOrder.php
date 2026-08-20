<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OmWorkOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'work_order_number',
        'title',
        'category',
        'location',
        'priority',
        'status',
        'assigned_to',
        'description',
        'completed_at',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
    ];
}
