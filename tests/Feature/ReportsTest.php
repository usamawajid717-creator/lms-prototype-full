<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\BookCategory;
use App\Models\Loan;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_report_metrics_for_selected_period(): void
    {
        [$book, $member, $loan] = $this->createLoan();
        $loan->update(['returned_at' => now()]);
        $loan->fine()->create([
            'amount_pkr' => 150,
            'paid_at' => now(),
        ]);
        Reservation::create([
            'user_id' => $member->id,
            'book_id' => $book->id,
            'status' => 'waiting',
        ]);
        Reservation::create([
            'user_id' => User::factory()->create(['role' => 'member'])->id,
            'book_id' => $book->id,
            'status' => 'ready',
            'hold_expires_at' => now()->addDay(),
        ]);
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get(route('reports.index', [
            'from' => today()->subDays(30)->toDateString(),
            'to' => today()->toDateString(),
        ]));

        $response
            ->assertOk()
            ->assertSee('Operational Reports')
            ->assertSee('Loans issued in period')
            ->assertSee('Most borrowed books')
            ->assertSee('Most active members')
            ->assertSee($book->title)
            ->assertSee('PKR 0')
            ->assertSee('PKR 150');
        $response->assertViewHas('metrics', fn (array $metrics) => $metrics['loans_issued'] === 1
            && $metrics['loans_returned'] === 1
            && $metrics['paid_fines_pkr'] === 150);
    }

    public function test_report_date_range_is_validated(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('reports.index', [
            'from' => today()->toDateString(),
            'to' => today()->subDay()->toDateString(),
        ]))->assertSessionHasErrors('from');

        $this->get(route('reports.index', [
            'from' => today()->addDay()->toDateString(),
            'to' => today()->addDays(2)->toDateString(),
        ]))->assertSessionHasErrors('from');
    }

    public function test_loan_csv_export_uses_selected_dates_and_escapes_spreadsheet_formulas(): void
    {
        [$book, , $loan] = $this->createLoan(['name' => '=HYPERLINK("https://example.test")']);
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get(route('reports.loans.export', [
            'from' => today()->subDays(30)->toDateString(),
            'to' => today()->toDateString(),
        ]));

        $response->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertHeader('content-disposition', 'attachment; filename=loan-report-'.today()->subDays(30)->toDateString().'-'.today()->toDateString().'.csv');

        $content = $response->streamedContent();
        $this->assertStringContainsString('"Loan ID",Member,"Library Card",Book,Barcode', $content);
        $this->assertStringContainsString("'=HYPERLINK", $content);
        $this->assertStringContainsString($book->title, $content);
        $this->assertStringContainsString((string) $loan->id, $content);
    }

    public function test_reports_and_exports_are_admin_only(): void
    {
        $member = User::factory()->create(['role' => 'member']);

        $this->get(route('reports.index'))->assertRedirect(route('login'));
        $this->get(route('reports.loans.export'))->assertRedirect(route('login'));

        $this->actingAs($member)->get(route('reports.index'))->assertForbidden();
        $this->actingAs($member)->get(route('reports.loans.export'))->assertForbidden();
    }

    private function createLoan(array $memberAttributes = []): array
    {
        $category = BookCategory::create(['name' => 'Reports '.uniqid(), 'is_active' => true]);
        $book = Book::create([
            'isbn' => '978'.str_pad((string) random_int(0, 9999999999), 10, '0', STR_PAD_LEFT),
            'title' => 'Report Test Book',
            'authors' => 'Report Author',
            'category_id' => $category->id,
            'total_copies' => 1,
            'available_copies' => 0,
        ]);
        $copy = $book->copies()->firstOrFail();
        $member = User::factory()->create(array_merge([
            'role' => 'member',
            'library_card_number' => 'LMS-'.uniqid(),
        ], $memberAttributes));
        $admin = User::factory()->create(['role' => 'admin']);
        $loan = Loan::create([
            'user_id' => $member->id,
            'book_copy_id' => $copy->id,
            'issued_by' => $admin->id,
            'issued_at' => today(),
            'due_at' => today()->addDays(14),
        ]);

        return [$book, $member, $loan];
    }
}
