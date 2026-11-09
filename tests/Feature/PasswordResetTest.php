<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_and_password_reset_pages_do_not_show_application_navigation(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee('appSidebar')
            ->assertDontSee('mobile-topbar')
            ->assertDontSee('app-topbar');

        $this->get(route('password.request'))
            ->assertOk()
            ->assertDontSee('appSidebar');
    }

    public function test_user_can_request_a_reset_link_and_reset_password_once(): void
    {
        Notification::fake();
        $user = User::factory()->create(['password' => 'old-password']);

        $this->get(route('password.request'))->assertOk()->assertSee('Reset your password');
        $this->post(route('password.email'), ['email' => $user->email])
            ->assertRedirect()
            ->assertSessionHas('status');

        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use (&$token): bool {
            $token = $notification->token;

            return true;
        });
        $this->assertNotNull($token);

        $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]))
            ->assertOk()
            ->assertSee('Choose a new password');
        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ])->assertRedirect(route('login'))->assertSessionHas('status');

        $this->assertTrue(Hash::check('new-secure-password', $user->fresh()->password));
        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'another-secure-password',
            'password_confirmation' => 'another-secure-password',
        ])->assertSessionHasErrors('email');
    }

    public function test_password_reset_requires_confirmed_strong_enough_password(): void
    {
        $user = User::factory()->create();

        $this->post(route('password.update'), [
            'token' => 'invalid',
            'email' => $user->email,
            'password' => 'short',
            'password_confirmation' => 'different',
        ])->assertSessionHasErrors('password');
    }

    public function test_unknown_email_gets_same_reset_request_response_without_notification(): void
    {
        Notification::fake();

        $response = $this->from(route('password.request'))->post(route('password.email'), [
            'email' => 'unknown@example.test',
        ]);

        $response->assertRedirect(route('password.request'))
            ->assertSessionHas('status', 'If an account exists for that email, a password reset link has been sent.');
        Notification::assertNothingSent();
    }

    public function test_authenticated_users_cannot_access_password_reset_forms(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('password.request'))
            ->assertRedirect(route('home'));
    }
}
