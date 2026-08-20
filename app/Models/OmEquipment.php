<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OmEquipment extends Model
{
    use HasFactory;

    protected $table = 'om_equipment_status';

    protected $fillable = [
        'equipment_code',
        'name',
        'category',
        'location',
        'status',
        'uptime_pct',
        'last_ping_at',
    ];

    protected $casts = [
        'last_ping_at' => 'datetime',
        'uptime_pct' => 'decimal:2',
    ];
}
