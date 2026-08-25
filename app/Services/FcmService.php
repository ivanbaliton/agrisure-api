<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class FcmService
{
    public static function sendPushNotification(string $deviceToken, string $title, string $body, array $data = []): bool
    {
        try {
            $accessToken = self::getAccessToken();
            $projectId = 'agrisure-f4a82';

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json',
            ])->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", [
                'message' => [
                    'token' => $deviceToken,
                    'notification' => [
                        'title' => $title,
                        'body' => $body,
                    ],
                    'data' => array_map('strval', $data),
                ],
            ]);

            return $response->successful();
        } catch (Exception $e) {
            Log::error('FCM Push Notification failed: ' . $e->getMessage());
            return false;
        }
    }

    private static function getAccessToken(): string
    {
        $credentialsPath = storage_path('app/firebase-service-account.json');

        if (!file_exists($credentialsPath)) {
            throw new Exception('Firebase credentials file missing at ' . $credentialsPath);
        }

        $credentials = json_decode(file_get_contents($credentialsPath), true);

        $header = base64_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $now = time();
        $claims = base64_encode(json_encode([
            'iss' => $credentials['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'exp' => $now + 3600,
            'iat' => $now,
        ]));

        $signatureInput = $header . '.' . $claims;
        $privateKey = $credentials['private_key'];

        openssl_sign($signatureInput, $signature, $privateKey, 'SHA256');
        $jwt = $signatureInput . '.' . base64_encode($signature);

        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ]);

        return $response->json('access_token');
    }
}