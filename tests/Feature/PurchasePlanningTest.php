<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\BookCategory;
use App\Models\BookRequest;
use App\Models\CourseReadingList;
use App\Models\Loan;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchasePlanningTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_purchase_planning_ranks_demand_and_shows_unfulfilled_requests(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $member = User::factory()->create(['role' => 'member']);
        $faculty = User::factory()->create(['role' => 'member', 'membership_type' => 'Faculty']);
        $highDemand = $this->createBook('9780000000601', 'High Demand Title', 0);
        $lowDemand = $this->createBook('9780000000602', 'Low Demand Title', 1);
        $publishedList = $this->createReadingList($faculty, 'CS101', true);
        $publishedList->items()->create(['book_id' => $highDemand->id]);
        $unpublishedList = $this->createReadingList($faculty, 'CS102', false);
        $unpublishedList->items()->create(['book_id' => $lowDemand->id]);
        BookRequest::create([
            'user_id' => $member->id,
            'title' => 'High Demand Title',
            'isbn' => $highDemand->isbn,
            'status' => 'pending',
        ]);
        Reservation::create([
            'user_id' => $member->id,
            'book_id' => $highDemand->id,
            'status' => 'waiting',
        ]);
        $copy = $lowDemand->copies()->firstOrFail();
        Loan::create([
            'user_id' => $member->id,
            'book_copy_id' => $copy->id,
            'issued_by' => $admin->id,
            'issued_at' => today()->subDays(30),
            'due_at' => today()->subDays(16),
            'returned_at' => now(),
            'returned_by' => $admin->id,
        ]);
        BookRequest::create([
            'user_id' => $member->id,
            'title' => 'Uncatalogued Request',
            'status' => 'ordered',
            'supplier' => 'Campus Books Ltd.',
            'ordered_at' => today()->subDay(),
        ]);

        $this->actingAs($admin)->get(route('reports.purchase-planning'))
            ->assertOk()
            ->assertSee('Purchase Planning')
            ->assertSee('Uncatalogued Request')
            ->assertSee('Campus Books Ltd.')
            ->assertSee('High Demand Title')
            ->assertSee('Low Demand Title')
            ->assertSee('Required by courses · unavailable')
            ->assertSee('Course lists')
            ->assertSeeInOrder(['High Demand Title', 'Low Demand Title'])
            ->assertViewHas('books', function ($books) use ($highDemand, $lowDemand): bool {
                return $books->first()->id === $highDemand->id
                    && $books->first()->active_requests_count === 1
                    && $books->first()->waiting_reservations_count === 1
                    && $books->first()->published_course_lists_count === 1
                    && $books->first()->is_unavailable_course_title
                    && $books->first()->demand_score === 9
                    && $books->last()->id === $lowDemand->id
                    && $books->last()->recent_loans_count === 1
                    && $books->last()->published_course_lists_count === 0
                    && ! $books->last()->is_unavailable_course_title;
            });
    }

    public function test_purchase_planning_is_admin_only(): void
    {
        $member = User::factory()->create(['role' => 'member']);

        $this->get(route('reports.purchase-planning'))->assertRedirect(route('login'));
        $this->actingAs($member)->get(route('reports.purchase-planning'))->assertForbidden();
    }

    private function createBook(string $isbn, string $title, int $available): Book
    {
        $category = BookCategory::create(['name' => 'Purchase Planning '.uniqid(), 'is_active' => true]);

        return Book::create([
            'isbn' => $isbn,
            'title' => $title,
            'authors' => 'Planning Author',
            'category_id' => $category->id,
            'total_copies' => 1,
            'available_copies' => $available,
        ]);
    }

    private function createReadingList(User $faculty, string $code, bool $published): CourseReadingList
    {
        return CourseReadingList::create([
            'user_id' => $faculty->id,
            'course_code' => $code,
            'course_name' => 'Course '.$code,
            'semester' => 'Fall',
            'academic_year' => 2026,
            'is_published' => $published,
        ]);
    }
}
