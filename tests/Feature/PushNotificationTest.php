<?php

namespace Tests\Feature;

use App\Models\PushSubscription;
use App\Models\User;
use App\Notifications\Channels\WebPushChannel;
use App\Notifications\LibraryNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PushNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_and_remove_own_browser_subscription(): void
    {
        $member = User::factory()->create(['role' => 'member']);
        $subscription = [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/test-endpoint',
            'keys' => ['p256dh' => 'test-public-key_123', 'auth' => 'test-auth-key_123'],
            'contentEncoding' => 'aes128gcm',
        ];

        $this->actingAs($member)->postJson(route('push-subscriptions.store'), $subscription)
            ->assertCreated();
        $this->assertDatabaseHas('push_subscriptions', [
            'user_id' => $member->id,
            'endpoint_hash' => hash('sha256', $subscription['endpoint']),
        ]);

        $this->actingAs($member)->deleteJson(route('push-subscriptions.destroy'), [
            'endpoint' => $subscription['endpoint'],
        ])->assertOk();
        $this->assertDatabaseCount('push_subscriptions', 0);
    }

    public function test_subscription_endpoint_rejects_non_https_and_non_push_hosts(): void
    {
        $member = User::factory()->create(['role' => 'member']);
        $payload = [
            'keys' => ['p256dh' => 'key123', 'auth' => 'auth123'],
        ];

        $this->actingAs($member)->postJson(route('push-subscriptions.store'), $payload + [
            'endpoint' => 'http://fcm.googleapis.com/fcm/send/test',
        ])->assertStatus(422);

        $this->postJson(route('push-subscriptions.store'), $payload + [
            'endpoint' => 'https://127.0.0.1/admin',
        ])->assertStatus(422);

        $this->assertDatabaseCount('push_subscriptions', 0);
    }

    public function test_user_cannot_remove_another_users_subscription(): void
    {
        $owner = User::factory()->create(['role' => 'member']);
        $other = User::factory()->create(['role' => 'member']);
        $endpoint = 'https://fcm.googleapis.com/fcm/send/owner-endpoint';
        PushSubscription::create([
            'user_id' => $owner->id,
            'endpoint' => $endpoint,
            'endpoint_hash' => hash('sha256', $endpoint),
            'public_key' => 'public',
            'auth_token' => 'auth',
        ]);

        $this->actingAs($other)->deleteJson(route('push-subscriptions.destroy'), [
            'endpoint' => $endpoint,
        ])->assertOk();

        $this->assertDatabaseHas('push_subscriptions', ['user_id' => $owner->id]);
    }

    public function test_push_channel_is_used_only_when_enabled_and_subscribed(): void
    {
        $member = User::factory()->create(['role' => 'member']);
        $notification = new LibraryNotification('Book ready', 'Your book is ready.', route('loans.mine'));
        config(['services.webpush.enabled' => true]);

        $this->assertNotContains(WebPushChannel::class, $notification->via($member));

        $endpoint = 'https://fcm.googleapis.com/fcm/send/member-endpoint';
        PushSubscription::create([
            'user_id' => $member->id,
            'endpoint' => $endpoint,
            'endpoint_hash' => hash('sha256', $endpoint),
            'public_key' => 'public',
            'auth_token' => 'auth',
        ]);

        $this->assertContains(WebPushChannel::class, $notification->via($member));
        $this->assertSame([
            'title' => 'Book ready',
            'body' => 'Your book is ready.',
            'url' => route('loans.mine'),
        ], $notification->toPush($member));
    }
}
