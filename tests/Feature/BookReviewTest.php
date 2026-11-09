<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\BookCategory;
use App\Models\BookReview;
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

    private function createBook(): Book
    {
        $category = BookCategory::create(['name' => 'Reviews', 'is_active' => true]);

        return Book::create([
            'isbn' => '9780000000201',
            'title' => 'Book for Reviews',
            'authors' => 'Review Author',
            'category_id' => $category->id,
            'total_copies' => 1,
            'available_copies' => 1,
        ]);
    }
}
