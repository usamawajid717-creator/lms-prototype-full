<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_authenticated_changes_are_audited_without_storing_submitted_values(): void
    {
        $member = User::factory()->create(['phone' => '555-0100']);

        $this->actingAs($member)->put(route('profile.update'), [
            'name' => $member->name,
            'email' => $member->email,
            'phone' => '555-0101',
            'address' => 'Updated address',
        ])->assertRedirect(route('profile.edit'));

        $auditLog = AuditLog::query()->firstOrFail();
        $this->assertSame($member->id, $auditLog->user_id);
        $this->assertSame('profile.update', $auditLog->event);
        $this->assertSame('PUT', $auditLog->method);
        $this->assertNull($auditLog->subject_id);
        $this->assertDatabaseMissing('audit_logs', ['event' => 'Updated address']);
    }

    public function test_failed_validation_is_not_audited(): void
    {
        $member = User::factory()->create();

        $this->actingAs($member)->put(route('profile.update'), [
            'name' => '',
            'email' => $member->email,
            'phone' => '555-0100',
        ])->assertSessionHasErrors('name');

        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_only_admins_can_view_audit_logs(): void
    {
        $this->get(route('audit-logs.index'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create(['role' => 'member']))
            ->get(route('audit-logs.index'))
            ->assertForbidden();

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('audit-logs.index'))
            ->assertOk()
            ->assertSee('Audit Log');
    }
}
