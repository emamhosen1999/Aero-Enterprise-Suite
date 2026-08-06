<?php

namespace App\Models\HRM;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class BiometricDevice extends Model
{
    protected $fillable = [
        'name',
        'serial_number',
        'model',
        'ip_address',
        'port',
        'location',
        'auth_token',
        'adms_token',
        'protocol',
        'is_active',
        'last_heartbeat_at',
        'last_log_download_at',
        'notes',
        'config',
        'users_count',
        'clock_offset_seconds',
        'clock_offset_samples',
        'clock_offset_measured_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_heartbeat_at' => 'datetime',
        'last_log_download_at' => 'datetime',
        'config' => 'array',
        // Nullable on purpose, and the cast keeps it that way: a NULL offset
        // means "this device's clock has never been measured", which behaves
        // differently from a measured zero (see DeviceClockService). An
        // 'integer' cast returns null for null, so the distinction survives.
        'clock_offset_seconds' => 'integer',
        'clock_offset_samples' => 'integer',
        'clock_offset_measured_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $device) {
            if (empty($device->auth_token)) {
                $device->auth_token = Str::random(48);
            }
        });
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function regenerateToken(): string
    {
        $token = Str::random(48);
        $this->update(['auth_token' => $token]);

        return $token;
    }

    /**
     * Generate and persist a fresh ADMS shared secret (biometric_devices.adms_token),
     * returning the plaintext so it can be shown to the admin exactly once.
     *
     * Deliberately NOT wired into the booted() creating hook: the
     * EnsureAdmsDeviceAuthorized middleware treats a NULL adms_token as an
     * intentional "allowlist-only" fallback so already-deployed hardware keeps
     * working. Auto-generating a secret on create would silently start
     * rejecting every un-reconfigured device the moment strict mode is enabled.
     * NULL must stay meaningful — provisioning is an explicit admin action.
     *
     * Length matches regenerateToken()'s 48 chars for consistency; the column is
     * string(64) so there is headroom, and Str::random() is CSPRNG-backed.
     */
    public function regenerateAdmsToken(): string
    {
        $token = Str::random(48);
        $this->update(['adms_token' => $token]);

        return $token;
    }

    /**
     * Check if device is online based on last heartbeat.
     * ADMS default polling interval is 30–120 s, so 1 min caused false "offline" flicker.
     * 5 minutes gives enough headroom without masking a genuinely disconnected device.
     */
    public function isOnline(): bool
    {
        if (! $this->last_heartbeat_at) {
            return false;
        }

        return $this->last_heartbeat_at->gt(now()->subMinutes(5));
    }

    /**
     * Get online status as a string for display
     */
    public function getOnlineStatusAttribute(): string
    {
        return $this->isOnline() ? 'online' : 'offline';
    }

    public function attendanceTypes()
    {
        return $this->belongsToMany(
            AttendanceType::class,
            'attendance_type_biometric_device',
            'biometric_device_id',
            'attendance_type_id'
        )->withPivot('created_at');
    }

    public function downloadSessions()
    {
        return $this->hasMany(BiometricDownloadSession::class, 'biometric_device_id');
    }

    public function isAdms(): bool
    {
        return $this->protocol === 'adms';
    }
}
