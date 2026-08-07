<?php

namespace App\Models\HRM;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BiometricAttLog extends Model
{
    protected $table = 'biometric_att_logs';

    protected $fillable = [
        'biometric_device_id',
        'serial_number',
        'user_pin',
        'user_id',
        'punch_time',
        // The moment actually written to `attendances`, and the signed adjustment
        // used to get there (2026_08_06_000001). Fillable because the direct
        // webhook path builds its row as an array and hands it to
        // BiometricAttLog::create(); the ADMS path writes through the query
        // builder and does not care either way. Without these two entries a
        // create() carrying a correction dropped it silently.
        'corrected_punch_time',
        'clock_offset_applied_seconds',
        'check_type',
        'punch_status',
        'punch_status_reason',
        'verify_code',
        'work_code',
        'raw_data',
        'context',
        'occurred_at',
    ];

    protected $casts = [
        // RAW device value. Part of the punch natural key
        // (biometric_device_id, user_pin, punch_time, check_type) made unique by
        // 2026_08_03_000001, and deliberately never rewritten — see that
        // migration and 2026_08_06_000001. The cast only changes what Eloquent
        // hands back on read; the stored value stays exactly what the device said.
        'punch_time' => 'datetime',
        // What was actually written to `attendances` after the device's clock
        // offset was applied. NULL means no correction was applied and
        // `punch_time` is what was used, and a 'datetime' cast preserves that
        // null. This is the column an auditor compares against `punch_time`, so
        // it has to come back as a date, not as the raw string it was until now.
        'corrected_punch_time' => 'datetime',
        // Signed seconds added to the raw device time. Nullable and stays
        // nullable: null = nothing applied, which is not the same as 0.
        'clock_offset_applied_seconds' => 'integer',
        'occurred_at' => 'datetime',
        'context' => 'array',
        'biometric_device_id' => 'integer',
        'user_id' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(BiometricDevice::class, 'biometric_device_id');
    }
}
