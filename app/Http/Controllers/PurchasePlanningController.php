<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\BookRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PurchasePlanningController extends Controller
{
    public function index(): View
    {
        $books = Book::query()
            ->with('category')
            ->withCount([
                'reservations as waiting_reservations_count' => fn ($query) => $query->where('status', 'waiting'),
            ])
            ->addSelect([
                'active_requests_count' => BookRequest::query()
                    ->selectRaw('COUNT(*)')
                    ->whereIn('status', ['pending', 'approved', 'ordered'])
                    ->where(function ($query): void {
                        $query->whereRaw('LOWER(book_requests.title) = LOWER(books.title)')
                            ->orWhere(function ($isbnQuery): void {
                                $isbnQuery->whereNotNull('books.isbn')
                                    ->whereColumn('book_requests.isbn', 'books.isbn');
                            });
                    }),
                'recent_loans_count' => DB::table('loans')
                    ->join('book_copies', 'loans.book_copy_id', '=', 'book_copies.id')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('book_copies.book_id', 'books.id')
                    ->whereDate('loans.issued_at', '>=', today()->subDays(180)),
                'published_course_lists_count' => DB::table('course_reading_list_items')
                    ->join('course_reading_lists', 'course_reading_lists.id', '=', 'course_reading_list_items.course_reading_list_id')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('course_reading_list_items.book_id', 'books.id')
                    ->where('course_reading_lists.is_published', true),
            ])
            ->get()
            ->map(function (Book $book): Book {
                $book->demand_score = ($book->active_requests_count * 3)
                    + ($book->waiting_reservations_count * 2)
                    + $book->recent_loans_count
                    + ($book->published_course_lists_count * 4);
                $book->is_unavailable_course_title = $book->published_course_lists_count > 0
                    && $book->available_copies === 0;

                return $book;
            })
            ->sortByDesc('demand_score')
            ->values();

        return view('reports.purchase-planning', [
            'books' => $books,
            'activeRequests' => BookRequest::query()
                ->with('member:id,name')
                ->whereIn('status', ['pending', 'approved', 'ordered', 'received'])
                ->latest()
                ->get(),
            'periodDays' => 180,
        ]);
    }
}
