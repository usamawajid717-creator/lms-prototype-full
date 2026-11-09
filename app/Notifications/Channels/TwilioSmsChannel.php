<?php

namespace App\Notifications\Channels;

use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

class TwilioSmsChannel
{
    public function send(object $notifiable, object $notification): void
    {
        $config = config('services.twilio');
        $accountSid = $config['account_sid'];
        $authToken = $config['auth_token'];
        $messagingServiceSid = $config['messaging_service_sid'];
        $from = $config['from'];

        if (! $accountSid || ! $authToken || (! $messagingServiceSid && ! $from)) {
            throw new InvalidArgumentException('Twilio SMS is enabled but its account credentials and sender are not configured.');
        }

        if (! preg_match('/^\+[1-9]\d{7,14}$/', (string) $notifiable->phone)) {
            throw new InvalidArgumentException('SMS notifications require a phone number in E.164 format.');
        }

        $payload = [
            'To' => $notifiable->phone,
            'Body' => $notification->toSms($notifiable),
        ];
        if ($messagingServiceSid) {
            $payload['MessagingServiceSid'] = $messagingServiceSid;
        } else {
            $payload['From'] = $from;
        }

        Http::asForm()
            ->withBasicAuth($accountSid, $authToken)
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Messages.json", $payload)
            ->throw();
    }
}
