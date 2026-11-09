<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\BookRequest;
use App\Notifications\LibraryNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BookRequestController extends Controller
{
    public function mine(Request $request): View
    {
        return view('book-requests.mine', [
            'requests' => $request->user()->bookRequests()->with(['admin', 'book'])->latest()->paginate(15),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_if($request->user()->isAdmin(), 403, 'Only library members can request books.');

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'authors' => ['nullable', 'string', 'max:255'],
            'isbn' => ['nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $title = trim($validated['title']);
        $isbn = filled($validated['isbn'] ?? null) ? trim($validated['isbn']) : null;
        $matchesBook = fn ($query) => $isbn
            ? $query->whereRaw('LOWER(isbn) = ?', [mb_strtolower($isbn)])
            : $query->whereRaw('LOWER(title) = ?', [mb_strtolower($title)]);
        $alreadyCatalogued = Book::query()
            ->whereHas('category', fn ($query) => $query->where('is_active', true))
            ->where($matchesBook)
            ->exists();
        $duplicate = BookRequest::query()
            ->whereIn('status', ['pending', 'approved'])
            ->where($matchesBook)
            ->exists();

        if ($alreadyCatalogued) {
            throw ValidationException::withMessages([
                'title' => 'This book is already available in the active catalog.',
            ]);
        }
        if ($duplicate) {
            throw ValidationException::withMessages([
                'title' => 'An active request for this book already exists.',
            ]);
        }

        $request->user()->bookRequests()->create([...$validated, 'title' => $title, 'isbn' => $isbn]);

        return redirect()->route('book-requests.mine')->with('status', 'Your book request was submitted.');
    }

    public function adminIndex(): View
    {
        return view('book-requests.admin-index', [
            'requests' => BookRequest::query()->with(['member', 'admin', 'book'])->latest()->paginate(20),
            'books' => Book::query()->orderBy('title')->get(['id', 'title', 'isbn']),
        ]);
    }

    public function update(Request $request, BookRequest $bookRequest): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['approved', 'declined', 'fulfilled'])],
            'reason' => ['nullable', 'string', 'min:5', 'max:2000'],
            'book_id' => ['required_if:status,fulfilled', 'nullable', 'integer', Rule::exists('books', 'id')],
        ]);

        if ($validated['status'] === 'declined' && blank($validated['reason'] ?? null)) {
            throw ValidationException::withMessages(['reason' => 'A reason is required when declining a request.']);
        }

        DB::transaction(function () use ($bookRequest, $request, $validated): void {
            $lockedRequest = BookRequest::query()->lockForUpdate()->findOrFail($bookRequest->id);
            $allowed = match ($validated['status']) {
                'approved', 'declined' => $lockedRequest->status === 'pending',
                'fulfilled' => $lockedRequest->status === 'approved',
            };

            if (! $allowed) {
                throw ValidationException::withMessages([
                    'status' => 'This request can no longer be changed to the selected status.',
                ]);
            }

            $lockedRequest->update([
                'status' => $validated['status'],
                'admin_id' => $request->user()->id,
                'book_id' => $validated['status'] === 'fulfilled' ? $validated['book_id'] : null,
                'decision_reason' => $validated['status'] === 'declined' ? trim($validated['reason']) : null,
                'reviewed_at' => now(),
            ]);
        });

        $updatedRequest = $bookRequest->fresh(['book', 'member']);
        $title = match ($updatedRequest->status) {
            'approved' => 'Book request approved',
            'declined' => 'Book request declined',
            'fulfilled' => 'Book request fulfilled',
        };
        $message = match ($updatedRequest->status) {
            'approved' => 'Your request for "'.$updatedRequest->title.'" was approved. We will update you when the book is acquired.',
            'declined' => 'Your request for "'.$updatedRequest->title.'" was declined. Reason: '.$updatedRequest->decision_reason,
            'fulfilled' => 'The requested book "'.$updatedRequest->book->title.'" has been added to the catalog.',
        };
        $updatedRequest->member->notify(new LibraryNotification(
            $title,
            $message,
            route('book-requests.mine'),
        ));

        return redirect()->route('book-requests.admin')->with('status', 'Book request updated.');
    }
}
