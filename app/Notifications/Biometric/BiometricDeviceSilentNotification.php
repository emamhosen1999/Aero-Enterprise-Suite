<?php

// app/Notifications/Biometric/BiometricDeviceSilentNotification.php

namespace App\Notifications\Biometric;

use App\Notifications\Concerns\DeliversProactiveAttendanceAlert;
use App\Services\Notification\Push\PushMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * A biometric terminal has stopped talking to the server.
 *
 * Recipients are the administrators who can actually act on it (see
 * BiometricDeviceHealthAlert), not the employees whose punches are being lost.
 *
 * Uses the same proactive-alert channel routing as the shift-lifecycle alerts:
 * honour the notification registry + user preferences when the type is
 * registered, and fall back to in-app + push rather than going silent when it
 * is not. An operational alert that quietly delivers nowhere is worse than no
 * alert at all, because it looks like everything is fine.
 */
class BiometricDeviceSilentNotification extends Notification implements ShouldQueue
{
    use DeliversProactiveAttendanceAlert, Queueable;

    /**
     * @param  string  $deviceName  e.g. 'Gate MB460'
     * @param  string  $serialNumber  device serial
     * @param  int  $silentMinutes  minutes since the last heartbeat, 0 when never seen
     * @param  string|null  $lastSeen  last heartbeat, 'Y-m-d H:i' local, null when never seen
     */
    public function __construct(
        public string $deviceName,
        public string $serialNumber,
        public int $silentMinutes,
        public ?string $lastSeen = null,
    ) {}

    public function typeKey(): string
    {
        return 'biometric.device_silent';
    }

    public function toArray(object $notifiable): array
    {
        $body = $this->lastSeen === null
            ? "{$this->deviceName} ({$this->serialNumber}) has never checked in. Punches from this terminal are not reaching attendance."
            : "{$this->deviceName} ({$this->serialNumber}) has not checked in for {$this->silentMinutes} minutes (last seen {$this->lastSeen}). Punches from this terminal are not reaching attendance.";

        return [
            'type_key' => $this->typeKey(),
            'title' => 'Biometric device offline',
            'body' => $body,
            'url' => '/settings/biometric-devices',
            'device_name' => $this->deviceName,
            'serial_number' => $this->serialNumber,
            'silent_minutes' => $this->silentMinutes,
            'last_seen' => $this->lastSeen,
        ];
    }

    public function toPush(object $notifiable): PushMessage
    {
        $data = $this->toArray($notifiable);

        return new PushMessage($data['title'], $data['body'], [
            'type_key' => $data['type_key'],
            'url' => $data['url'],
        ]);
    }
}
