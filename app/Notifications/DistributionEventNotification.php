<?php

namespace App\Notifications;

use App\Models\DistributionEvent;
use App\Services\NotificationService;
use App\Services\SmsService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use App\Mail\DistributionBeneficiaryMail;
use Carbon\Carbon;

class DistributionEventNotification extends Notification
{
    use Queueable;

    protected $event;
    protected array $channels;

    public function __construct(DistributionEvent $event, array $channels)
    {
        $this->event = $event;
        $this->channels = $channels;
    }

    /**
     * Determine which notification channels are active.
     */
    public function via(object $notifiable): array
    {
        $activeChannels = [];

        $selectedChannels = array_map(
            'strtolower',
            $this->channels ?? []
        );

        /*
         * PUSH NOTIFICATION
         *
         * Supports both "push" and "app".
         */
        if (
            in_array('push', $selectedChannels) ||
            in_array('app', $selectedChannels)
        ) {
            $this->triggerDirectPush($notifiable);
        }

        /*
         * SMS NOTIFICATION
         *
         * SMS is sent directly through SmsService
         * which communicates with Semaphore.
         */
        if (in_array('sms', $selectedChannels)) {
            $this->triggerDirectSms($notifiable);
        }

        /*
         * EMAIL NOTIFICATION
         *
         * Laravel handles the actual email delivery.
         */
        if (in_array('email', $selectedChannels)) {
            $activeChannels[] = 'mail';
        }

        /*
         * SMS and push are intentionally not returned
         * because they are triggered directly above.
         */
        return $activeChannels;
    }

    /**
     * Send push notification.
     */
    protected function triggerDirectPush(object $notifiable): void
    {
        try {
            $targetUserId = $notifiable->farmer_id
                ?? $notifiable->id;

            $title = $this->event->title
                ?? $this->event->name
                ?? 'Farm Supply Assistance';

            $dateSource = $this->event->distribution_date
                ?? $this->event->date;

            $formattedDate = $dateSource
                ? Carbon::parse($dateSource)->format('M d, Y')
                : '';

            $venue = $this->event->venue
                ?? 'Barangay Hall';

            $titlePayload = '🌾 Farm Supply Assistance Scheduled';

            $messagePayload =
                "You are scheduled to receive items for {$title} "
                . "on {$formattedDate} at {$venue}.";

            NotificationService::send(
                $targetUserId,
                $titlePayload,
                $messagePayload
            );

            \Log::info('Distribution push notification sent', [
                'event_id' => $this->event->id ?? null,
                'farmer_id' => $targetUserId,
            ]);

        } catch (\Exception $e) {

            \Log::error('Push notification execution failed', [
                'event_id' => $this->event->id ?? null,
                'farmer_id' => $notifiable->id ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send SMS notification through Semaphore.
     */
    protected function triggerDirectSms(object $notifiable): void
    {
        try {

            /*
             * Get the farmer's phone number.
             *
             * Supports:
             * - User model directly
             * - Model with farmer relationship
             */
            $phoneNumber = $notifiable->phone_number
                ?? $notifiable->farmer?->phone_number
                ?? null;

            if (!$phoneNumber) {
                throw new \Exception(
                    'Farmer does not have a phone number.'
                );
            }

            /*
             * Use the same SMS message defined in toSms().
             */
            $message = $this->toSms($notifiable);

            /*
             * Send SMS through the centralized SmsService.
             */
            $smsResponse = app(SmsService::class)->sendMessage(
                $phoneNumber,
                $message
            );

            /*
             * Log successful Semaphore response.
             *
             * The API key is never logged.
             */
            \Log::info('Distribution SMS sent successfully', [
                'event_id' => $this->event->id ?? null,
                'farmer_id' => $notifiable->id ?? null,
                'phone' => $phoneNumber,
                'response' => $smsResponse,
            ]);

        } catch (\Exception $e) {

            /*
             * SMS failure does not stop other notification channels.
             */
            \Log::error('Distribution SMS sending failed', [
                'event_id' => $this->event->id ?? null,
                'farmer_id' => $notifiable->id ?? null,
                'phone' => $notifiable->phone_number ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Build the email notification.
     */
    public function toMail(object $notifiable): \Illuminate\Mail\Mailable
    {
        $farmerName = 'Farmer';

        if (!empty($notifiable->name)) {

            $farmerName = $notifiable->name;

        } elseif (!empty($notifiable->first_name)) {

            $farmerName =
                $notifiable->first_name
                . ' '
                . ($notifiable->last_name ?? '');

        } elseif (!empty($notifiable->farmer)) {

            $farmerName =
                ($notifiable->farmer->first_name ?? 'Farmer')
                . ' '
                . ($notifiable->farmer->last_name ?? '');
        }

        /*
         * Get farmer email.
         */
        $targetEmail =
            $notifiable->email
            ?? $notifiable->farmer?->email
            ?? null;

        $mailable = new DistributionBeneficiaryMail(
            $this->event,
            trim($farmerName)
        );

        if ($targetEmail) {
            $mailable->to($targetEmail);
        }

        return $mailable;
    }

    /**
     * Return the SMS message.
     */
    public function toSms(object $notifiable): string
    {
        $title = $this->event->title
            ?? $this->event->name
            ?? 'Farm Supply Distribution';

        $venue = $this->event->venue
            ?? 'Barangay Hall';

        $dateSource = $this->event->distribution_date
            ?? $this->event->date;

        $date = $dateSource
            ? Carbon::parse($dateSource)->format('M d, Y')
            : '';

        return
            "AgriSure: You are scheduled to receive farm supply "
            . "assistance for {$title} on {$date} at {$venue}.";
    }
}
