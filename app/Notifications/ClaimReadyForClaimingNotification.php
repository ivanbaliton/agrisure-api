<?php

namespace App\Notifications;

use App\Models\Claim;
use App\Services\FcmService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ClaimReadyForClaimingNotification extends Notification
{
    use Queueable;

    protected Claim $claim;

    public function __construct(Claim $claim)
    {
        $this->claim = $claim;
    }

    public function via($notifiable): array
    {
        $channels = ['database'];

        if (!empty($notifiable->email)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail($notifiable): MailMessage
    {
        $farmName = $this->claim->damageReport?->insuranceApplication?->farm?->farm_name ?? 'your farm';

        return (new MailMessage)
            ->subject('Indemnity Claim Ready for Claiming')
            ->greeting('Hello ' . $notifiable->first_name . ',')
            ->line("Great news! Your indemnity claim (#{$this->claim->id}) for \"{$farmName}\" is now ready for claiming.")
            ->line('Schedule: ' . ($this->claim->claim_schedule ?? 'N/A'))
            ->line('Venue: ' . ($this->claim->claim_venue ?? 'N/A'))
            ->line('Remarks: ' . ($this->claim->pcic_remarks ?? 'None'))
            ->line('Thank you for using AgriSure!');
    }

    public function toDatabase($notifiable): array
    {
        $farmName = $this->claim->damageReport?->insuranceApplication?->farm?->farm_name ?? 'your farm';

        return [
            'title' => 'Claim Ready for Claiming',
            'message' => "Your indemnity claim (#{$this->claim->id}) for \"{$farmName}\" is now ready for claiming at {$this->claim->claim_venue}.",
            'claim_id' => $this->claim->id,
            'type' => 'claim_ready_for_claiming',
        ];
    }

    public function sendCustomAlerts($notifiable): void
    {
        $farmName = $this->claim->damageReport?->insuranceApplication?->farm?->farm_name ?? 'your farm';

        // 1. Send SMS via Semaphore
        if (!empty($notifiable->phone_number)) {
            try {
                Http::withoutVerifying()
                    ->asForm()
                    ->post('https://semaphore.co/api/v4/messages', [
                        'apikey' => config('services.semaphore.api_key'),
                        'number' => $notifiable->phone_number,
                        'message' => "AgriSure: Your claim #{$this->claim->id} for {$farmName} is ready for claiming at {$this->claim->claim_venue} on {$this->claim->claim_schedule}.",
                        'sendername' => config('services.semaphore.sender_name'),
                    ]);
            } catch (\Exception $e) {
                Log::error('SMS notification error: ' . $e->getMessage());
            }
        }

        // 2. Send Push Notification via FCM
        if (!empty($notifiable->fcm_token)) {
            FcmService::sendPushNotification(
                $notifiable->fcm_token,
                'Claim Ready for Claiming',
                "Your indemnity claim (#{$this->claim->id}) is ready for claiming.",
                [
                    'claim_id' => (string)$this->claim->id,
                    'type' => 'claim_ready_for_claiming',
                ]
            );
        }
    }
}