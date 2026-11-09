<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfilePasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_change_password_after_entering_current_password(): void
    {
        $user = User::factory()->create([
            'phone' => '555-0100',
            'password' => 'current-password',
        ]);

        $this->actingAs($user)->put(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'current_password' => 'current-password',
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ])->assertRedirect(route('profile.edit'));

        $this->assertTrue(Hash::check('new-secure-password', $user->fresh()->password));
    }

    public function test_password_change_requires_correct_current_password_and_confirmation(): void
    {
        $user = User::factory()->create([
            'phone' => '555-0100',
            'password' => 'current-password',
        ]);
        $payload = [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'current_password' => 'wrong-password',
            'password' => 'new-secure-password',
            'password_confirmation' => 'different-password',
        ];

        $this->actingAs($user)->put(route('profile.update'), $payload)
            ->assertSessionHasErrors(['current_password', 'password']);

        $this->assertTrue(Hash::check('current-password', $user->fresh()->password));
    }

    public function test_user_can_update_profile_without_changing_password(): void
    {
        $user = User::factory()->create([
            'phone' => '555-0100',
            'password' => 'current-password',
        ]);

        $this->actingAs($user)->put(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => '555-0101',
        ])->assertRedirect(route('profile.edit'));

        $this->assertTrue(Hash::check('current-password', $user->fresh()->password));
        $this->assertSame('555-0101', $user->fresh()->phone);
    }
}
