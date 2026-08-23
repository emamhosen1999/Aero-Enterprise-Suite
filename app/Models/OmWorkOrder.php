<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'reported_by',
        'assigned_by',
        'verified_by',
        'verified_at',
        'completed_at',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
        'verified_at' => 'datetime',
    ];

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(OmWorkOrderPhoto::class, 'work_order_id');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(OmActivityLog::class, 'entity_id')
            ->where('entity_type', 'work_order');
    }
}