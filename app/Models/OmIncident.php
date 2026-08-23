<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'reported_by',
        'escalation_level',
        'escalated_at',
        'escalated_by',
        'escalation_notes',
        'reported_at',
        'cleared_at',
    ];

    protected $casts = [
        'reported_at' => 'datetime',
        'cleared_at' => 'datetime',
        'escalated_at' => 'datetime',
        'escalation_level' => 'integer',
    ];

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function escalator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'escalated_by');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(OmIncidentPhoto::class, 'incident_id');
    }

    public function escalations(): HasMany
    {
        return $this->hasMany(OmIncidentEscalation::class, 'incident_id');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(OmActivityLog::class, 'entity_id')
            ->where('entity_type', 'incident');
    }
}