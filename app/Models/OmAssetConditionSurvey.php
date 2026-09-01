<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OmAssetConditionSurvey extends Model
{
    use HasFactory;

    protected $fillable = [
        'asset_id',
        'survey_date',
        'condition_score',
        'condition_grade',
        'roughness_iri',
        'rutting_depth_mm',
        'skid_resistance_sn',
        'inspector_id',
        'findings',
        'recommendations',
        'photo_paths',
    ];

    protected $casts = [
        'survey_date' => 'date',
        'condition_score' => 'integer',
        'roughness_iri' => 'decimal:2',
        'rutting_depth_mm' => 'decimal:2',
        'skid_resistance_sn' => 'decimal:2',
        'photo_paths' => 'array',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(OmAsset::class, 'asset_id');
    }

    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspector_id');
    }
}
