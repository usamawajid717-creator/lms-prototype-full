<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\BookCategory;
use App\Models\BookCopy;
use App\Models\Loan;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservationManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_member_can_reserve_a_book_with_no_available_copies(): void
    {
        [$book, $copy] = $this->createBook();
        $copy->update(['status' => 'on_loan']);
        $book->update(['available_copies' => 0]);
        $member = $this->createMember();

        $this->actingAs($member)->post(route('reservations.store', $book))
            ->assertRedirect(route('reservations.mine'))
            ->assertSessionHas('status', 'Reservation added to the waiting list.');

        $reservation = Reservation::query()->firstOrFail();
        $this->assertSame('waiting', $reservation->status);
        $this->actingAs($member)->get(route('reservations.mine'))->assertOk()->assertSee($book->title)->assertSee('Waiting');
    }

    public function test_reservation_requires_an_active_membership_and_unavailable_title(): void
    {
        [$book, $copy] = $this->createBook();
        $member = $this->createMember(['membership_status' => 'suspended']);

        $this->actingAs($member)->post(route('reservations.store', $book))
            ->assertSessionHasErrors('reservation');
        $this->assertDatabaseCount('reservations', 0);

        $member->update(['membership_status' => 'active']);
        $this->post(route('reservations.store', $book))
            ->assertSessionHasErrors('reservation');
        $this->assertDatabaseCount('reservations', 0);

        $member->update(['membership_expires_at' => today()->subDay()]);
        $copy->update(['status' => 'on_loan']);
        $book->update(['available_copies' => 0]);
        $this->post(route('reservations.store', $book))
            ->assertSessionHasErrors('reservation');
        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_returns_allocate_reserved_copies_in_fifo_order_and_only_the_member_can_borrow_it(): void
    {
        [, , $loan] = $this->issueLoan();
        $book = $loan->copy->book;
        $first = $this->createMember();
        $second = $this->createMember();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($first)->post(route('reservations.store', $book))->assertRedirect(route('reservations.mine'));
        $firstReservation = $first->reservations()->firstOrFail();
        $this->travel(1)->seconds();
        $this->actingAs($second)->post(route('reservations.store', $book))->assertRedirect(route('reservations.mine'));
        $secondReservation = $second->reservations()->firstOrFail();

        $this->actingAs($admin)->patch(route('loans.return', $loan))->assertRedirect(route('loans.index'));

        $this->assertSame('ready', $firstReservation->fresh()->status);
        $this->assertSame('waiting', $secondReservation->fresh()->status);
        $this->assertSame(now()->addDays(2)->toDateTimeString(), $firstReservation->fresh()->hold_expires_at->toDateTimeString());
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $first->id,
            'type' => \App\Notifications\LibraryNotification::class,
        ]);

        $copy = $loan->copy;
        $this->post(route('loans.store'), [
            'member_card' => $second->library_card_number,
            'barcode' => $copy->barcode,
        ])->assertSessionHasErrors('barcode');

        $this->post(route('loans.store'), [
            'member_card' => $first->library_card_number,
            'barcode' => $copy->barcode,
        ])->assertRedirect(route('loans.index'));

        $this->assertSame('fulfilled', $firstReservation->fresh()->status);
        $this->assertDatabaseHas('loans', [
            'reservation_id' => $firstReservation->id,
            'user_id' => $first->id,
        ]);
        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'available_copies' => 0,
        ]);
    }

    public function test_expired_pickup_hold_is_released_to_the_next_member(): void
    {
        [, , $loan] = $this->issueLoan();
        $book = $loan->copy->book;
        $first = $this->createMember();
        $second = $this->createMember();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($first)->post(route('reservations.store', $book));
        $firstReservation = $first->reservations()->firstOrFail();
        $this->travel(1)->seconds();
        $this->actingAs($second)->post(route('reservations.store', $book));
        $secondReservation = $second->reservations()->firstOrFail();
        $this->actingAs($admin)->patch(route('loans.return', $loan));
        $this->travel(3)->days();

        $this->actingAs($admin)->get(route('reservations.index'))->assertOk();

        $this->assertSame('expired', $firstReservation->fresh()->status);
        $this->assertSame('ready', $secondReservation->fresh()->status);
        $this->assertSame('reserved', $loan->copy->fresh()->status);
        $this->assertSame(2, $first->notifications()->where('type', \App\Notifications\LibraryNotification::class)->count());
    }

    public function test_scheduled_command_expires_pickup_holds_without_a_web_request(): void
    {
        [, , $loan] = $this->issueLoan();
        $member = $this->createMember();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($member)->post(route('reservations.store', $loan->copy->book));
        $reservation = $member->reservations()->firstOrFail();
        $this->actingAs($admin)->patch(route('loans.return', $loan));
        $this->travel(3)->days();

        $this->artisan('reservations:expire-holds')
            ->expectsOutput('Expired reservation holds have been processed.')
            ->assertExitCode(0);

        $this->assertSame('expired', $reservation->fresh()->status);
        $this->assertSame('available', $loan->copy->fresh()->status);
    }

    public function test_cancelling_a_ready_reservation_offers_the_copy_to_the_next_member(): void
    {
        [, , $loan] = $this->issueLoan();
        $book = $loan->copy->book;
        $first = $this->createMember();
        $second = $this->createMember();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($first)->post(route('reservations.store', $book));
        $firstReservation = $first->reservations()->firstOrFail();
        $this->travel(1)->seconds();
        $this->actingAs($second)->post(route('reservations.store', $book));
        $secondReservation = $second->reservations()->firstOrFail();
        $this->actingAs($admin)->patch(route('loans.return', $loan));

        $this->actingAs($first)->patch(route('reservations.cancel', $firstReservation))
            ->assertRedirect(route('reservations.mine'));

        $this->assertSame('cancelled', $firstReservation->fresh()->status);
        $this->assertSame('ready', $secondReservation->fresh()->status);
        $this->assertSame('reserved', $loan->copy->fresh()->status);
    }

    public function test_member_cannot_cancel_another_members_reservation(): void
    {
        [$book, $copy] = $this->createBook();
        $copy->update(['status' => 'on_loan']);
        $book->update(['available_copies' => 0]);
        $owner = $this->createMember();
        $other = $this->createMember();
        $this->actingAs($owner)->post(route('reservations.store', $book));
        $reservation = $owner->reservations()->firstOrFail();

        $this->actingAs($other)->patch(route('reservations.cancel', $reservation))->assertForbidden();
    }

    public function test_member_can_only_have_one_active_reservation_per_book_and_five_total(): void
    {
        $member = $this->createMember();
        $books = [];
        for ($number = 0; $number < 6; $number++) {
            [$book, $copy] = $this->createBook();
            $copy->update(['status' => 'on_loan']);
            $book->update(['available_copies' => 0]);
            $books[] = $book;
        }

        $this->actingAs($member)->post(route('reservations.store', $books[0]));
        $this->post(route('reservations.store', $books[0]))->assertSessionHasErrors('reservation');

        foreach (array_slice($books, 1, 4) as $book) {
            $this->post(route('reservations.store', $book))->assertRedirect(route('reservations.mine'));
        }

        $this->post(route('reservations.store', $books[5]))->assertSessionHasErrors('reservation');
        $this->assertSame(5, $member->reservations()->whereIn('status', ['waiting', 'ready'])->count());
    }

    private function createBook(): array
    {
        $category = BookCategory::create(['name' => 'Reservation '.uniqid(), 'is_active' => true]);
        $book = Book::create([
            'isbn' => '978'.str_pad((string) random_int(0, 9999999999), 10, '0', STR_PAD_LEFT),
            'title' => 'Test Reservation Book',
            'authors' => 'Reservation Author',
            'category_id' => $category->id,
            'total_copies' => 1,
            'available_copies' => 1,
        ]);

        return [$book, $book->copies()->firstOrFail()];
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

    private function issueLoan(): array
    {
        [$book, $copy] = $this->createBook();
        $member = $this->createMember();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('loans.store'), [
            'member_card' => $member->library_card_number,
            'barcode' => $copy->barcode,
        ])->assertRedirect(route('loans.index'));

        return [$book, $copy, Loan::query()->firstOrFail(), $member];
    }
}
