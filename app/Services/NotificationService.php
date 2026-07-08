<?php

namespace App\Services;

use App\Models\User;
use App\Models\Notification;
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
        Notification::create([
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
        ]);

        $user = User::find($userId);

        if (!$user || !$user->fcm_token) {
            return;
        }

        $factory = (new Factory)
            ->withServiceAccount(storage_path('app/firebase/firebase_credentials.json'));

        $messaging = $factory->createMessaging();

        $firebaseMessage = CloudMessage::new()
            ->withNotification(
                FirebaseNotification::create($title, $message)
            )
            ->withToken($user->fcm_token);

        $messaging->send($firebaseMessage);
    }
}