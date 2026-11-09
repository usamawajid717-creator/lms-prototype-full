<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\BookCategory;
use App\Models\Loan;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_sees_only_their_loan_reservation_and_fine_information(): void
    {
        $member = $this->createMember('MEMBER');
        $otherMember = $this->createMember('OTHER');
        $admin = User::factory()->create(['role' => 'admin']);
        $loanBook = $this->createBook('My Dashboard Loan Book', 0);
        $loan = Loan::create([
            'user_id' => $member->id,
            'book_copy_id' => $loanBook->copies()->firstOrFail()->id,
            'issued_by' => $admin->id,
            'issued_at' => today()->subDays(10),
            'due_at' => today()->subDay(),
        ]);
        $loan->fine()->create(['amount_pkr' => 250]);

        $reservationBook = $this->createBook('My Dashboard Reservation Book', 0);
        Reservation::create([
            'user_id' => $member->id,
            'book_id' => $reservationBook->id,
            'book_copy_id' => $reservationBook->copies()->firstOrFail()->id,
            'status' => 'ready',
            'hold_expires_at' => now()->addDay(),
        ]);

        $otherBook = $this->createBook('Private Other Member Book', 0);
        $otherLoan = Loan::create([
            'user_id' => $otherMember->id,
            'book_copy_id' => $otherBook->copies()->firstOrFail()->id,
            'issued_by' => $admin->id,
            'issued_at' => today(),
            'due_at' => today()->addDays(14),
        ]);
        $otherLoan->fine()->create(['amount_pkr' => 999]);

        $this->actingAs($member)->get(route('dashboard.member'))
            ->assertOk()
            ->assertSee('Welcome, '.$member->name)
            ->assertSee($member->library_card_number)
            ->assertSee('My Dashboard Loan Book')
            ->assertSee('My Dashboard Reservation Book')
            ->assertSee('Overdue')
            ->assertSee('Ready')
            ->assertSee('PKR 250')
            ->assertSee('MEMBER')
            ->assertDontSee('Private Other Member Book')
            ->assertDontSee('PKR 999')
            ->assertViewHas('metrics', fn (array $metrics): bool => $metrics['active_loans'] === 1
                && $metrics['overdue_loans'] === 1
                && $metrics['ready_reservations'] === 1
                && $metrics['outstanding_fines_pkr'] === 250);
    }

    public function test_member_dashboard_is_restricted_to_members(): void
    {
        $this->get(route('dashboard.member'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('dashboard.member'))
            ->assertForbidden();
    }

    public function test_successful_login_redirects_members_to_their_dashboard(): void
    {
        $member = $this->createMember('LOGIN');
        $password = 'Member-password-123!';
        $member->update(['password' => $password]);

        $this->post(route('login.store'), [
            'email' => $member->email,
            'password' => $password,
        ])->assertRedirect(route('dashboard.member'));
    }

    private function createMember(string $suffix): User
    {
        return User::factory()->create([
            'role' => 'member',
            'library_card_number' => 'LMS-'.$suffix.'-'.uniqid(),
            'membership_status' => 'active',
            'membership_expires_at' => today()->addYear(),
        ]);
    }

    private function createBook(string $title, int $availableCopies): Book
    {
        $category = BookCategory::create(['name' => 'Member dashboard '.uniqid(), 'is_active' => true]);
        $book = Book::create([
            'isbn' => '978'.str_pad((string) random_int(0, 9999999999), 10, '0', STR_PAD_LEFT),
            'title' => $title,
            'authors' => 'Dashboard Author',
            'category_id' => $category->id,
            'total_copies' => 1,
            'available_copies' => $availableCopies,
        ]);

        return $book;
    }
}
