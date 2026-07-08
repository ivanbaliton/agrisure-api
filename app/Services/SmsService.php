<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Exception;

class SmsService
{
    public function sendOtp(string $phoneNumber, string $otpCode): array
    {
        $response = Http::withoutVerifying()
            ->asForm()
            ->post('https://semaphore.co/api/v4/messages', [
                'apikey' => config('services.semaphore.api_key'),
                'number' => $phoneNumber,
                'message' => "Your AgriSure OTP is {$otpCode}. This code is valid for 5 minutes.",
                'sendername' => config('services.semaphore.sender_name'),
            ]);

        if (!$response->successful()) {
            throw new Exception('Semaphore SMS failed: ' . $response->body());
        }

        return $response->json();
    }
}