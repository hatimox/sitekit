<?php

namespace App\Notifications\Channels;

use Illuminate\Http\Client\RequestException;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DiscordWebhookChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        $webhookUrl = $notifiable->routeNotificationFor('discord', $notification);

        if (empty($webhookUrl)) {
            return;
        }

        if (!method_exists($notification, 'toDiscord')) {
            return;
        }

        $message = $notification->toDiscord($notifiable);

        try {
            $response = Http::timeout(10)->post($webhookUrl, $message);

            if ($response->failed()) {
                Log::warning('Discord webhook failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (RequestException $e) {
            Log::error('Discord webhook error', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
