<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OmTrafficLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'section_code',
        'section_name',
        'vehicle_count_per_hour',
        'avg_speed_kmh',
        'density_status',
        'overspeed_count',
        'overload_count',
        'recorded_at',
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
        'avg_speed_kmh' => 'decimal:2',
    ];
}
