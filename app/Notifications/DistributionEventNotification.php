<?php
namespace App\Notifications;

use App\Models\DistributionEvent;
use App\Services\NotificationService;
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

   public function via(object $notifiable): array
{
    $activeChannels = [];
    $selectedChannels = array_map('strtolower', $this->channels ?? []);

    // Fire custom push
    if (in_array('push', $selectedChannels) || in_array('app', $selectedChannels)) {
        $this->triggerDirectPush($notifiable);
    }

    // Fire custom SMS (executed directly, NOT returned in $activeChannels)
    if (in_array('sms', $selectedChannels)) {
        $this->triggerDirectSms($notifiable);
    }

    // Standard Laravel mail driver
    if (in_array('email', $selectedChannels)) {
        $activeChannels[] = 'mail';
    }

    return $activeChannels; // Never return 'sms' here
}
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

            // Make sure target user FCM token exists before dispatching
            NotificationService::send($targetUserId, $titlePayload, $messagePayload);
        } catch (\Exception $e) {
            \Log::error("Push notification execution failed: " . $e->getMessage());
        }
    }

    protected function triggerDirectSms(object $notifiable): void
{
    try {
        // Your Semaphore API request logic here
        // ...
    } catch (\Exception $e) {
        // Log Semaphore failure (e.g., zero credits) without crashing push/email
        \Log::error("SMS sending failed: " . $e->getMessage());
    }
}
    public function toMail(object $notifiable): \Illuminate\Mail\Mailable
    {
        $farmerName = 'Farmer';
        if (!empty($notifiable->name)) {
            $farmerName = $notifiable->name;
        } elseif (!empty($notifiable->first_name)) {
            $farmerName = $notifiable->first_name . ' ' . ($notifiable->last_name ?? '');
        } elseif (!empty($notifiable->farmer)) {
            $farmerName = ($notifiable->farmer->first_name ?? 'Farmer') . ' ' . ($notifiable->farmer->last_name ?? '');
        }

        $targetEmail = $notifiable->email ?? $notifiable->farmer->email ?? null;
        $mailable = (new DistributionBeneficiaryMail($this->event, trim($farmerName)));

        if ($targetEmail) {
            $mailable->to($targetEmail);
        }

        return $mailable;
    }

    public function toSms(object $notifiable): string
    {
        $title = $this->event->title ?? $this->event->name ?? 'Farm Supply Distribution';
        $venue = $this->event->venue ?? 'Barangay Hall';
        $dateSource = $this->event->distribution_date ?? $this->event->date;
        $date = $dateSource ? Carbon::parse($dateSource)->format('M d, Y') : '';

        return "AgriSure MAO: You are scheduled to receive farm supply assistance for {$title} on {$date} at {$venue}.";
    }
}