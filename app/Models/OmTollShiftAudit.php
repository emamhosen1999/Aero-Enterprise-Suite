<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OmTollShiftAudit extends Model
{
    use HasFactory;

    protected $fillable = [
        'audit_code',
        'plaza_name',
        'shift_date',
        'shift_type',
        'auditor_id',
        'shift_supervisor_id',
        'system_calculated_total',
        'cash_declared_by_collectors',
        'etc_automatic_revenue',
        'pos_card_mfs_revenue',
        'variance_amount',
        'total_vehicle_transactions',
        'avc_physical_axle_count',
        'exempted_vehicle_count',
        'evasion_violation_count',
        'bank_deposit_reference',
        'bank_deposit_amount',
        'audit_status',
        'auditor_notes',
    ];

    protected $casts = [
        'shift_date' => 'date',
        'system_calculated_total' => 'decimal:2',
        'cash_declared_by_collectors' => 'decimal:2',
        'etc_automatic_revenue' => 'decimal:2',
        'pos_card_mfs_revenue' => 'decimal:2',
        'variance_amount' => 'decimal:2',
        'bank_deposit_amount' => 'decimal:2',
        'total_vehicle_transactions' => 'integer',
        'avc_physical_axle_count' => 'integer',
        'exempted_vehicle_count' => 'integer',
        'evasion_violation_count' => 'integer',
    ];

    public function auditor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auditor_id');
    }

    public function shiftSupervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shift_supervisor_id');
    }

    public function exemptions(): HasMany
    {
        return $this->hasMany(OmTollExemption::class, 'toll_shift_audit_id');
    }
}
