<?php

use App\Services\ReservationService;
use App\Services\LoanReminderService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Minishlink\WebPush\VAPID;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('reservations:expire-holds', function (ReservationService $reservations): void {
    $reservations->expireHolds();
    $this->info('Expired reservation holds have been processed.');
})->purpose('Expire reservation pickup holds and offer copies to the next member');

Schedule::command('reservations:expire-holds')->hourly();

Artisan::command('loans:send-reminders', function (LoanReminderService $reminders): void {
    $count = $reminders->sendDueDateReminders();
    $this->info("Sent {$count} loan reminder(s).");
})->purpose('Send due-soon and overdue loan reminders');

Schedule::command('loans:send-reminders')->dailyAt('09:00');

Artisan::command('webpush:vapid-keys', function (): void {
    $keys = VAPID::createVapidKeys();
    $this->line('VAPID_PUBLIC_KEY='.$keys['publicKey']);
    $this->line('VAPID_PRIVATE_KEY='.$keys['privateKey']);
    $this->warn('Keep VAPID_PRIVATE_KEY secret; add both values to .env and set WEBPUSH_ENABLED=true.');
})->purpose('Generate VAPID keys for browser push notifications');
