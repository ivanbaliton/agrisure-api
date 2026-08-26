<?php

namespace App\Notifications;

use App\Models\InsuranceApplication;
use App\Services\FcmService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ApplicationForwardedToPcicNotification extends Notification
{
    use Queueable;

    protected InsuranceApplication $application;

    public function __construct(InsuranceApplication $application)
    {
        $this->application = $application;
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
        $farmName = $this->application->farm->farm_name ?? 'your registered farm';

        return (new MailMessage)
            ->subject('Insurance Application Forwarded to PCIC')
            ->greeting('Hello ' . $notifiable->first_name . ',')
            ->line("Great news! Your crop insurance application for farm \"{$farmName}\" has been reviewed by MAO and successfully forwarded to PCIC.")
            ->line('Application ID: ' . $this->application->id)
            ->line('Variety: ' . $this->application->variety)
            ->line('Insured Area: ' . $this->application->insured_area . ' ha')
            ->line('Thank you for using AgriSure!');
    }

    public function toDatabase($notifiable): array
    {
        $farmName = $this->application->farm->farm_name ?? 'your registered farm';

        return [
            'title' => 'Application Forwarded to PCIC',
            'message' => "Your crop insurance application for \"{$farmName}\" has been endorsed and forwarded to PCIC.",
            'application_id' => $this->application->id,
            // 'farm_id' => $this->application->farm_id,
            'type' => 'insurance_pcic_submission',
        ];
    }

    public function sendCustomAlerts($notifiable): void
    {
        $farmName = $this->application->farm->farm_name ?? 'your farm';

        // 1. Send SMS via Semaphore
        if (!empty($notifiable->phone_number)) {
            try {
                Http::withoutVerifying()
                    ->asForm()
                    ->post('https://semaphore.co/api/v4/messages', [
                        'apikey' => config('services.semaphore.api_key'),
                        'number' => $notifiable->phone_number,
                        'message' => "AgriSure: Your insurance application for {$farmName} (ID: #{$this->application->id}) has been forwarded to PCIC.",
                        'sendername' => config('services.semaphore.sender_name'),
                    ]);
            } catch (\Exception $e) {
                Log::error('SMS notification error: ' . $e->getMessage());
            }
        }

        // 2. Send Push Notification via Firebase FCM
        if (!empty($notifiable->fcm_token)) {
            FcmService::sendPushNotification(
                $notifiable->fcm_token,
                'Application Forwarded to PCIC',
                "Your insurance application for {$farmName} was successfully submitted to PCIC.",
                [
                    'application_id' => (string)$this->application->id,
                    'type' => 'insurance_pcic_submission',
                ]
            );
        }
    }
}