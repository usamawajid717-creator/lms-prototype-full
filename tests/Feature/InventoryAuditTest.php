<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\BookCategory;
use App\Models\BookCopy;
use App\Models\InventoryAudit;
use App\Models\InventoryAuditItem;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_start_scan_complete_and_reconcile_missing_copy(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $book = $this->createBook();
        $copies = $book->copies()->orderBy('copy_number')->get();

        $this->actingAs($admin)->post(route('inventory-audits.store'), [
            'name' => 'November shelf check',
        ])->assertRedirect();

        $audit = InventoryAudit::query()->firstOrFail();
        $this->assertSame(2, $audit->items()->where('expected', true)->count());

        $this->post(route('inventory-audits.scan', $audit), ['barcode' => $copies[0]->barcode])
            ->assertRedirect(route('inventory-audits.show', $audit));
        $this->assertNotNull($audit->items()->where('barcode', $copies[0]->barcode)->firstOrFail()->scanned_at);

        $this->patch(route('inventory-audits.complete', $audit))
            ->assertRedirect(route('inventory-audits.show', $audit));
        $missingItem = $audit->items()->where('barcode', $copies[1]->barcode)->firstOrFail();

        $this->patch(route('inventory-audits.items.lost', [$audit, $missingItem]))
            ->assertRedirect(route('inventory-audits.show', $audit));

        $this->assertDatabaseHas('book_copies', ['id' => $copies[1]->id, 'status' => 'lost']);
        $this->assertDatabaseHas('books', ['id' => $book->id, 'available_copies' => 1]);
        $this->assertNotNull($missingItem->fresh()->reconciled_at);
        $this->get(route('inventory-audits.show', $audit))
            ->assertOk()
            ->assertSee('November shelf check')
            ->assertSee('Marked lost');
    }

    public function test_scan_flags_unknown_and_non_shelf_copies_as_unexpected_and_rejects_duplicate(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $book = $this->createBook();
        $copy = $book->copies()->firstOrFail();
        $this->createLoan($admin, $book, $copy);

        $this->actingAs($admin)->post(route('inventory-audits.store'), ['name' => 'Unexpected scan audit']);
        $audit = InventoryAudit::query()->firstOrFail();

        $this->post(route('inventory-audits.scan', $audit), ['barcode' => 'UNKNOWN-123'])
            ->assertSessionHas('status', 'Barcode UNKNOWN-123 is not registered in the catalog and was recorded as unexpected.');
        $this->post(route('inventory-audits.scan', $audit), ['barcode' => $copy->barcode])
            ->assertSessionHas('status', "Copy {$copy->barcode} was scanned but was not expected on the shelf.");
        $this->post(route('inventory-audits.scan', $audit), ['barcode' => 'UNKNOWN-123'])
            ->assertSessionHasErrors('barcode');

        $this->assertSame(2, $audit->items()->where('expected', false)->count());
    }

    public function test_open_audit_prevents_another_and_completed_audit_rejects_scans(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->post(route('inventory-audits.store'), ['name' => 'First audit']);
        $audit = InventoryAudit::query()->firstOrFail();

        $this->post(route('inventory-audits.store'), ['name' => 'Second audit'])
            ->assertSessionHasErrors('audit');
        $this->patch(route('inventory-audits.complete', $audit));
        $this->post(route('inventory-audits.scan', $audit), ['barcode' => 'UNKNOWN-456'])
            ->assertSessionHasErrors('audit');
    }

    public function test_missing_copy_that_became_on_loan_is_not_marked_lost(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $book = $this->createBook();
        $copy = $book->copies()->firstOrFail();
        $this->actingAs($admin)->post(route('inventory-audits.store'), ['name' => 'Concurrent circulation']);
        $audit = InventoryAudit::query()->firstOrFail();
        $this->patch(route('inventory-audits.complete', $audit));
        $copy->update(['status' => 'on_loan']);
        $book->decrement('available_copies');
        $item = $audit->items()->where('book_copy_id', $copy->id)->firstOrFail();

        $this->patch(route('inventory-audits.items.lost', [$audit, $item]))
            ->assertSessionHasErrors('copy');

        $this->assertSame('on_loan', $copy->fresh()->status);
    }

    public function test_inventory_audit_routes_are_admin_only(): void
    {
        $member = User::factory()->create(['role' => 'member']);
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->post(route('inventory-audits.store'), ['name' => 'Protected audit']);
        $audit = InventoryAudit::query()->firstOrFail();

        $this->actingAs($member)->get(route('inventory-audits.index'))->assertForbidden();
        $this->get(route('inventory-audits.show', $audit))->assertForbidden();
        $this->post(route('inventory-audits.scan', $audit), ['barcode' => 'UNKNOWN-789'])->assertForbidden();
    }

    private function createBook(): Book
    {
        $category = BookCategory::create(['name' => 'Audits', 'is_active' => true]);

        return Book::create([
            'isbn' => '9780000000401',
            'title' => 'Audit Test Book',
            'authors' => 'Stocktake Author',
            'category_id' => $category->id,
            'total_copies' => 2,
            'available_copies' => 2,
        ]);
    }

    private function createLoan(User $admin, Book $book, BookCopy $copy): Loan
    {
        $member = User::factory()->create(['role' => 'member']);
        $copy->update(['status' => 'on_loan']);
        $book->decrement('available_copies');

        return Loan::create([
            'user_id' => $member->id,
            'book_copy_id' => $copy->id,
            'issued_by' => $admin->id,
            'issued_at' => today(),
            'due_at' => today()->addDays(14),
        ]);
    }
}
