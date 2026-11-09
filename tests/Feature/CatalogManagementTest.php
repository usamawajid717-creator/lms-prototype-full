<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\BookCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CatalogManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_can_search_public_catalog_and_filter_availability(): void
    {
        $category = BookCategory::create(['name' => 'Computing', 'is_active' => true]);
        Book::create([
            'isbn' => '9780000000001',
            'title' => 'Learning Laravel',
            'authors' => 'A. Writer',
            'category_id' => $category->id,
            'total_copies' => 3,
            'available_copies' => 2,
        ]);
        Book::create([
            'isbn' => '9780000000002',
            'title' => 'Unavailable Guide',
            'authors' => 'B. Writer',
            'category_id' => $category->id,
            'total_copies' => 1,
            'available_copies' => 0,
        ]);

        $this->get(route('catalog.index', ['search' => 'Laravel', 'availability' => 'available']))
            ->assertOk()
            ->assertSee('catalog-search-form')
            ->assertSee('catalog-results')
            ->assertSee('catalog-suggestions')
            ->assertSee('aria-autocomplete="list"', false)
            ->assertSee('Updating results...')
            ->assertSee('Start typing a title, author, ISBN, or publisher')
            ->assertSee('Learning Laravel')
            ->assertDontSee('Unavailable Guide');
    }

    public function test_catalog_suggestions_return_matching_active_books_and_clear_for_short_queries(): void
    {
        $activeCategory = BookCategory::create(['name' => 'Suggestions', 'is_active' => true]);
        $inactiveCategory = BookCategory::create(['name' => 'Hidden suggestions', 'is_active' => false]);
        foreach ([
            [$activeCategory, 'Laravel in Practice'],
            [$activeCategory, 'Laravel for Beginners'],
            [$inactiveCategory, 'Laravel Archived Title'],
        ] as [$category, $title]) {
            Book::create([
                'isbn' => '978'.str_pad((string) random_int(0, 9999999999), 10, '0', STR_PAD_LEFT),
                'title' => $title,
                'authors' => 'Suggestion Author',
                'category_id' => $category->id,
                'total_copies' => 1,
                'available_copies' => 1,
            ]);
        }

        $this->getJson(route('catalog.suggestions', ['search' => 'Laravel']))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.title', 'Laravel for Beginners')
            ->assertJsonPath('data.0.authors', 'Suggestion Author')
            ->assertJsonPath('data.0.available_copies', 1)
            ->assertJsonMissing(['title' => 'Laravel Archived Title']);

        $this->getJson(route('catalog.suggestions', ['search' => 'L']))
            ->assertOk()
            ->assertExactJson(['data' => []]);
    }

    public function test_inactive_categories_are_hidden_from_public_catalog_and_details(): void
    {
        $category = BookCategory::create(['name' => 'Archived', 'is_active' => false]);
        $book = Book::create([
            'isbn' => '9780000000003',
            'title' => 'Hidden Book',
            'authors' => 'A. Writer',
            'category_id' => $category->id,
            'total_copies' => 1,
            'available_copies' => 1,
        ]);

        $this->get(route('catalog.index'))->assertOk()->assertDontSee('Hidden Book');
        $this->get(route('catalog.show', $book))->assertNotFound();
    }

    public function test_admin_can_create_book_with_available_copies_initialized(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = BookCategory::create(['name' => 'History', 'is_active' => true]);

        $this->actingAs($admin)->post(route('catalog.admin.store'), [
            'isbn' => '9780000000004',
            'title' => 'A Book of History',
            'authors' => 'C. Writer',
            'category_id' => $category->id,
            'total_copies' => 4,
        ])->assertRedirect(route('catalog.admin.index'));

        $this->assertDatabaseHas('books', [
            'isbn' => '9780000000004',
            'total_copies' => 4,
            'available_copies' => 4,
        ]);
    }

    public function test_admin_can_search_open_library_and_get_book_fields_for_the_add_form(): void
    {
        Http::fake([
            'openlibrary.org/search.json*' => Http::response([
                'docs' => [[
                    'title' => 'A Book to Add',
                    'author_name' => ['A. Author', 'B. Author'],
                    'isbn' => ['978-0-306-40615-7', 'not-an-isbn'],
                    'publisher' => ['Example Press'],
                    'first_publish_year' => 2020,
                ]],
            ]),
        ]);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->getJson(route('catalog.admin.book-suggestions', ['search' => 'A Book']))
            ->assertOk()
            ->assertJsonPath('data.0.title', 'A Book to Add')
            ->assertJsonPath('data.0.authors', 'A. Author, B. Author')
            ->assertJsonPath('data.0.isbn', '9780306406157')
            ->assertJsonPath('data.0.publisher', 'Example Press')
            ->assertJsonPath('data.0.publication_year', 2020);

        Http::assertSent(fn ($request): bool => str_contains($request->url(), 'openlibrary.org/search.json')
            && $request['q'] === 'A Book'
            && $request['limit'] === 8
            && str_contains($request->header('User-Agent')[0] ?? '', 'LibraryManagementSystem'));
    }

    public function test_open_library_lookup_is_admin_only_and_handles_provider_errors(): void
    {
        Http::fake(['openlibrary.org/*' => Http::response([], 503)]);

        $this->get(route('catalog.admin.book-suggestions', ['search' => 'Book']))
            ->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create(['role' => 'member']))
            ->get(route('catalog.admin.book-suggestions', ['search' => 'Book']))
            ->assertForbidden();

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->getJson(route('catalog.admin.book-suggestions', ['search' => 'Book']))
            ->assertStatus(503)
            ->assertJsonPath('message', 'Book suggestions are temporarily unavailable. Please try again or enter the details manually.');

        $this->getJson(route('catalog.admin.book-suggestions', ['search' => 'B']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('search');
    }

    public function test_add_book_form_has_open_library_lookup(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('catalog.admin.create'))
            ->assertOk()
            ->assertSee('Find book details')
            ->assertSee('Open Library');
    }

    public function test_admin_can_upload_and_remove_a_book_cover(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin']);
        $category = BookCategory::create(['name' => 'Art', 'is_active' => true]);
        $cover = UploadedFile::fake()->createWithContent(
            'cover.png',
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/l2cAAAAASUVORK5CYII='),
        );

        $this->actingAs($admin)->post(route('catalog.admin.store'), [
            'isbn' => '9780000000010',
            'title' => 'Illustrated Book',
            'authors' => 'F. Writer',
            'category_id' => $category->id,
            'total_copies' => 1,
            'cover_image' => $cover,
        ])->assertRedirect(route('catalog.admin.index'));

        $book = Book::query()->where('isbn', '9780000000010')->firstOrFail();
        Storage::disk('public')->assertExists($book->cover_image_path);
        $oldPath = $book->cover_image_path;

        $this->put(route('catalog.admin.update', $book), [
            'isbn' => $book->isbn,
            'title' => $book->title,
            'authors' => $book->authors,
            'category_id' => $category->id,
            'total_copies' => 1,
            'remove_cover' => '1',
        ])->assertRedirect(route('catalog.admin.index'));

        $this->assertNull($book->fresh()->cover_image_path);
        Storage::disk('public')->assertMissing($oldPath);
    }

    public function test_book_cover_rejects_non_image_files(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin']);
        $category = BookCategory::create(['name' => 'Media', 'is_active' => true]);

        $this->actingAs($admin)->post(route('catalog.admin.store'), [
            'isbn' => '9780000000011',
            'title' => 'Invalid Cover',
            'authors' => 'G. Writer',
            'category_id' => $category->id,
            'total_copies' => 1,
            'cover_image' => UploadedFile::fake()->create('cover.jpg', 10, 'text/plain'),
        ])->assertSessionHasErrors('cover_image');

        $this->assertDatabaseMissing('books', ['isbn' => '9780000000011']);
    }

    public function test_member_cannot_access_catalog_management(): void
    {
        $member = User::factory()->create(['role' => 'member']);

        $this->actingAs($member)->get(route('catalog.admin.index'))->assertForbidden();
    }

    public function test_book_copy_total_cannot_be_reduced_below_unavailable_copies(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = BookCategory::create(['name' => 'Science', 'is_active' => true]);
        $book = Book::create([
            'isbn' => '9780000000005',
            'title' => 'Science Book',
            'authors' => 'D. Writer',
            'category_id' => $category->id,
            'total_copies' => 3,
            'available_copies' => 1,
        ]);

        $this->actingAs($admin)->put(route('catalog.admin.update', $book), [
            'isbn' => $book->isbn,
            'title' => $book->title,
            'authors' => $book->authors,
            'category_id' => $category->id,
            'total_copies' => 1,
        ])->assertSessionHasErrors('total_copies');

        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'total_copies' => 3,
            'available_copies' => 1,
        ]);
    }

    public function test_book_with_retired_copies_can_be_removed_when_it_has_no_loan_history(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = BookCategory::create(['name' => 'Archives', 'is_active' => true]);
        $book = Book::create([
            'isbn' => '9780000000006',
            'title' => 'Retired Copy Book',
            'authors' => 'E. Writer',
            'category_id' => $category->id,
            'total_copies' => 2,
            'available_copies' => 2,
        ]);

        $this->actingAs($admin)->put(route('catalog.admin.update', $book), [
            'isbn' => $book->isbn,
            'title' => $book->title,
            'authors' => $book->authors,
            'category_id' => $category->id,
            'total_copies' => 1,
        ])->assertRedirect(route('catalog.admin.index'));

        $this->delete(route('catalog.admin.destroy', $book))
            ->assertRedirect(route('catalog.admin.index'));

        $this->assertDatabaseMissing('books', ['id' => $book->id]);
    }
}
