<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\BookCategory;
use App\Models\BookCopy;
use App\Models\Loan;
use App\Models\LibraryPolicy;
use App\Models\Fine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoanManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_issues_a_unique_copy_to_an_active_member(): void
    {
        [$book, $copy] = $this->createBook();
        $member = $this->createMember();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('loans.store'), [
            'member_card' => $member->library_card_number,
            'barcode' => $copy->barcode,
        ])->assertRedirect(route('loans.index'));

        $loan = Loan::query()->firstOrFail();
        $this->assertSame($member->id, $loan->user_id);
        $this->assertSame(today()->addDays(14)->toDateString(), $loan->due_at->toDateString());
        $this->assertDatabaseHas('book_copies', ['id' => $copy->id, 'status' => 'on_loan']);
        $this->assertDatabaseHas('books', ['id' => $book->id, 'available_copies' => 0]);
        $this->actingAs($admin)->get(route('loans.index'))->assertOk()->assertSee($member->library_card_number);
    }

    public function test_copy_cannot_be_issued_when_unavailable_or_member_membership_is_inactive(): void
    {
        [, $copy] = $this->createBook();
        $member = $this->createMember(['membership_status' => 'suspended']);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('loans.store'), [
            'member_card' => $member->library_card_number,
            'barcode' => $copy->barcode,
            'override_reason' => 'Attempting to bypass membership.',
        ])->assertSessionHasErrors('member_card');
        $this->assertDatabaseCount('loans', 0);

        $member->update(['membership_status' => 'active']);
        $member->update(['membership_expires_at' => today()->subDay()]);

        $this->actingAs($admin)->post(route('loans.store'), [
            'member_card' => $member->library_card_number,
            'barcode' => $copy->barcode,
        ])->assertSessionHasErrors('member_card');
        $this->assertDatabaseCount('loans', 0);

        $member->update(['membership_expires_at' => today()->addYear()]);
        $copy->update(['status' => 'on_loan']);

        $this->actingAs($admin)->post(route('loans.store'), [
            'member_card' => $member->library_card_number,
            'barcode' => $copy->barcode,
            'override_reason' => 'Attempting to bypass copy availability.',
        ])->assertSessionHasErrors('barcode');
        $this->assertDatabaseCount('loans', 0);
    }

    public function test_member_with_an_overdue_active_loan_cannot_be_issued_another_book_without_override(): void
    {
        [, , $overdueLoan, $member] = $this->issueLoan();
        $overdueLoan->update(['due_at' => today()->subDay()]);
        [, $newCopy] = $this->createBook();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('loans.store'), [
            'member_card' => $member->library_card_number,
            'barcode' => $newCopy->barcode,
        ])->assertSessionHasErrors(['member_card', 'override_reason']);

        $this->assertDatabaseCount('loans', 1);
        $this->assertDatabaseHas('book_copies', ['id' => $newCopy->id, 'status' => 'available']);
    }

    public function test_unpaid_fine_balance_above_configured_limit_blocks_issue_but_limit_is_inclusive(): void
    {
        LibraryPolicy::current()->update(['issue_fine_limit_pkr' => 100]);
        [, , $loan, $member] = $this->issueLoan();
        Fine::create(['loan_id' => $loan->id, 'amount_pkr' => 100]);
        [, $copyAtLimit] = $this->createBook();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('loans.store'), [
            'member_card' => $member->library_card_number,
            'barcode' => $copyAtLimit->barcode,
        ])->assertRedirect(route('loans.index'));

        $loan->fine()->update(['amount_pkr' => 101]);
        [, $copyAboveLimit] = $this->createBook();
        $this->post(route('loans.store'), [
            'member_card' => $member->library_card_number,
            'barcode' => $copyAboveLimit->barcode,
        ])->assertSessionHasErrors(['member_card', 'override_reason']);

        $this->assertDatabaseCount('loans', 2);
        $this->assertDatabaseHas('book_copies', ['id' => $copyAboveLimit->id, 'status' => 'available']);
    }

    public function test_admin_can_override_issue_blocks_with_audited_reason_but_not_omit_the_reason(): void
    {
        LibraryPolicy::current()->update(['issue_fine_limit_pkr' => 100]);
        [, , $overdueLoan, $member] = $this->issueLoan();
        $overdueLoan->update(['due_at' => today()->subDay()]);
        Fine::create(['loan_id' => $overdueLoan->id, 'amount_pkr' => 150]);
        [, $copy] = $this->createBook();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('loans.store'), [
            'member_card' => $member->library_card_number,
            'barcode' => $copy->barcode,
        ])->assertSessionHasErrors(['member_card', 'override_reason']);
        $this->assertDatabaseCount('loans', 1);

        $reason = 'Supervisor approved a one-time exception.';
        $this->post(route('loans.store'), [
            'member_card' => $member->library_card_number,
            'barcode' => $copy->barcode,
            'override_reason' => $reason,
        ])->assertRedirect(route('loans.index'));

        $overrideLoan = Loan::query()->where('book_copy_id', $copy->id)->firstOrFail();
        $this->assertSame($reason, $overrideLoan->issue_override_reason);
        $this->assertSame($admin->id, $overrideLoan->issue_overridden_by);
        $this->assertNotNull($overrideLoan->issue_overridden_at);
        $this->get(route('loans.index'))->assertOk()->assertSee($reason);
    }

    public function test_admin_return_restores_copy_availability(): void
    {
        [$book, $copy, $loan] = $this->issueLoan();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->patch(route('loans.return', $loan))
            ->assertRedirect(route('loans.index'));

        $this->assertDatabaseHas('loans', ['id' => $loan->id, 'returned_by' => $admin->id]);
        $this->assertDatabaseHas('book_copies', ['id' => $copy->id, 'status' => 'available']);
        $this->assertDatabaseHas('books', ['id' => $book->id, 'available_copies' => 1]);
    }

    public function test_admin_can_return_a_damaged_copy_with_condition_and_damage_charge(): void
    {
        [$book, $copy, $loan] = $this->issueLoan();
        $admin = User::factory()->create(['role' => 'admin']);
        $reason = 'Front cover torn and several pages stained.';

        $this->actingAs($admin)->patch(route('loans.return', $loan), [
            'condition' => 'damaged',
            'damage_condition_note' => $reason,
            'damage_fee_pkr' => 250,
        ])->assertRedirect(route('loans.index'))
            ->assertSessionHas('status', 'Book returned and marked damaged. Total fine/charges assessed: PKR 250.');

        $this->assertDatabaseHas('book_copies', [
            'id' => $copy->id,
            'status' => 'damaged',
            'condition_note' => $reason,
        ]);
        $this->assertDatabaseHas('loans', [
            'id' => $loan->id,
            'damage_fee_pkr' => 250,
            'damage_condition_note' => $reason,
        ]);
        $this->assertDatabaseHas('fines', ['loan_id' => $loan->id, 'amount_pkr' => 250]);
        $this->assertDatabaseHas('books', ['id' => $book->id, 'available_copies' => 0]);
    }

    public function test_damaged_return_requires_condition_note_and_combines_overdue_and_damage_charges(): void
    {
        [, $copy, $loan] = $this->issueLoan();
        $admin = User::factory()->create(['role' => 'admin']);
        $loan->update(['due_at' => today()->subDays(2)]);

        $this->actingAs($admin)->patch(route('loans.return', $loan), [
            'condition' => 'damaged',
            'damage_fee_pkr' => 100,
        ])->assertSessionHasErrors('damage_condition_note');
        $this->assertTrue($loan->fresh()->isActive());

        $this->patch(route('loans.return', $loan), [
            'condition' => 'damaged',
            'damage_condition_note' => 'Spine detached.',
            'damage_fee_pkr' => 100,
        ])->assertRedirect(route('loans.index'));

        $this->assertDatabaseHas('fines', ['loan_id' => $loan->id, 'amount_pkr' => 200]);
        $this->assertDatabaseHas('book_copies', ['id' => $copy->id, 'status' => 'damaged']);
    }

    public function test_returning_an_overdue_book_assesses_fifty_pkr_per_day_without_a_cap(): void
    {
        [, , $loan] = $this->issueLoan();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->travel(55)->days();

        $this->actingAs($admin)->patch(route('loans.return', $loan))
            ->assertRedirect(route('loans.index'))
            ->assertSessionHas('status', 'Book returned. Total fine/charges assessed: PKR 2,050.');

        $this->assertDatabaseHas('fines', [
            'loan_id' => $loan->id,
            'amount_pkr' => 2050,
            'paid_at' => null,
            'paid_by' => null,
        ]);
        $this->assertSame(1, $loan->member->notifications()
            ->where('type', \App\Notifications\LibraryNotification::class)->count());
    }

    public function test_on_time_return_does_not_create_a_fine(): void
    {
        [, , $loan] = $this->issueLoan();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->patch(route('loans.return', $loan))
            ->assertRedirect(route('loans.index'));

        $this->assertDatabaseCount('fines', 0);
    }

    public function test_unpaid_fine_blocks_renewal_until_admin_records_payment(): void
    {
        LibraryPolicy::current()->update(['issue_fine_limit_pkr' => 1000]);
        [, , $overdueLoan, $member] = $this->issueLoan();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->travel(18)->days();

        $this->actingAs($admin)->patch(route('loans.return', $overdueLoan));
        $fine = $overdueLoan->fresh()->fine;
        $this->assertSame(200, $fine->amount_pkr);

        [, $copy] = $this->createBook();
        $this->post(route('loans.store'), [
            'member_card' => $member->library_card_number,
            'barcode' => $copy->barcode,
        ])->assertRedirect(route('loans.index'));
        $renewableLoan = $member->loans()->whereNull('returned_at')->firstOrFail();

        $this->actingAs($member)->patch(route('loans.renew', $renewableLoan))
            ->assertSessionHasErrors('loan');

        $this->actingAs($admin)->patch(route('fines.pay', $fine))
            ->assertRedirect(route('loans.index'))
            ->assertSessionHas('status', 'Simulated payment recorded.');
        $this->assertDatabaseHas('fines', [
            'id' => $fine->id,
            'paid_by' => $admin->id,
        ]);
        $this->assertSame(2, $member->notifications()
            ->where('type', \App\Notifications\LibraryNotification::class)->count());

        $this->actingAs($member)->patch(route('loans.renew', $renewableLoan))
            ->assertRedirect(route('loans.mine'));
        $this->assertSame(1, $renewableLoan->fresh()->renewal_count);
    }

    public function test_only_admins_can_record_fine_payments_and_fines_cannot_be_paid_twice(): void
    {
        [, , $loan, $member] = $this->issueLoan();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->travel(18)->days();
        $this->actingAs($admin)->patch(route('loans.return', $loan));
        $fine = $loan->fresh()->fine;

        $this->actingAs($member)->patch(route('fines.pay', $fine))->assertForbidden();

        $this->actingAs($admin)->patch(route('fines.pay', $fine))
            ->assertRedirect(route('loans.index'));
        $paidAt = $fine->fresh()->paid_at;
        $this->patch(route('fines.pay', $fine))->assertSessionHasErrors('fine');
        $this->assertSame($paidAt->toDateTimeString(), $fine->fresh()->paid_at->toDateTimeString());
    }

    public function test_member_can_renew_own_active_loan_twice_by_default(): void
    {
        [, , $loan, $member] = $this->issueLoan();
        $originalDueDate = $loan->due_at->toDateString();

        $this->actingAs($member)->get(route('loans.mine'))->assertOk()->assertSee('Test Circulation Book');

        $this->actingAs($member)->patch(route('loans.renew', $loan))
            ->assertRedirect(route('loans.mine'));

        $loan->refresh();
        $this->assertSame(1, $loan->renewal_count);
        $this->assertSame(now()->parse($originalDueDate)->addDays(14)->toDateString(), $loan->due_at->toDateString());
        $this->get(route('loans.mine'))->assertSee('Renew 14 days');

        $this->actingAs($member)->patch(route('loans.renew', $loan))
            ->assertRedirect(route('loans.mine'));
        $this->assertSame(2, $loan->fresh()->renewal_count);

        $this->patch(route('loans.renew', $loan))->assertSessionHasErrors('loan');
        $this->assertSame(2, $loan->fresh()->renewal_count);
    }

    public function test_member_cannot_renew_another_members_loan(): void
    {
        [, , $loan] = $this->issueLoan();
        $otherMember = $this->createMember(['email' => 'other@example.test', 'library_card_number' => 'LMS-OTHER']);

        $this->actingAs($otherMember)->patch(route('loans.renew', $loan))->assertForbidden();
    }

    public function test_book_creation_generates_individually_barcoded_copies(): void
    {
        [$book] = $this->createBook(3);

        $copies = $book->copies()->orderBy('copy_number')->get();
        $this->assertCount(3, $copies);
        $this->assertSame(['LMS-'.$book->id.'-000001', 'LMS-'.$book->id.'-000002', 'LMS-'.$book->id.'-000003'], $copies->pluck('barcode')->all());
        $this->assertSame(['available', 'available', 'available'], $copies->pluck('status')->all());
    }

    public function test_admin_copy_status_updates_keep_catalog_availability_in_sync(): void
    {
        [$book, $copy] = $this->createBook();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->patch(route('catalog.admin.copies.update', [$book, $copy]), [
            'status' => 'unavailable',
        ])->assertRedirect();
        $this->assertDatabaseHas('books', ['id' => $book->id, 'available_copies' => 0]);

        $this->patch(route('catalog.admin.copies.update', [$book, $copy]), [
            'status' => 'available',
        ])->assertRedirect();
        $this->assertDatabaseHas('books', ['id' => $book->id, 'available_copies' => 1]);
    }

    public function test_admin_can_mark_a_copy_damaged_with_a_condition_note_and_restore_its_availability_later(): void
    {
        [$book, $copy] = $this->createBook();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->patch(route('catalog.admin.copies.update', [$book, $copy]), [
            'status' => 'damaged',
        ])->assertSessionHasErrors('condition_note');
        $this->assertDatabaseHas('books', ['id' => $book->id, 'available_copies' => 1]);

        $this->patch(route('catalog.admin.copies.update', [$book, $copy]), [
            'status' => 'damaged',
            'condition_note' => 'Water damage on the cover.',
        ])->assertRedirect();
        $this->assertDatabaseHas('book_copies', [
            'id' => $copy->id,
            'status' => 'damaged',
            'condition_note' => 'Water damage on the cover.',
        ]);
        $this->assertDatabaseHas('books', ['id' => $book->id, 'available_copies' => 0]);

        $this->patch(route('catalog.admin.copies.update', [$book, $copy]), [
            'status' => 'available',
        ])->assertRedirect();
        $this->assertDatabaseHas('books', ['id' => $book->id, 'available_copies' => 1]);
    }

    public function test_member_cannot_exceed_five_active_loans(): void
    {
        [, $firstCopy] = $this->createBook(6);
        $member = $this->createMember();
        $admin = User::factory()->create(['role' => 'admin']);
        $copies = BookCopy::query()->where('book_id', $firstCopy->book_id)->orderBy('copy_number')->get();

        foreach ($copies->take(5) as $copy) {
            $this->actingAs($admin)->post(route('loans.store'), [
                'member_card' => $member->library_card_number,
                'barcode' => $copy->barcode,
            ])->assertRedirect(route('loans.index'));
        }

        $this->actingAs($admin)->post(route('loans.store'), [
            'member_card' => $member->library_card_number,
            'barcode' => $copies->last()->barcode,
            'override_reason' => 'Attempting to bypass the active loan quota.',
        ])->assertSessionHasErrors('member_card');

        $this->assertDatabaseCount('loans', 5);
    }

    private function createBook(int $copies = 1): array
    {
        $category = BookCategory::create(['name' => 'Circulation '.uniqid(), 'is_active' => true]);
        $book = Book::create([
            'isbn' => '978'.str_pad((string) random_int(0, 9999999999), 10, '0', STR_PAD_LEFT),
            'title' => 'Test Circulation Book',
            'authors' => 'Test Author',
            'category_id' => $category->id,
            'total_copies' => $copies,
            'available_copies' => $copies,
        ]);

        return [$book, $book->copies()->orderBy('copy_number')->first()];
    }

    private function createMember(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'role' => 'member',
            'library_card_number' => 'LMS-'.uniqid(),
            'membership_status' => 'active',
            'membership_expires_at' => today()->addYear(),
        ], $attributes));
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
