<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_sign_in_before_viewing_members(): void
    {
        $this->get(route('members.index'))->assertRedirect(route('login'));
    }

    public function test_admin_can_create_a_member_with_a_unique_library_card(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->post(route('members.store'), [
            'name' => 'Ayesha Khan',
            'email' => 'ayesha@example.test',
            'password' => 'member-password',
            'cnic' => '12345-1234567-1',
            'phone' => '03001234567',
            'address' => 'Lahore',
            'membership_type' => 'Student',
            'membership_status' => 'active',
            'membership_expires_at' => now()->addYear()->toDateString(),
        ]);

        $member = User::where('email', 'ayesha@example.test')->firstOrFail();

        $response->assertRedirect(route('members.index'));
        $this->assertSame('member', $member->role);
        $this->assertSame('LMS-'.str_pad((string) $member->id, 8, '0', STR_PAD_LEFT), $member->library_card_number);
        $this->assertTrue(password_verify('member-password', $member->password));
    }

    public function test_members_cannot_access_admin_member_creation(): void
    {
        $member = User::factory()->create(['role' => 'member']);

        $this->actingAs($member)->get(route('members.create'))->assertForbidden();
    }

    public function test_member_directory_does_not_expose_other_members_contact_details(): void
    {
        $viewer = User::factory()->create(['role' => 'member']);
        User::factory()->create([
            'name' => 'Other Member',
            'email' => 'other@example.test',
            'phone' => '03009998877',
            'role' => 'member',
            'membership_type' => 'Faculty',
        ]);

        $this->actingAs($viewer)
            ->get(route('members.index'))
            ->assertOk()
            ->assertSee('Other Member')
            ->assertDontSee('other@example.test')
            ->assertDontSee('03009998877');

        $this->get(route('members.index', ['search' => 'other@example.test']))
            ->assertDontSee('Other Member');
    }

    public function test_member_can_update_contact_details_without_changing_role(): void
    {
        $member = User::factory()->create(['role' => 'member']);

        $this->actingAs($member)->put(route('profile.update'), [
            'name' => $member->name,
            'email' => $member->email,
            'phone' => '03001112222',
            'address' => 'New address',
            'role' => 'admin',
        ])->assertRedirect(route('profile.edit'));

        $this->assertDatabaseHas('users', [
            'id' => $member->id,
            'phone' => '03001112222',
            'address' => 'New address',
            'role' => 'member',
        ]);
    }
}
