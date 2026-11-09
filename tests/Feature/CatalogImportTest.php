<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\BookCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class CatalogImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_import_books_and_create_their_copy_records_from_csv(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = BookCategory::create(['name' => 'Computing', 'is_active' => true]);
        $csv = "isbn,title,authors,publisher,publication_year,edition,category,total_copies\n"
            ."9780000000301,Imported Book,Import Author,Example Press,2022,2nd,Computing,3\n"
            ."9780000000302,Second Imported Book,Another Author,,,,Computing,1\n";

        $this->actingAs($admin)->post(route('catalog.admin.import'), [
            'file' => UploadedFile::fake()->createWithContent('books.csv', $csv),
        ])->assertRedirect(route('catalog.admin.index'))
            ->assertSessionHas('status', '2 books imported successfully.');

        $book = Book::query()->where('isbn', '9780000000301')->firstOrFail();
        $this->assertSame(3, $book->copies()->count());
        $this->assertDatabaseHas('books', [
            'isbn' => '9780000000302',
            'category_id' => $category->id,
            'total_copies' => 1,
            'available_copies' => 1,
        ]);
    }

    public function test_invalid_csv_rows_reject_the_entire_import_without_partial_writes(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        BookCategory::create(['name' => 'Computing', 'is_active' => true]);
        $csv = "isbn,title,authors,category,total_copies\n"
            ."9780000000303,Valid Row,An Author,Computing,2\n"
            ."9780000000304,Invalid Category,Another Author,Missing Category,1\n";

        $this->actingAs($admin)->post(route('catalog.admin.import'), [
            'file' => UploadedFile::fake()->createWithContent('books.csv', $csv),
        ])->assertSessionHasErrors('file');

        $this->assertDatabaseCount('books', 0);
        $this->assertDatabaseCount('book_copies', 0);
    }

    public function test_import_rejects_duplicate_isbns_and_protects_admin_routes(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $member = User::factory()->create(['role' => 'member']);
        $csv = "isbn,title,authors,category,total_copies\n"
            ."9780000000305,First,An Author,Computing,1\n"
            ."9780000000305,Duplicate,Another Author,Computing,1\n";

        $this->actingAs($member)->get(route('catalog.admin.import-form'))->assertForbidden();
        $this->actingAs($member)->post(route('catalog.admin.import'), [
            'file' => UploadedFile::fake()->createWithContent('books.csv', $csv),
        ])->assertForbidden();

        BookCategory::create(['name' => 'Computing', 'is_active' => true]);
        $this->actingAs($admin)->post(route('catalog.admin.import'), [
            'file' => UploadedFile::fake()->createWithContent('books.csv', $csv),
        ])->assertSessionHasErrors('file');
        $this->assertDatabaseCount('books', 0);
    }

    public function test_import_template_has_expected_csv_headers(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('catalog.admin.import-template'))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertSeeText('isbn,title,authors,publisher,publication_year,edition,category,total_copies');
    }

    public function test_admin_can_view_the_import_form(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('catalog.admin.import-form'))
            ->assertOk()
            ->assertSee('Bulk Catalog Import')
            ->assertSee(route('catalog.admin.import-template'));
    }
}
