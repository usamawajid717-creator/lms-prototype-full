<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\BookCategory;
use App\Models\BookRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use App\Notifications\LibraryNotification;
use Tests\TestCase;

class BookRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_submit_a_request_and_admin_can_approve_and_fulfill_it(): void
    {
        $member = User::factory()->create(['role' => 'member']);
        $admin = User::factory()->create(['role' => 'admin']);
        $book = $this->createBook();

        $this->actingAs($member)->post(route('book-requests.store'), [
            'title' => 'Requested Book',
            'authors' => 'A. Writer',
            'isbn' => '9781111111111',
            'notes' => 'Frequently requested by members.',
        ])->assertRedirect(route('book-requests.mine'));

        $request = BookRequest::query()->firstOrFail();
        $this->actingAs($admin)->patch(route('book-requests.update', $request), [
            'status' => 'approved',
        ])->assertRedirect(route('book-requests.admin'));

        $this->assertSame('approved', $request->fresh()->status);
        $this->patch(route('book-requests.update', $request), [
            'status' => 'ordered',
            'supplier' => 'Campus Books Ltd.',
            'ordered_at' => today()->toDateString(),
        ])->assertRedirect(route('book-requests.admin'));
        $this->assertDatabaseHas('book_requests', [
            'id' => $request->id,
            'status' => 'ordered',
            'supplier' => 'Campus Books Ltd.',
        ]);
        $this->assertSame(today()->toDateString(), $request->fresh()->ordered_at->toDateString());
        $this->patch(route('book-requests.update', $request), [
            'status' => 'received',
        ])->assertRedirect(route('book-requests.admin'));
        $this->assertNotNull($request->fresh()->received_at);
        $this->patch(route('book-requests.update', $request), [
            'status' => 'fulfilled',
            'book_id' => $book->id,
        ])->assertRedirect(route('book-requests.admin'));

        $this->assertDatabaseHas('book_requests', [
            'id' => $request->id,
            'status' => 'fulfilled',
            'book_id' => $book->id,
            'admin_id' => $admin->id,
        ]);
        $this->actingAs($member)->get(route('book-requests.mine'))
            ->assertOk()
            ->assertSee('Fulfilled')
            ->assertSee($book->title);
    }

    public function test_duplicate_active_title_or_isbn_is_rejected(): void
    {
        $member = User::factory()->create(['role' => 'member']);
        $otherMember = User::factory()->create(['role' => 'member']);
        $this->actingAs($member)->post(route('book-requests.store'), [
            'title' => 'The Requested Book',
            'isbn' => '9781111111112',
        ])->assertRedirect(route('book-requests.mine'));

        $this->actingAs($otherMember)->post(route('book-requests.store'), [
            'title' => 'the requested book',
        ])->assertSessionHasErrors('title');
        $this->post(route('book-requests.store'), [
            'title' => 'Different Title',
            'isbn' => '9781111111112',
        ])->assertSessionHasErrors('title');

        $this->assertDatabaseCount('book_requests', 1);
    }

    public function test_member_cannot_request_a_book_already_in_the_active_catalog(): void
    {
        $member = User::factory()->create(['role' => 'member']);
        $book = $this->createBook();

        $this->actingAs($member)->post(route('book-requests.store'), [
            'title' => 'Different Title',
            'isbn' => $book->isbn,
        ])->assertSessionHasErrors('title');

        $this->assertDatabaseCount('book_requests', 0);
    }

    public function test_admin_review_is_role_protected_and_decline_requires_a_reason(): void
    {
        $member = User::factory()->create(['role' => 'member']);
        $admin = User::factory()->create(['role' => 'admin']);
        $request = BookRequest::create(['user_id' => $member->id, 'title' => 'A Suggested Book']);

        $this->actingAs($member)->get(route('book-requests.admin'))->assertForbidden();
        $this->actingAs($admin)->post(route('book-requests.store'), ['title' => 'Admin Request'])->assertForbidden();
        $this->patch(route('book-requests.update', $request), [
            'status' => 'declined',
        ])->assertSessionHasErrors('reason');

        $this->patch(route('book-requests.update', $request), [
            'status' => 'declined',
            'reason' => 'Not aligned with the current collection.',
        ])->assertRedirect(route('book-requests.admin'));
        $this->assertSame('declined', $request->fresh()->status);
        $this->assertSame('Not aligned with the current collection.', $request->fresh()->decision_reason);
    }

    public function test_request_cannot_be_fulfilled_before_approval(): void
    {
        $member = User::factory()->create(['role' => 'member']);
        $admin = User::factory()->create(['role' => 'admin']);
        $book = $this->createBook();
        $request = BookRequest::create(['user_id' => $member->id, 'title' => 'Pending Request']);

        $this->actingAs($admin)->patch(route('book-requests.update', $request), [
            'status' => 'fulfilled',
            'book_id' => $book->id,
        ])->assertSessionHasErrors('status');

        $this->assertSame('pending', $request->fresh()->status);
    }

    public function test_ordering_requires_supplier_and_date_and_stages_cannot_be_skipped(): void
    {
        $member = User::factory()->create(['role' => 'member']);
        $admin = User::factory()->create(['role' => 'admin']);
        $request = BookRequest::create([
            'user_id' => $member->id,
            'title' => 'Order Workflow Book',
            'status' => 'approved',
        ]);

        $this->actingAs($admin)->patch(route('book-requests.update', $request), [
            'status' => 'ordered',
        ])->assertSessionHasErrors(['supplier', 'ordered_at']);
        $this->assertSame('approved', $request->fresh()->status);

        $this->patch(route('book-requests.update', $request), [
            'status' => 'received',
        ])->assertSessionHasErrors('status');
        $this->assertSame('approved', $request->fresh()->status);
    }

    public function test_admin_can_view_requests_and_member_can_view_request_form(): void
    {
        $member = User::factory()->create(['role' => 'member']);
        $admin = User::factory()->create(['role' => 'admin']);
        BookRequest::create(['user_id' => $member->id, 'title' => 'Requested Display Title']);

        $this->actingAs($admin)->get(route('book-requests.admin'))
            ->assertOk()
            ->assertSee('Requested Display Title');
        $this->actingAs($member)->get(route('book-requests.mine'))
            ->assertOk()
            ->assertSee('Request a Book')
            ->assertSee('Requested Display Title');
    }

    public function test_member_can_search_open_library_for_a_request_and_guests_cannot_use_the_endpoint(): void
    {
        Http::fake([
            'openlibrary.org/search.json*' => Http::response([
                'docs' => [[
                    'title' => 'Suggested Request Title',
                    'author_name' => ['Suggestion Author'],
                    'isbn' => ['978-0-306-40615-7'],
                    'first_publish_year' => 2020,
                ]],
            ]),
        ]);
        $member = User::factory()->create(['role' => 'member']);

        $this->get(route('book-requests.book-suggestions', ['search' => 'Suggested Request']))
            ->assertRedirect(route('login'));
        $this->actingAs($member)->getJson(route('book-requests.book-suggestions', ['search' => 'Suggested Request']))
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Suggested Request Title')
            ->assertJsonPath('data.0.authors', 'Suggestion Author')
            ->assertJsonPath('data.0.isbn', '9780306406157');

        $this->actingAs($member)->getJson(route('book-requests.book-suggestions', ['search' => 'S']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('search');
    }

    public function test_member_is_notified_by_email_and_in_app_when_request_is_approved_declined_or_fulfilled(): void
    {
        Notification::fake();
        $member = User::factory()->create(['role' => 'member']);
        $admin = User::factory()->create(['role' => 'admin']);
        $book = $this->createBook();
        $approvedRequest = BookRequest::create(['user_id' => $member->id, 'title' => 'Approved Request']);
        $declinedRequest = BookRequest::create(['user_id' => $member->id, 'title' => 'Declined Request']);

        $this->actingAs($admin)->patch(route('book-requests.update', $approvedRequest), [
            'status' => 'approved',
        ])->assertRedirect(route('book-requests.admin'));

        Notification::assertSentTo($member, LibraryNotification::class, function (LibraryNotification $notification, array $channels): bool {
            $this->assertSame(['database', 'mail'], $channels);
            $this->assertSame('Book request approved', $notification->toArray(new \stdClass)['title']);
            $this->assertStringContainsString('Approved Request', $notification->toArray(new \stdClass)['message']);
            $this->assertSame(route('book-requests.mine'), $notification->toArray(new \stdClass)['url']);

            return true;
        });

        $this->patch(route('book-requests.update', $approvedRequest), [
            'status' => 'ordered',
            'supplier' => 'Campus Books Ltd.',
            'ordered_at' => today()->toDateString(),
        ])->assertRedirect(route('book-requests.admin'));
        $this->patch(route('book-requests.update', $approvedRequest), [
            'status' => 'received',
        ])->assertRedirect(route('book-requests.admin'));
        Notification::assertSentTo($member, LibraryNotification::class, function (LibraryNotification $notification): bool {
            return $notification->toArray(new \stdClass)['title'] === 'Requested book received'
                && str_contains($notification->toArray(new \stdClass)['message'], 'Approved Request');
        });

        $this->patch(route('book-requests.update', $approvedRequest), [
            'status' => 'fulfilled',
            'book_id' => $book->id,
        ])->assertRedirect(route('book-requests.admin'));
        Notification::assertSentTo($member, LibraryNotification::class, function (LibraryNotification $notification): bool {
            return $notification->toArray(new \stdClass)['title'] === 'Book request fulfilled'
                && str_contains($notification->toArray(new \stdClass)['message'], 'Acquired Catalog Book');
        });

        $this->patch(route('book-requests.update', $declinedRequest), [
            'status' => 'declined',
            'reason' => 'Outside current collection scope.',
        ])->assertRedirect(route('book-requests.admin'));
        Notification::assertSentTo($member, LibraryNotification::class, function (LibraryNotification $notification): bool {
            return $notification->toArray(new \stdClass)['title'] === 'Book request declined'
                && str_contains($notification->toArray(new \stdClass)['message'], 'Outside current collection scope.');
        });

        Notification::assertSentToTimes($member, LibraryNotification::class, 5);
    }

    private function createBook(): Book
    {
        $category = BookCategory::create(['name' => 'Requests', 'is_active' => true]);

        return Book::create([
            'isbn' => '9780000000101',
            'title' => 'Acquired Catalog Book',
            'authors' => 'Library Author',
            'category_id' => $category->id,
            'total_copies' => 1,
            'available_copies' => 1,
        ]);
    }
}
