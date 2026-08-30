<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Exception;

class SmsService
{
    /**
     * Send OTP SMS
     */
    public function sendOtp(string $phoneNumber, string $otpCode): array
    {
        $message = "AgriSure OTP: {$otpCode}. This code is valid for 3 minutes.";

        return $this->sendMessage($phoneNumber, $message);
    }

    /**
     * Send regular SMS
     */
    public function sendMessage(string $phoneNumber, string $message): array
    {
        $response = Http::asForm()->post(
            'https://semaphore.co/api/v4/messages',
            [
                'apikey' => config('services.semaphore.api_key'),
                'number' => $phoneNumber,
                'message' => $message,
                'sendername' => config(
                    'services.semaphore.sender_name',
                    'AgriSure'
                ),
            ]
        );

        // HTTP-level error
        if ($response->failed()) {
            throw new Exception(
                'Semaphore SMS failed: ' . $response->body()
            );
        }

        $data = $response->json();

        /*
         * Semaphore may return HTTP 200 while still reporting
         * an API validation error.
         *
         * Example:
         * {
         *     "sendername": [
         *         "The selected sendername is invalid."
         *     ]
         * }
         */

        if (
            isset($data['apikey']) ||
            isset($data['sendername']) ||
            isset($data['number']) ||
            isset($data['message'])
        ) {
            $errors = [];

            foreach ($data as $field => $messages) {
                if (is_array($messages)) {
                    $errors[] = $field . ': ' . implode(', ', $messages);
                } else {
                    $errors[] = $field . ': ' . $messages;
                }
            }

            throw new Exception(
                'Semaphore API error: ' . implode(' | ', $errors)
            );
        }

        return $data;
    }
}

