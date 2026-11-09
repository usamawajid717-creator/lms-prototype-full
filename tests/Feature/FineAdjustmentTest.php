<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\BookCategory;
use App\Models\Fine;
use App\Models\Loan;
use App\Models\User;
use App\Notifications\LibraryNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class FineAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_adjust_unpaid_fine_and_reason_is_audited(): void
    {
        Notification::fake();
        [$fine, $admin] = $this->createFine();

        $this->actingAs($admin)->patch(route('fines.adjust', $fine), [
            'action' => 'adjust',
            'amount_pkr' => 80,
            'reason' => 'Partial credit approved.',
        ])->assertRedirect(route('loans.index'))->assertSessionHas('status', 'Fine amount adjusted.');

        $this->assertSame(80, $fine->fresh()->amount_pkr);
        $this->assertNull($fine->fresh()->waived_at);
        $this->assertDatabaseHas('fine_adjustments', [
            'fine_id' => $fine->id,
            'admin_id' => $admin->id,
            'action' => 'adjust',
            'previous_amount_pkr' => 150,
            'new_amount_pkr' => 80,
            'reason' => 'Partial credit approved.',
        ]);
        Notification::assertSentTo($fine->loan->member, LibraryNotification::class);
    }

    public function test_admin_can_waive_fine_with_reason_and_member_can_renew(): void
    {
        Notification::fake();
        [$fine, $admin] = $this->createFine();

        $this->actingAs($admin)->patch(route('fines.adjust', $fine), [
            'action' => 'waive',
            'reason' => 'Returned during a documented closure.',
        ])->assertRedirect(route('loans.index'))->assertSessionHas('status', 'Fine waived.');

        $fine->refresh();
        $this->assertSame(0, $fine->amount_pkr);
        $this->assertNotNull($fine->waived_at);
        $this->assertSame($admin->id, $fine->waived_by);
        $this->assertSame('Returned during a documented closure.', $fine->waived_reason);
        $this->assertDatabaseHas('fine_adjustments', [
            'fine_id' => $fine->id,
            'action' => 'waive',
            'previous_amount_pkr' => 150,
            'new_amount_pkr' => 0,
        ]);

        $member = $fine->loan->member;
        $activeLoan = $this->createActiveLoan($member, $admin);
        $this->actingAs($member)->patch(route('loans.renew', $activeLoan))
            ->assertRedirect(route('loans.mine'));
    }

    public function test_only_admins_can_adjust_and_reason_is_required(): void
    {
        [$fine] = $this->createFine();
        $member = $fine->loan->member;

        $this->actingAs($member)->patch(route('fines.adjust', $fine), [
            'action' => 'waive',
            'reason' => 'Valid reason.',
        ])->assertForbidden();

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->patch(route('fines.adjust', $fine), [
            'action' => 'adjust',
            'amount_pkr' => 150,
            'reason' => '',
        ])->assertSessionHasErrors('reason');

        $this->patch(route('fines.adjust', $fine), [
            'action' => 'adjust',
            'amount_pkr' => 150,
            'reason' => 'No change requested.',
        ])->assertSessionHasErrors('amount_pkr');

        $this->assertSame(150, $fine->fresh()->amount_pkr);
        $this->assertDatabaseCount('fine_adjustments', 0);
    }

    public function test_paid_fine_cannot_be_adjusted_or_waived(): void
    {
        [$fine, $admin] = $this->createFine();
        $fine->update(['paid_at' => now(), 'paid_by' => $admin->id]);

        $this->actingAs($admin)->patch(route('fines.adjust', $fine), [
            'action' => 'waive',
            'reason' => 'This should not apply.',
        ])->assertSessionHasErrors('fine');

        $this->assertNull($fine->fresh()->waived_at);
        $this->assertDatabaseCount('fine_adjustments', 0);
    }

    private function createFine(): array
    {
        $category = BookCategory::create(['name' => 'Fine adjustments '.uniqid(), 'is_active' => true]);
        $book = Book::create([
            'isbn' => '978'.str_pad((string) random_int(0, 9999999999), 10, '0', STR_PAD_LEFT),
            'title' => 'Fine Adjustment Book',
            'authors' => 'Fine Author',
            'category_id' => $category->id,
            'total_copies' => 1,
            'available_copies' => 0,
        ]);
        $member = User::factory()->create(['role' => 'member']);
        $admin = User::factory()->create(['role' => 'admin']);
        $loan = Loan::create([
            'user_id' => $member->id,
            'book_copy_id' => $book->copies()->firstOrFail()->id,
            'issued_by' => $admin->id,
            'issued_at' => today()->subDays(20),
            'due_at' => today()->subDays(6),
            'returned_at' => now(),
            'returned_by' => $admin->id,
        ]);

        return [$loan->fine()->create(['amount_pkr' => 150]), $admin];
    }

    private function createActiveLoan(User $member, User $admin): Loan
    {
        $category = BookCategory::create(['name' => 'Renew after waiver '.uniqid(), 'is_active' => true]);
        $book = Book::create([
            'isbn' => '978'.str_pad((string) random_int(0, 9999999999), 10, '0', STR_PAD_LEFT),
            'title' => 'Renewable Book',
            'authors' => 'Renewal Author',
            'category_id' => $category->id,
            'total_copies' => 1,
            'available_copies' => 0,
        ]);

        return Loan::create([
            'user_id' => $member->id,
            'book_copy_id' => $book->copies()->firstOrFail()->id,
            'issued_by' => $admin->id,
            'issued_at' => today(),
            'due_at' => today()->addDays(7),
        ]);
    }
}
