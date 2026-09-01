<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OmAsset extends Model
{
    use HasFactory;

    protected $fillable = [
        'asset_code',
        'name',
        'category',
        'start_chainage',
        'end_chainage',
        'direction',
        'location_description',
        'latitude',
        'longitude',
        'manufacturer',
        'model_number',
        'serial_number',
        'installation_date',
        'warranty_expiry',
        'purchase_cost',
        'replacement_cost',
        'expected_lifespan_years',
        'condition_score',
        'condition_grade',
        'operational_status',
        'last_inspected_at',
        'technical_specs',
        'notes',
    ];

    protected $casts = [
        'installation_date' => 'date',
        'warranty_expiry' => 'date',
        'last_inspected_at' => 'datetime',
        'purchase_cost' => 'decimal:2',
        'replacement_cost' => 'decimal:2',
        'condition_score' => 'integer',
        'expected_lifespan_years' => 'integer',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    public function conditionSurveys(): HasMany
    {
        return $this->hasMany(OmAssetConditionSurvey::class, 'asset_id');
    }

    public function defects(): HasMany
    {
        return $this->hasMany(OmDefect::class, 'asset_id');
    }

    public function workOrders(): HasMany
    {
        return $this->hasMany(OmWorkOrder::class, 'asset_id');
    }
}
