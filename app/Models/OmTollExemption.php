<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OmTollExemption extends Model
{
    use HasFactory;

    protected $fillable = [
        'toll_shift_audit_id',
        'plaza_name',
        'lane_id',
        'vehicle_reg_number',
        'exemption_category',
        'authorizing_document_ref',
        'officer_or_driver_name',
        'passed_at',
    ];

    protected $casts = [
        'passed_at' => 'datetime',
    ];

    public function tollShiftAudit(): BelongsTo
    {
        return $this->belongsTo(OmTollShiftAudit::class, 'toll_shift_audit_id');
    }
}
