<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\BookCategory;
use App\Models\BookReview;
use App\Models\BookReviewReport;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_create_update_and_remove_one_review_per_book(): void
    {
        $member = User::factory()->create(['role' => 'member']);
        $book = $this->createBook();

        $this->actingAs($member)->put(route('book-reviews.store', $book), [
            'rating' => 4,
            'comment' => 'A thoughtful book.',
        ])->assertRedirect(route('catalog.show', $book));

        $review = BookReview::query()->firstOrFail();
        $this->put(route('book-reviews.store', $book), [
            'rating' => 5,
            'comment' => 'I changed my mind after rereading it.',
        ])->assertRedirect(route('catalog.show', $book));

        $this->assertDatabaseCount('book_reviews', 1);
        $this->assertDatabaseHas('book_reviews', [
            'id' => $review->id,
            'user_id' => $member->id,
            'rating' => 5,
        ]);
        $this->get(route('catalog.show', $book))
            ->assertOk()
            ->assertSee('Member Reviews')
            ->assertSee('5.0 / 5')
            ->assertSee('I changed my mind after rereading it.');

        $this->delete(route('book-reviews.destroy', $book))
            ->assertRedirect(route('catalog.show', $book));
        $this->assertDatabaseMissing('book_reviews', ['id' => $review->id]);
    }

    public function test_reviews_are_member_only_and_ratings_are_limited_to_five_stars(): void
    {
        $book = $this->createBook();
        $admin = User::factory()->create(['role' => 'admin']);
        $member = User::factory()->create(['role' => 'member']);

        $this->put(route('book-reviews.store', $book), ['rating' => 5])->assertRedirect(route('login'));
        $this->actingAs($admin)->put(route('book-reviews.store', $book), ['rating' => 5])->assertForbidden();

        $this->actingAs($member)->put(route('book-reviews.store', $book), [
            'rating' => 6,
            'comment' => 'Invalid rating.',
        ])->assertSessionHasErrors('rating');
        $this->assertDatabaseCount('book_reviews', 0);
    }

    public function test_member_can_report_another_members_review_once_and_admin_can_hide_it(): void
    {
        $author = User::factory()->create(['role' => 'member']);
        $reporter = User::factory()->create(['role' => 'member']);
        $admin = User::factory()->create(['role' => 'admin']);
        $book = $this->createBook();
        $review = BookReview::create([
            'book_id' => $book->id,
            'user_id' => $author->id,
            'rating' => 1,
            'comment' => 'Reported review text.',
        ]);

        $this->actingAs($reporter)->post(route('book-reviews.report', $review), [
            'reason' => 'This review contains inappropriate language.',
        ])->assertRedirect(route('catalog.show', $book));
        $report = BookReviewReport::query()->firstOrFail();

        $this->post(route('book-reviews.report', $review), [
            'reason' => 'Repeated report.',
        ])->assertSessionHasErrors('report');
        $this->assertDatabaseCount('book_review_reports', 1);

        $this->actingAs($admin)->get(route('book-reviews.reports'))
            ->assertOk()
            ->assertSee('Reported review text.')
            ->assertSee('This review contains inappropriate language.');

        $this->patch(route('book-reviews.reports.resolve', $report), [
            'action' => 'hide',
        ])->assertRedirect(route('book-reviews.reports'));

        $this->assertNotNull($review->fresh()->hidden_at);
        $this->assertNotNull($report->fresh()->resolved_at);
        $this->get(route('catalog.show', $book))
            ->assertOk()
            ->assertDontSee('Reported review text.')
            ->assertSee('0 reviews');

        $this->patch(route('book-reviews.moderate', $review->fresh()), [
            'action' => 'restore',
        ])->assertRedirect(route('book-reviews.reports'));
        $this->assertNull($review->fresh()->hidden_at);
        $this->get(route('catalog.show', $book))->assertSee('Reported review text.');
    }

    public function test_members_cannot_report_their_own_review_and_admin_routes_are_protected(): void
    {
        $author = User::factory()->create(['role' => 'member']);
        $book = $this->createBook();
        $review = BookReview::create([
            'book_id' => $book->id,
            'user_id' => $author->id,
            'rating' => 4,
        ]);

        $this->actingAs($author)->post(route('book-reviews.report', $review), [
            'reason' => 'I want to report my own review.',
        ])->assertForbidden();
        $this->get(route('book-reviews.reports'))->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('book-reviews.reports'))
            ->assertOk();
        $this->assertDatabaseCount('book_review_reports', 0);
    }

    public function test_verified_borrower_badge_requires_a_loan_for_that_book(): void
    {
        $member = User::factory()->create(['role' => 'member']);
        $issuer = User::factory()->create(['role' => 'admin']);
        $book = $this->createBook();
        $otherBook = $this->createBook('9780000000202', 'Different Book');
        $review = BookReview::create([
            'book_id' => $book->id,
            'user_id' => $member->id,
            'rating' => 5,
            'comment' => 'Borrowed and reviewed.',
        ]);
        $otherReview = BookReview::create([
            'book_id' => $otherBook->id,
            'user_id' => $member->id,
            'rating' => 5,
            'comment' => 'Not borrowed.',
        ]);
        $copy = $book->copies()->firstOrFail();
        Loan::create([
            'user_id' => $member->id,
            'book_copy_id' => $copy->id,
            'issued_by' => $issuer->id,
            'issued_at' => today(),
            'due_at' => today()->addDays(14),
        ]);

        $this->assertTrue($review->hasVerifiedLoan());
        $this->assertFalse($otherReview->hasVerifiedLoan());
        $this->get(route('catalog.show', $book))->assertSee('Verified borrower');
        $this->get(route('catalog.show', $otherBook))->assertDontSee('Verified borrower');
    }

    private function createBook(string $isbn = '9780000000201', string $title = 'Book for Reviews'): Book
    {
        $category = BookCategory::firstOrCreate(['name' => 'Reviews'], ['is_active' => true]);

        return Book::create([
            'isbn' => $isbn,
            'title' => $title,
            'authors' => 'Review Author',
            'category_id' => $category->id,
            'total_copies' => 1,
            'available_copies' => 1,
        ]);
    }
}
