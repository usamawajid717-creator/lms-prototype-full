<?php

namespace App\Notifications\Channels;

use App\Models\PushSubscription;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use RuntimeException;

class WebPushChannel
{
    public function send(object $notifiable, object $notification): void
    {
        $config = config('services.webpush');
        if (blank($config['public_key']) || blank($config['private_key']) || blank($config['subject'])) {
            throw new RuntimeException('Web Push is enabled but VAPID keys and subject are not configured.');
        }

        $webPush = new WebPush([
            'VAPID' => [
                'subject' => $config['subject'],
                'publicKey' => $config['public_key'],
                'privateKey' => $config['private_key'],
            ],
        ]);
        $payload = json_encode($notification->toPush($notifiable), JSON_THROW_ON_ERROR);

        foreach ($notifiable->pushSubscriptions as $pushSubscription) {
            $report = $webPush->sendOneNotification(
                Subscription::create([
                    'endpoint' => $pushSubscription->endpoint,
                    'publicKey' => $pushSubscription->public_key,
                    'authToken' => $pushSubscription->auth_token,
                    'contentEncoding' => $pushSubscription->content_encoding,
                ]),
                $payload,
                ['TTL' => 86400],
            );

            if ($report->isSubscriptionExpired()) {
                $pushSubscription->delete();

                continue;
            }

            if (! $report->isSuccess()) {
                Log::warning('Web Push delivery failed.', [
                    'subscription_id' => $pushSubscription->id,
                    'reason' => $report->getReason(),
                ]);
            }
        }
    }
}
