<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\LibraryNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_view_and_mark_own_notification_read(): void
    {
        $member = User::factory()->create(['role' => 'member']);
        $otherMember = User::factory()->create(['role' => 'member']);
        $member->notify(new LibraryNotification(
            'Book ready for pickup',
            'Your reserved book is ready.',
            route('reservations.mine'),
        ));
        $notification = $member->notifications()->firstOrFail();
        $otherMember->notify(new LibraryNotification(
            'Private update',
            'Only the other member can see this.',
            route('loans.mine'),
        ));
        $otherNotification = $otherMember->notifications()->firstOrFail();

        $this->actingAs($member)->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Book ready for pickup')
            ->assertSee('Your reserved book is ready.');

        $this->actingAs($member)->patch(route('notifications.read', $otherNotification->id))
            ->assertNotFound();

        $this->actingAs($member)->patch(route('notifications.read', $notification->id))
            ->assertRedirect(route('reservations.mine'));
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_member_can_mark_all_notifications_read(): void
    {
        $member = User::factory()->create(['role' => 'member']);
        $member->notify(new LibraryNotification(
            'First update',
            'First message.',
            route('loans.mine'),
        ));
        $member->notify(new LibraryNotification(
            'Second update',
            'Second message.',
            route('loans.mine'),
        ));

        $this->actingAs($member)->patch(route('notifications.read-all'))
            ->assertRedirect(route('notifications.index'));

        $this->assertSame(0, $member->unreadNotifications()->count());
    }

    public function test_library_notifications_are_delivered_in_app_and_by_email(): void
    {
        Notification::fake();
        $member = User::factory()->create(['role' => 'member']);
        $notification = new LibraryNotification(
            'Book ready for pickup',
            'Your reserved book is ready.',
            route('reservations.mine'),
        );

        $member->notify($notification);

        Notification::assertSentTo(
            $member,
            LibraryNotification::class,
            function (LibraryNotification $sent, array $channels) use ($member): bool {
                $mail = $sent->toMail($member);

                $this->assertSame(['database', 'mail'], $channels);
                $this->assertSame('Book ready for pickup', $mail->subject);
                $this->assertSame('View in library', $mail->actionText);
                $this->assertSame(route('reservations.mine'), $mail->actionUrl);
                $this->assertContains('Your reserved book is ready.', $mail->introLines);

                return true;
            },
        );
    }

    public function test_opted_in_member_receives_sms_through_configured_twilio_account(): void
    {
        Http::fake([
            'api.twilio.com/*' => Http::response(['sid' => 'SM123'], 201),
        ]);
        Mail::fake();
        config([
            'services.twilio.enabled' => true,
            'services.twilio.account_sid' => 'AC123',
            'services.twilio.auth_token' => 'test-token',
            'services.twilio.from' => '+15005550006',
            'services.twilio.messaging_service_sid' => null,
        ]);
        $member = User::factory()->create([
            'phone' => '+923001234567',
            'sms_notifications_enabled' => true,
        ]);

        $member->notify(new LibraryNotification(
            'Book ready',
            'Your reserved book is ready.',
            route('reservations.mine'),
        ));

        Http::assertSent(function (ClientRequest $request): bool {
            return $request->url() === 'https://api.twilio.com/2010-04-01/Accounts/AC123/Messages.json'
                && $request['To'] === '+923001234567'
                && $request['From'] === '+15005550006'
                && $request['Body'] === 'Book ready: Your reserved book is ready.';
        });
    }

    public function test_sms_is_not_sent_without_member_opt_in(): void
    {
        Http::fake();
        config([
            'services.twilio.enabled' => true,
            'services.twilio.account_sid' => 'AC123',
            'services.twilio.auth_token' => 'test-token',
            'services.twilio.from' => '+15005550006',
        ]);
        $member = User::factory()->create([
            'phone' => '+923001234567',
            'sms_notifications_enabled' => false,
        ]);

        $member->notify(new LibraryNotification('Update', 'Message.', route('loans.mine')));

        Http::assertNothingSent();
    }

    public function test_member_must_use_international_number_to_enable_sms(): void
    {
        $member = User::factory()->create(['phone' => '03001234567']);

        $this->actingAs($member)->put(route('profile.update'), [
            'name' => $member->name,
            'email' => $member->email,
            'phone' => '03001234567',
            'sms_notifications_enabled' => '1',
        ])->assertSessionHasErrors('phone');

        $this->assertFalse($member->fresh()->sms_notifications_enabled);
    }
}
