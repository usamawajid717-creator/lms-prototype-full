<?php

namespace App\Http\Controllers;

use App\Models\BookReview;
use App\Models\BookReviewReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BookReviewModerationController extends Controller
{
    public function index(): View
    {
        return view('book-reviews.reports', [
            'reports' => BookReviewReport::query()
                ->with(['review.book', 'review.member', 'reporter', 'resolver'])
                ->orderByRaw('resolved_at IS NULL DESC')
                ->latest()
                ->paginate(20),
        ]);
    }

    public function report(Request $request, BookReview $bookReview): RedirectResponse
    {
        abort_if($request->user()->isAdmin(), 403, 'Only members can report reviews.');
        abort_if($bookReview->user_id === $request->user()->id, 403, 'You cannot report your own review.');
        abort_if($bookReview->hidden_at, 404);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        $existing = BookReviewReport::query()
            ->where('book_review_id', $bookReview->id)
            ->where('user_id', $request->user()->id)
            ->exists();
        if ($existing) {
            throw ValidationException::withMessages([
                'report' => 'You have already reported this review.',
            ]);
        }

        $bookReview->reports()->create([
            'user_id' => $request->user()->id,
            'reason' => trim($validated['reason']),
        ]);

        return redirect()->route('catalog.show', $bookReview->book_id)
            ->with('status', 'Your report was sent to the library administrators.');
    }

    public function resolve(Request $request, BookReviewReport $report): RedirectResponse
    {
        DB::transaction(function () use ($request, $report): void {
            $lockedReport = BookReviewReport::query()->lockForUpdate()->findOrFail($report->id);
            if ($lockedReport->resolved_at) {
                throw ValidationException::withMessages(['report' => 'This review report has already been resolved.']);
            }

            $review = BookReview::query()->lockForUpdate()->findOrFail($lockedReport->book_review_id);
            $validated = $request->validate(['action' => ['required', 'in:hide,keep']]);
            $hide = $validated['action'] === 'hide';
            $review->update([
                'hidden_at' => $hide ? now() : null,
                'hidden_by' => $hide ? $request->user()->id : null,
            ]);
            $lockedReport->update([
                'resolved_at' => now(),
                'resolved_by' => $request->user()->id,
            ]);
        });

        return redirect()->route('book-reviews.reports')->with('status', 'Review report resolved.');
    }

    public function visibility(Request $request, BookReview $bookReview): RedirectResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'in:hide,restore'],
        ]);
        $hide = $validated['action'] === 'hide';
        $bookReview->update([
            'hidden_at' => $hide ? now() : null,
            'hidden_by' => $hide ? $request->user()->id : null,
        ]);

        return redirect()->route('book-reviews.reports')->with('status', $hide ? 'Review hidden.' : 'Review restored.');
    }
}
