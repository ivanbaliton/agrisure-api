<?php

namespace App\Notifications;

use App\Models\DistributionEvent;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use App\Mail\DistributionBeneficiaryMail; 
use Carbon\Carbon;

class DistributionEventNotification extends Notification
{
    use Queueable;

    protected $event;
    protected $channels;

    /**
     * Create a new notification instance.
     */
    public function __construct(DistributionEvent $event, array $channels)
    {
        $this->event = $event;
        $this->channels = $channels; 
    }

    /**
     * Determine which channels the notification should be sent through.
     */
    public function via(object $notifiable): array
    {
        $activeChannels = [];
        $selectedChannels = array_map('strtolower', $this->channels ?? []);

        // 1. Fire custom push logic inline immediately to avoid driver validation errors entirely
        if (in_array('push', $selectedChannels) || in_array('app', $selectedChannels)) {
            $this->triggerDirectPush($notifiable);
        }

        // 2. Direct Mail verification routed to standard Laravel handler
        if (in_array('email', $selectedChannels)) {
            $activeChannels[] = 'mail';
        }

        // 3. SMS text routing
        if (in_array('sms', $selectedChannels)) {
            $activeChannels[] = 'sms'; 
        }

        return $activeChannels;
    }

    /**
     * Helper to execute the push service without breaking the driver engine
     */
    protected function triggerDirectPush(object $notifiable): void
    {
        try {
            $targetUserId = $notifiable->farmer_id ?? $notifiable->id;

            $title = $this->event->title ?? $this->event->name ?? 'Farm Supply Assistance';
            $dateSource = $this->event->distribution_date ?? $this->event->date;
            $formattedDate = $dateSource ? Carbon::parse($dateSource)->format('M d, Y') : '';
            $venue = $this->event->venue ?? 'Barangay Hall';

            $titlePayload = '🌾 Farm Supply Assistance Scheduled';
            $messagePayload = "You are scheduled to receive items for {$title} on {$formattedDate} at {$venue}.";

            NotificationService::send($targetUserId, $titlePayload, $messagePayload);
        } catch (\Exception $e) {
            // Silently capture or log internal push issues so email delivery is never blocked
            \Log::error("Push notification side-execution failed: " . $e->getMessage());
        }
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): \Illuminate\Mail\Mailable
    {
        // Resolve the farmer's name cleanly
        $farmerName = 'Farmer';
        if (!empty($notifiable->name)) {
            $farmerName = $notifiable->name;
        } elseif (!empty($notifiable->first_name)) {
            $farmerName = $notifiable->first_name . ' ' . ($notifiable->last_name ?? '');
        } elseif (!empty($notifiable->farmer)) {
            $farmerName = ($notifiable->farmer->first_name ?? 'Farmer') . ' ' . ($notifiable->farmer->last_name ?? '');
        }

        // Resolve the destination email address
        $targetEmail = $notifiable->email ?? $notifiable->farmer->email ?? null;

        $mailable = (new DistributionBeneficiaryMail($this->event, trim($farmerName)));

        if ($targetEmail) {
            $mailable->to($targetEmail);
        }

        return $mailable;
    }

    /**
     * Get the SMS representation.
     */
    public function toSms(object $notifiable): string
    {
        $title = $this->event->title ?? $this->event->name ?? 'Farm Supply Distribution';
        $venue = $this->event->venue ?? 'Barangay Hall';
        $dateSource = $this->event->distribution_date ?? $this->event->date;
        $date = $dateSource ? Carbon::parse($dateSource)->format('M d, Y') : '';

        return "AgriSure MAO: You are scheduled to receive farm supply assistance for {$title} on {$date} at the {$venue}.";
    }
}