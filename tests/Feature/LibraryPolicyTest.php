<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\BookCategory;
use App\Models\Loan;
use App\Models\LibraryPolicy;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LibraryPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_policies_and_members_cannot(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $member = User::factory()->create(['role' => 'member']);
        $payload = [
            'loan_period_days' => 10,
            'max_active_loans' => 4,
            'max_renewals' => 2,
            'fine_per_day_pkr' => 75,
            'max_active_reservations' => 3,
            'reservation_hold_days' => 4,
            'due_reminder_days' => 5,
        ];

        $this->get(route('library-policies.edit'))->assertRedirect(route('login'));
        $this->actingAs($member)->get(route('library-policies.edit'))->assertForbidden();
        $this->put(route('library-policies.update'), $payload)->assertForbidden();

        $this->actingAs($admin)->get(route('library-policies.edit'))->assertOk()->assertSee('Loan period (days)');
        $this->put(route('library-policies.update'), $payload)
            ->assertRedirect(route('library-policies.edit'))
            ->assertSessionHas('status', 'Library policies updated.');

        $this->assertSame($payload, LibraryPolicy::current()->only(array_keys($payload)));
    }

    public function test_invalid_policy_values_are_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->put(route('library-policies.update'), [
            'loan_period_days' => 0,
            'max_active_loans' => 21,
            'max_renewals' => -1,
            'fine_per_day_pkr' => -1,
            'max_active_reservations' => 0,
            'reservation_hold_days' => 15,
            'due_reminder_days' => 31,
        ])->assertSessionHasErrors([
            'loan_period_days',
            'max_active_loans',
            'max_renewals',
            'fine_per_day_pkr',
            'max_active_reservations',
            'reservation_hold_days',
            'due_reminder_days',
        ]);
    }

    public function test_policy_controls_loan_length_loan_limit_renewals_and_fine_amount(): void
    {
        $this->updatePolicy([
            'loan_period_days' => 7,
            'max_active_loans' => 1,
            'max_renewals' => 2,
            'fine_per_day_pkr' => 25,
        ]);
        [$book, $copies] = $this->createBook(2);
        $member = $this->createMember();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('loans.store'), [
            'member_card' => $member->library_card_number,
            'barcode' => $copies[0]->barcode,
        ])->assertRedirect(route('loans.index'));

        $loan = Loan::query()->firstOrFail();
        $this->assertSame(today()->addDays(7)->toDateString(), $loan->due_at->toDateString());
        $this->post(route('loans.store'), [
            'member_card' => $member->library_card_number,
            'barcode' => $copies[1]->barcode,
        ])->assertSessionHasErrors('member_card');

        $this->actingAs($member)->patch(route('loans.renew', $loan))->assertRedirect(route('loans.mine'));
        $loan->refresh();
        $this->assertSame(today()->addDays(14)->toDateString(), $loan->due_at->toDateString());
        $this->actingAs($member)->patch(route('loans.renew', $loan))->assertRedirect(route('loans.mine'));
        $this->actingAs($member)->patch(route('loans.renew', $loan))->assertSessionHasErrors('loan');

        $loan->update(['due_at' => today()->subDay()]);
        $this->actingAs($admin)->patch(route('loans.return', $loan))->assertRedirect(route('loans.index'));
        $this->assertDatabaseHas('fines', ['loan_id' => $loan->id, 'amount_pkr' => 25]);
    }

    public function test_reservation_limit_and_pickup_hold_use_configured_policy(): void
    {
        $this->updatePolicy([
            'max_active_reservations' => 1,
            'reservation_hold_days' => 4,
        ]);
        [$book, $copies] = $this->createBook(1);
        $copy = $copies[0];
        $copy->update(['status' => 'on_loan']);
        $book->update(['available_copies' => 0]);
        $member = $this->createMember();
        $admin = User::factory()->create(['role' => 'admin']);
        $loan = Loan::create([
            'user_id' => $member->id,
            'book_copy_id' => $copy->id,
            'issued_by' => $admin->id,
            'issued_at' => today(),
            'due_at' => today()->addDays(14),
        ]);

        $this->actingAs($member)->post(route('reservations.store', $book))
            ->assertRedirect(route('reservations.mine'));
        $secondBook = $this->createBook(1)[0];
        $secondBook->copies()->firstOrFail()->update(['status' => 'on_loan']);
        $secondBook->update(['available_copies' => 0]);
        $this->post(route('reservations.store', $secondBook))->assertSessionHasErrors('reservation');

        $this->actingAs($admin)->patch(route('loans.return', $loan))->assertRedirect(route('loans.index'));
        $reservation = Reservation::query()->firstOrFail();
        $this->assertSame('ready', $reservation->status);
        $this->assertSame(now()->addDays(4)->toDateTimeString(), $reservation->hold_expires_at->toDateTimeString());
    }

    private function updatePolicy(array $updates): void
    {
        $policy = LibraryPolicy::current();
        $policy->update($updates);
    }

    private function createBook(int $count): array
    {
        $category = BookCategory::create(['name' => 'Policy '.uniqid(), 'is_active' => true]);
        $book = Book::create([
            'isbn' => '978'.str_pad((string) random_int(0, 9999999999), 10, '0', STR_PAD_LEFT),
            'title' => 'Policy Test Book',
            'authors' => 'Policy Author',
            'category_id' => $category->id,
            'total_copies' => $count,
            'available_copies' => $count,
        ]);

        return [$book, $book->copies()->orderBy('copy_number')->get()];
    }

    private function createMember(): User
    {
        return User::factory()->create([
            'role' => 'member',
            'library_card_number' => 'LMS-'.uniqid(),
            'membership_status' => 'active',
            'membership_expires_at' => today()->addYear(),
        ]);
    }
}
