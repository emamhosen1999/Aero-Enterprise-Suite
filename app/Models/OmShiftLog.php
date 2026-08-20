<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OmShiftLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'shift_date',
        'shift_type',
        'operator_id',
        'open_incidents_count',
        'handover_notes',
        'equipment_exceptions',
        'is_acknowledged',
    ];

    protected $casts = [
        'shift_date' => 'date',
        'is_acknowledged' => 'boolean',
    ];

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }
}
