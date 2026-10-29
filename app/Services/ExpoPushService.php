<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExpoPushService
{
    /**
     * Send a push notification via Expo
     *
     * @param string $to Expo Push Token
     * @param string $title Notification Title
     * @param string $body Notification Body
     * @param array $data Extra data for the notification
     * @return bool
     */
    public static function send($to, $title, $body, $data = [])
    {
        if (!$to || !preg_match('/^(ExponentPushToken|ExpoPushToken)\[[^\]]+\]$/', $to)) {
            return false;
        }

        try {
            $response = Http::timeout(20)->post('https://exp.host/--/api/v2/push/send', [
                'to' => $to,
                'title' => $title,
                'body' => $body,
                'data' => $data,
                'sound' => 'default',
                'priority' => 'high',
            ]);

            if ($response->successful() && $response->json('data.status') === 'ok') {
                return true;
            }

            Log::error('Expo Push Error: ' . $response->body());
            return false;
        } catch (\Exception $e) {
            Log::error('Expo Push Exception: ' . $e->getMessage());
            return false;
        }
    }
}
