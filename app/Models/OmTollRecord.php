<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OmTollRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'plaza_name',
        'lane_id',
        'vehicle_class',
        'payment_method',
        'amount',
        'transacted_at',
    ];

    protected $casts = [
        'transacted_at' => 'datetime',
        'amount' => 'decimal:2',
    ];
}
