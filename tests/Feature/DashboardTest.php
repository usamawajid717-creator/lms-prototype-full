<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\BookCategory;
use App\Models\Loan;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_library_statistics_and_recent_loans(): void
    {
        $category = BookCategory::create(['name' => 'Dashboard '.uniqid(), 'is_active' => true]);
        $book = Book::create([
            'isbn' => '978'.str_pad((string) random_int(0, 9999999999), 10, '0', STR_PAD_LEFT),
            'title' => 'Dashboard Test Book',
            'authors' => 'Dashboard Author',
            'category_id' => $category->id,
            'total_copies' => 1,
            'available_copies' => 0,
        ]);
        $member = User::factory()->create([
            'role' => 'member',
            'library_card_number' => 'LMS-'.uniqid(),
        ]);
        $admin = User::factory()->create(['role' => 'admin']);
        $loan = Loan::create([
            'user_id' => $member->id,
            'book_copy_id' => $book->copies()->firstOrFail()->id,
            'issued_by' => $admin->id,
            'issued_at' => today(),
            'due_at' => today()->subDay(),
        ]);
        $loan->fine()->create(['amount_pkr' => 75]);
        Reservation::create([
            'user_id' => $member->id,
            'book_id' => $book->id,
            'status' => 'waiting',
        ]);

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Statistics Dashboard')
            ->assertSee('mainNavigation')
            ->assertSee('Circulation')
            ->assertSee('dashboard-hero')
            ->assertSee('activity-chart')
            ->assertSee('Dashboard Test Book')
            ->assertSee($member->name)
            ->assertSee('Overdue')
            ->assertViewHas('metrics', fn (array $metrics): bool => $metrics['books'] === 1
                && $metrics['members'] === 1
                && $metrics['copies'] === 1
                && $metrics['available_copies'] === 0
                && $metrics['active_loans'] === 1
                && $metrics['overdue_loans'] === 1
                && $metrics['waiting_reservations'] === 1
                && $metrics['outstanding_fines_pkr'] === 75);
    }

    public function test_dashboard_is_admin_only(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create(['role' => 'member']))
            ->get(route('dashboard'))
            ->assertForbidden();
    }
}
