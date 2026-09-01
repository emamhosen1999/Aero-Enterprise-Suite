<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OmPatrolShift extends Model
{
    use HasFactory;

    protected $fillable = [
        'patrol_code',
        'patrol_date',
        'shift_type',
        'vehicle_reg_number',
        'call_sign',
        'lead_officer_id',
        'crew_member_ids',
        'assigned_zone_from',
        'assigned_zone_to',
        'start_odometer_km',
        'end_odometer_km',
        'fuel_liters_added',
        'started_at',
        'ended_at',
        'status',
        'incidents_attended_count',
        'defects_reported_count',
        'shift_summary',
    ];

    protected $casts = [
        'patrol_date' => 'date',
        'crew_member_ids' => 'array',
        'start_odometer_km' => 'decimal:2',
        'end_odometer_km' => 'decimal:2',
        'fuel_liters_added' => 'decimal:2',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'incidents_attended_count' => 'integer',
        'defects_reported_count' => 'integer',
    ];

    public function leadOfficer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lead_officer_id');
    }

    public function defects(): HasMany
    {
        return $this->hasMany(OmDefect::class, 'patrol_shift_id');
    }
}
