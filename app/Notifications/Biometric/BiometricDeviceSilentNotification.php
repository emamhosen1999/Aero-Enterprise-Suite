<?php

// app/Notifications/Biometric/BiometricDeviceSilentNotification.php

namespace App\Notifications\Biometric;

use App\Notifications\Concerns\DeliversProactiveAttendanceAlert;
use App\Services\Notification\Push\PushMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
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
 *
 * ── Why the copy is shaped the way it is ──────────────────────────────────
 * This lands at 02:00 on someone who has to decide, from the notification
 * alone, whether to get up. "Device offline" does not support that decision.
 * Every message therefore carries: WHICH terminal (name AND serial — sites run
 * several identical MB460s and the serial is what the ADMS registration and
 * the device screen key on), HOW LONG it has been silent in units a human
 * reads at 2am rather than raw minutes, WHEN it was last heard from, what it
 * COSTS (punches are not reaching attendance), and WHAT TO CHECK first.
 *
 * The last of those differs by case, which is why the two branches are not
 * one string with a null check. A device that was reporting and stopped is a
 * power/network/uplink failure. A device that has NEVER reported is not a
 * failure at all — it is a provisioning mistake (wrong ADMS server address,
 * port, domain, or a serial that does not match the registered one), and
 * sending an admin to check the mains lead for that wastes the callout.
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

    /**
     * Silence duration in units someone reads at 02:00.
     *
     * "has not checked in for 1,447 minutes" is technically the same fact as
     * "for 1 d 0 h" and materially harder to act on. Minutes are kept in the
     * payload as silent_minutes for anything that wants to compute on them.
     */
    public function humanSilence(): string
    {
        $minutes = max(0, $this->silentMinutes);

        if ($minutes < 60) {
            return $minutes.' min';
        }

        $days = intdiv($minutes, 1440);
        $hours = intdiv($minutes % 1440, 60);
        $mins = $minutes % 60;

        if ($days > 0) {
            return $hours > 0 ? "{$days} d {$hours} h" : "{$days} d";
        }

        return $mins > 0 ? "{$hours} h {$mins} min" : "{$hours} h";
    }

    /** First thing to check — differs for "stopped" versus "never started". */
    public function firstCheck(): string
    {
        return $this->lastSeen === null
            ? 'confirm the terminal\'s ADMS server address, port and domain, and that its serial matches the one registered here'
            : 'check the terminal\'s power and network link, then that it still points at this server';
    }

    public function toArray(object $notifiable): array
    {
        $body = $this->lastSeen === null
            ? "{$this->deviceName} ({$this->serialNumber}) has never checked in since it was registered. Punches from this terminal are not reaching attendance — ".$this->firstCheck().'.'
            : "{$this->deviceName} ({$this->serialNumber}) has not checked in for {$this->humanSilence()} (last heartbeat {$this->lastSeen}). Punches from this terminal are not reaching attendance — ".$this->firstCheck().'.';

        return [
            'type_key' => $this->typeKey(),
            'title' => 'Biometric device offline',
            'body' => $body,
            'url' => '/settings/biometric-devices',
            'device_name' => $this->deviceName,
            'serial_number' => $this->serialNumber,
            'silent_minutes' => $this->silentMinutes,
            'silent_for' => $this->humanSilence(),
            'last_seen' => $this->lastSeen,
        ];
    }

    public function toPush(object $notifiable): PushMessage
    {
        $data = $this->toArray($notifiable);

        return new PushMessage($data['title'], $data['body'], [
            'type_key' => $data['type_key'],
            'url' => $data['url'],
            'serial_number' => $this->serialNumber,
        ]);
    }

    /**
     * The out-of-hours path: an admin who is not carrying the app, or who has
     * no push token registered, still gets told. Mail has room the push body
     * does not, so the facts are laid out as separate lines instead of being
     * compressed into one sentence.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject("Biometric device offline: {$this->deviceName}")
            ->greeting('Biometric terminal not reporting')
            ->line("Device: {$this->deviceName} (serial {$this->serialNumber})");

        if ($this->lastSeen === null) {
            $mail->line('Last heartbeat: never — this terminal has not checked in once since it was registered.');
        } else {
            $mail->line("Silent for: {$this->humanSilence()}")
                ->line("Last heartbeat: {$this->lastSeen}");
        }

        return $mail
            ->line('While it is silent, punches from this terminal are not reaching attendance, and the gap will surface tomorrow as missing employee records.')
            ->line('First thing to check: '.$this->firstCheck().'.')
            ->action('Open biometric devices', url('/settings/biometric-devices'));
    }
}
