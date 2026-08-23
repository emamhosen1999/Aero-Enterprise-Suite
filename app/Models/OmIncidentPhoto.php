<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OmIncidentPhoto extends Model
{
    use HasFactory;

    protected $fillable = [
        'incident_id',
        'photo_path',
        'uploaded_by',
    ];

    public function incident(): BelongsTo
    {
        return $this->belongsTo(OmIncident::class, 'incident_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}