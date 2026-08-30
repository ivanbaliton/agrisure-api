<?php

namespace App\Services;

use App\Models\User;
use App\Models\Notification;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification as FirebaseNotification;
use Throwable;

class NotificationService
{
    /**
     * Send an in-app notification and FCM push notification.
     *
     * IMPORTANT:
     * This method returns a result array so the caller can determine
     * whether the database notification and push notification succeeded.
     */
    public static function send(
        int $userId,
        string $title,
        string $message
    ): array {

        $inAppSent = false;
        $pushSent = false;

        /*
         * ==========================================================
         * 1. FIND USER
         * ==========================================================
         */
        $user = User::find($userId);

        if (!$user) {

            Log::error(
                'Notification failed: User not found.',
                [
                    'user_id' => $userId,
                    'title'   => $title,
                ]
            );

            return [
                'success'      => false,
                'in_app_sent'  => false,
                'push_sent'    => false,
                'message'      => 'User not found.',
            ];
        }

        /*
         * ==========================================================
         * 2. SAVE IN-APP NOTIFICATION
         * ==========================================================
         */
        try {

            Notification::create([
                'user_id' => $user->id,
                'title'   => $title,
                'message' => $message,
            ]);

            $inAppSent = true;

            Log::info(
                'In-app notification saved successfully.',
                [
                    'user_id' => $user->id,
                    'title'   => $title,
                ]
            );

        } catch (Throwable $e) {

            Log::error(
                'In-app notification failed.',
                [
                    'user_id' => $user->id,
                    'title'   => $title,
                    'error'   => $e->getMessage(),
                ]
            );
        }

        /*
         * ==========================================================
         * 3. GET FCM TOKEN
         * ==========================================================
         */

        $fcmToken =
            $user->fcm_token
            ?? $user->device_token
            ?? null;

        if (empty($fcmToken)) {

            Log::warning(
                'FCM push skipped: User has no FCM token.',
                [
                    'user_id' => $user->id,
                    'title'   => $title,
                ]
            );

            return [
                'success'      => $inAppSent,
                'in_app_sent'  => $inAppSent,
                'push_sent'    => false,
                'message'      => $inAppSent
                    ? 'In-app notification saved, but no FCM token exists.'
                    : 'In-app notification and push notification failed.',
            ];
        }

        /*
         * ==========================================================
         * 4. FIREBASE CREDENTIALS
         * ==========================================================
         */

        $credentialsPath = storage_path(
            'app/firebase/firebase_credentials.json'
        );

        if (!file_exists($credentialsPath)) {

            Log::error(
                'FCM push failed: Firebase credentials file not found.',
                [
                    'user_id' => $user->id,
                    'path'    => $credentialsPath,
                ]
            );

            return [
                'success'      => $inAppSent,
                'in_app_sent'  => $inAppSent,
                'push_sent'    => false,
                'message'      => $inAppSent
                    ? 'In-app notification saved, but Firebase credentials are missing.'
                    : 'Notification failed.',
            ];
        }

        /*
         * ==========================================================
         * 5. SEND FCM PUSH
         * ==========================================================
         */

        try {

            $factory = (new Factory)
                ->withServiceAccount($credentialsPath);

            $messaging = $factory->createMessaging();

            /*
             * Firebase notification payload.
             */
            $firebaseNotification =
                FirebaseNotification::create(
                    $title,
                    $message
                );

            /*
             * Additional data sent to Flutter.
             */
            $data = [
                'user_id' => (string) $user->id,
                'title'   => $title,
                'message' => $message,
            ];

            $cloudMessage = CloudMessage::new()
                ->withNotification($firebaseNotification)
                ->withData($data)
                ->withToken($fcmToken);

            /*
             * Send the notification.
             */
            $messaging->send($cloudMessage);

            $pushSent = true;

            Log::info(
                'FCM push notification sent successfully.',
                [
                    'user_id' => $user->id,
                    'title'   => $title,
                ]
            );

        } catch (Throwable $e) {

            Log::error(
                'FCM push notification FAILED.',
                [
                    'user_id' => $user->id,
                    'title'   => $title,
                    'error'   => $e->getMessage(),
                ]
            );
        }

        /*
         * ==========================================================
         * 6. FINAL RESULT
         * ==========================================================
         */

        return [
            'success'      => $inAppSent || $pushSent,
            'in_app_sent'  => $inAppSent,
            'push_sent'    => $pushSent,
            'message'      => match (true) {
                $inAppSent && $pushSent =>
                    'In-app and push notification sent successfully.',

                $inAppSent && !$pushSent =>
                    'In-app notification saved, but push notification failed.',

                !$inAppSent && $pushSent =>
                    'Push notification sent, but in-app notification failed.',

                default =>
                    'Both in-app and push notifications failed.',
            },
        ];
    }
}

