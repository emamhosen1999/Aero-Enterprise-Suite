<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OmIncident extends Model
{
    use HasFactory;

    protected $fillable = [
        'incident_number',
        'title',
        'chainage',
        'direction',
        'severity',
        'status',
        'dispatched_unit',
        'response_time_minutes',
        'description',
        'reported_at',
        'cleared_at',
    ];

    protected $casts = [
        'reported_at' => 'datetime',
        'cleared_at' => 'datetime',
    ];
}
