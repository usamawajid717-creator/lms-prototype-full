<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\BookCategory;
use App\Models\Fine;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FineReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_filter_fine_report_and_view_totals_and_trends(): void
    {
        [$paidFine, $member] = $this->createFine(['name' => '=Formula Member'], [
            'amount_pkr' => 100,
            'paid_at' => now(),
        ]);
        [$unpaidFine, $unpaidMember] = $this->createFine(['email' => 'unpaid@example.test'], ['amount_pkr' => 200]);
        [$waivedFine] = $this->createFine(['email' => 'waived@example.test'], ['amount_pkr' => 0, 'waived_at' => now(), 'waived_reason' => 'Approved waiver.']);
        $waivedFine->adjustments()->create([
            'admin_id' => User::factory()->create(['role' => 'admin'])->id,
            'action' => 'waive',
            'previous_amount_pkr' => 80,
            'new_amount_pkr' => 0,
            'reason' => 'Approved waiver.',
        ]);
        $admin = User::factory()->create(['role' => 'admin']);
        $from = today()->subDays(7)->toDateString();
        $to = today()->toDateString();

        $response = $this->actingAs($admin)->get(route('reports.fines', [
            'from' => $from,
            'to' => $to,
        ]));

        $response->assertOk()
            ->assertSee('Fine Report')
            ->assertSee('Formula Member')
            ->assertSee('Unpaid')
            ->assertSee('Waived')
            ->assertSee('Top outstanding balances')
            ->assertSee($unpaidMember->name)
            ->assertViewHas('totals', fn (array $totals): bool => $totals['count'] === 3
                && $totals['assessed_pkr'] === 300
                && $totals['paid_pkr'] === 100
                && $totals['outstanding_pkr'] === 200
                && $totals['waived_pkr'] === 80);

        $this->get(route('reports.fines', ['from' => $from, 'to' => $to, 'status' => 'paid']))
            ->assertOk()
            ->assertSee('Formula Member')
            ->assertViewHas('totals', fn (array $totals): bool => $totals['count'] === 1 && $totals['paid_pkr'] === 100)
            ->assertViewHas('fines', fn ($fines): bool => $fines->total() === 1 && $fines->first()->id === $paidFine->id);

        $this->get(route('reports.fines', ['from' => $from, 'to' => $to, 'member' => $member->id]))
            ->assertOk()
            ->assertSee('Formula Member')
            ->assertViewHas('fines', fn ($fines): bool => $fines->total() === 1 && $fines->first()->id === $paidFine->id);
    }

    public function test_fine_report_validates_filters_and_is_admin_only(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $member = User::factory()->create(['role' => 'member']);

        $this->get(route('reports.fines'))->assertRedirect(route('login'));
        $this->actingAs($member)->get(route('reports.fines'))->assertForbidden();
        $this->actingAs($member)->get(route('reports.fines.export'))->assertForbidden();
        $this->actingAs($admin)->get(route('reports.fines', ['status' => 'invalid']))->assertSessionHasErrors('status');
        $this->get(route('reports.fines', ['member' => 999999]))->assertSessionHasErrors('member');
    }

    public function test_fine_csv_export_applies_status_member_and_date_filters_and_escapes_text(): void
    {
        [$paidFine] = $this->createFine(['name' => '=HYPERLINK("https://example.test")'], [
            'amount_pkr' => 125,
            'paid_at' => now(),
        ]);
        [, $otherMember] = $this->createFine(['email' => 'other@example.test'], ['amount_pkr' => 300]);
        $admin = User::factory()->create(['role' => 'admin']);
        $from = today()->subDays(7)->toDateString();
        $to = today()->toDateString();

        $response = $this->actingAs($admin)->get(route('reports.fines.export', [
            'from' => $from,
            'to' => $to,
            'status' => 'paid',
        ]));

        $response->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertHeader('content-disposition', "attachment; filename=fine-report-{$from}-{$to}.csv");
        $content = $response->streamedContent();
        $this->assertStringContainsString('"Fine ID",Member,"Library Card",Book,"Amount (PKR)",Status', $content);
        $this->assertStringContainsString("'=HYPERLINK", $content);
        $this->assertStringContainsString((string) $paidFine->id, $content);
        $this->assertStringNotContainsString($otherMember->name, $content);
    }

    private function createFine(array $memberAttributes, array $fineAttributes): array
    {
        $category = BookCategory::create(['name' => 'Fine report '.uniqid(), 'is_active' => true]);
        $book = Book::create([
            'isbn' => '978'.str_pad((string) random_int(0, 9999999999), 10, '0', STR_PAD_LEFT),
            'title' => 'Fine Report Book',
            'authors' => 'Report Author',
            'category_id' => $category->id,
            'total_copies' => 1,
            'available_copies' => 0,
        ]);
        $member = User::factory()->create(array_merge([
            'role' => 'member',
            'library_card_number' => 'LMS-'.uniqid(),
        ], $memberAttributes));
        $admin = User::factory()->create(['role' => 'admin']);
        $loan = Loan::create([
            'user_id' => $member->id,
            'book_copy_id' => $book->copies()->firstOrFail()->id,
            'issued_by' => $admin->id,
            'issued_at' => today()->subDays(10),
            'due_at' => today()->subDay(),
            'returned_at' => now(),
            'returned_by' => $admin->id,
        ]);

        return [$loan->fine()->create(array_merge(['amount_pkr' => 100], $fineAttributes)), $member];
    }
}
