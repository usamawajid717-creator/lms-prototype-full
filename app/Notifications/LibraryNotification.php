<?php

namespace App\Notifications;

use App\Notifications\Channels\TwilioSmsChannel;
use App\Notifications\Channels\WebPushChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LibraryNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $title,
        private readonly string $message,
        private readonly string $url,
    ) {}

    public function via(object $notifiable): array
    {
        $channels = ['database', 'mail'];

        if (config('services.twilio.enabled') && $notifiable->sms_notifications_enabled) {
            $channels[] = TwilioSmsChannel::class;
        }

        if (config('services.webpush.enabled') && $notifiable->pushSubscriptions()->exists()) {
            $channels[] = WebPushChannel::class;
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title)
            ->greeting('Hello '.$notifiable->name)
            ->line($this->message)
            ->action('View in library', $this->url)
            ->salutation('Library Management System');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'url' => $this->url,
        ];
    }

    public function toSms(object $notifiable): string
    {
        return $this->title.': '.$this->message;
    }

    public function toPush(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'body' => $this->message,
            'url' => $this->url,
        ];
    }
}
