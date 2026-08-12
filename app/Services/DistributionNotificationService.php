<?php

namespace App\Services;

use App\Models\DistributionEvent;
use App\Models\User;
use App\Models\NotificationLog;
use App\Notifications\DistributionEventNotification;
use Exception;
use Illuminate\Support\Collection;

class DistributionNotificationService
{
    /**
     * Dispatch multi-channel notifications to selected beneficiaries and log the results.
     */
   /**
     * Dispatch multi-channel notifications to selected beneficiaries and log the results.
     */
    public function notifyFarmers(DistributionEvent $event, Collection $farmers, array $channels): array
    {
        $successCount = 0;
        $failureCount = 0;

        foreach ($farmers as $farmerRow) {
            try {
                // 1. SAFELY RESOLVE THE NOTIFIABLE MODEL
                // If the loop yields a pivot record, extract the 'farmer' or 'user' relation model instance.
                // If it's already a clean User/Farmer model, use it directly.
                $actualNotifiable = null;

                if ($farmerRow instanceof \App\Models\User || $farmerRow instanceof \App\Models\Farmer) {
                    $actualNotifiable = $farmerRow;
                } elseif (!empty($farmerRow->farmer)) {
                    $actualNotifiable = $farmerRow->farmer; // Unwraps the relationship model
                } elseif (!empty($farmerRow->user)) {
                    $actualNotifiable = $farmerRow->user;   // Alternative fallback relationship name
                }

                // If we completely fail to resolve a target entity, record a failure and skip
                if (!$actualNotifiable) {
                    throw new Exception("Unable to resolve a valid User or Farmer model instance from the payload data structure.");
                }

                // 2. Trigger the notification against the resolved model instance
                $actualNotifiable->notify(new DistributionEventNotification($event, $channels));
                
                // 3. Log success for each active channel requested
                $this->logNotification($event->id, $actualNotifiable->id, $channels, 'sent');
                
                $successCount++;
            } catch (Exception $e) {
                // 4. Log failures gracefully if a channel, mailer, or provider breaks
                // Extract target ID safely for logs
                $logFarmerId = isset($actualNotifiable) ? $actualNotifiable->id : ($farmerRow->farmer_id ?? $farmerRow->id ?? 0);
                
                $this->logNotification($event->id, $logFarmerId, $channels, 'failed', substr($e->getMessage(), 0, 500));
                
                $failureCount++;
            }
        }

        return [
            'farmers_processed' => $farmers->count(),
            'successful_deliveries' => $successCount,
            'failed_deliveries' => $failureCount,
        ];
    }
    /**
     * Helper method to write records into the notification_logs table.
     */
    protected function logNotification(int $eventId, int $farmerId, array $channels, string $status, ?string $errorMessage = null): void
    {
        foreach ($channels as $channel) {
            NotificationLog::create([
                'distribution_event_id' => $eventId,
                'farmer_id' => $farmerId,
                'channel' => $channel,
                'status' => $status,
                'error_message' => $errorMessage
            ]);
        }
    }
}