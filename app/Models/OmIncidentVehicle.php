<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OmIncidentVehicle extends Model
{
    use HasFactory;

    protected $fillable = [
        'incident_id',
        'vehicle_reg_number',
        'vehicle_type',
        'driver_name',
        'driver_license_number',
        'driver_phone',
        'insurance_company',
        'insurance_policy_number',
        'towed_by_expressway_wrecker',
        'towing_fee_charged',
        'damage_to_vehicle_description',
        'damage_to_expressway_asset',
        'estimated_asset_repair_cost',
    ];

    protected $casts = [
        'towed_by_expressway_wrecker' => 'boolean',
        'towing_fee_charged' => 'decimal:2',
        'estimated_asset_repair_cost' => 'decimal:2',
    ];

    public function incident(): BelongsTo
    {
        return $this->belongsTo(OmIncident::class, 'incident_id');
    }
}
