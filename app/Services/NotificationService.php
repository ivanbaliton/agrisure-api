<?php

namespace App\Services;

use App\Models\User;
use App\Models\Notification;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification as FirebaseNotification;

class NotificationService
{
    public static function send(
        int $userId,
        string $title,
        string $message
    ): void {
        // 1. In-App Notification (Database)
        try {
            Notification::create([
                'user_id' => $userId,
                'title'   => $title,
                'message' => $message,
            ]);
        } catch (\Throwable $e) {
            Log::error("Failed saving in-app notification for user {$userId}: " . $e->getMessage());
        }

        // 2. Fetch User & FCM Token
        $user = User::find($userId);

        if (!$user) {
            Log::warning("Notification aborted: User ID {$userId} does not exist.");
            return;
        }

        $fcmToken = $user->fcm_token ?? $user->device_token ?? null;

        if (empty($fcmToken)) {
            Log::info("Push notification skipped: User ID {$userId} has no fcm_token registered.");
            return;
        }

        // 3. FCM Push Notification via Kreait SDK
        $credentialsPath = storage_path('app/firebase/firebase_credentials.json');

        if (!file_exists($credentialsPath)) {
            Log::error("FCM Push failed: Credentials file missing at {$credentialsPath}");
            return;
        }

        try {
            $factory = (new Factory)->withServiceAccount($credentialsPath);
            $messaging = $factory->createMessaging();

            $cloudMessage = CloudMessage::new()
                ->withNotification(FirebaseNotification::create($title, $message))
                ->withData(['user_id' => (string) $userId])
                ->withToken($fcmToken);

            $messaging->send($cloudMessage);
            Log::info("FCM Push successfully sent to User ID {$userId}.");
        } catch (\Throwable $e) {
            Log::error("FCM Push failed for User ID {$userId}: " . $e->getMessage());
        }
    }
}