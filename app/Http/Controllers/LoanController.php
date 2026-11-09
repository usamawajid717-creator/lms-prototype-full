<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Loan;
use App\Models\LibraryPolicy;
use App\Models\User;
use App\Models\Reservation;
use App\Services\ReservationService;
use App\Notifications\LibraryNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoanController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate([
            'status' => ['nullable', Rule::in(['active', 'returned'])],
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $loans = Loan::query()
            ->with(['member', 'copy.book', 'fine.adjustments.admin', 'issueOverrider'])
            ->when($request->input('status') === 'active', fn ($query) => $query->whereNull('returned_at'))
            ->when($request->input('status') === 'returned', fn ($query) => $query->whereNotNull('returned_at'))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->trim()->toString();
                $query->where(function ($query) use ($search) {
                    $query->whereHas('member', fn ($member) => $member->where('name', 'like', "%{$search}%")
                        ->orWhere('library_card_number', 'like', "%{$search}%"))
                        ->orWhereHas('copy', fn ($copy) => $copy->where('barcode', 'like', "%{$search}%")
                            ->orWhereHas('book', fn ($book) => $book->where('title', 'like', "%{$search}%")));
                });
            })
            ->orderByRaw('returned_at IS NULL DESC')
            ->orderByDesc('issued_at')
            ->paginate(20)
            ->withQueryString();

        return view('loans.index', compact('loans'));
    }

    public function create(): View
    {
        return view('loans.create', ['policy' => LibraryPolicy::current()]);
    }

    public function store(Request $request, ReservationService $reservations): RedirectResponse
    {
        $validated = $request->validate([
            'member_card' => [
                'required',
                'string',
                Rule::exists('users', 'library_card_number')->where('role', 'member'),
            ],
            'barcode' => ['required', 'string', Rule::exists('book_copies', 'barcode')],
            'override_reason' => ['nullable', 'string', 'min:10', 'max:2000'],
        ]);

        $reservations->expireHolds();

        $policy = LibraryPolicy::current();
        DB::transaction(function () use ($validated, $request, $policy): void {
            $bookId = BookCopy::query()->where('barcode', $validated['barcode'])->value('book_id');
            $book = Book::query()->whereKey($bookId)->lockForUpdate()->firstOrFail();
            $member = User::query()->where('library_card_number', $validated['member_card'])
                ->lockForUpdate()->firstOrFail();
            $copy = BookCopy::query()->where('barcode', $validated['barcode'])
                ->lockForUpdate()->firstOrFail();

            if ($member->effective_membership_status !== 'active') {
                throw ValidationException::withMessages([
                    'member_card' => 'Only members with an active, unexpired membership can borrow books.',
                ]);
            }

            $reservation = Reservation::query()
                ->where('book_copy_id', $copy->id)
                ->where('status', 'ready')
                ->lockForUpdate()
                ->first();

            if ($copy->status === 'reserved' && (! $reservation || $reservation->user_id !== $member->id)) {
                throw ValidationException::withMessages([
                    'barcode' => 'This copy is reserved for another member.',
                ]);
            }

            if (! in_array($copy->status, ['available', 'reserved'], true)) {
                throw ValidationException::withMessages([
                    'barcode' => 'This copy is not currently available.',
                ]);
            }

            if ($member->loans()->whereNull('returned_at')->count() >= $policy->max_active_loans) {
                throw ValidationException::withMessages([
                    'member_card' => 'A member may have no more than '.$policy->max_active_loans.' active loans.',
                ]);
            }

            $hasOverdueLoans = $member->loans()
                ->whereNull('returned_at')
                ->whereDate('due_at', '<', today())
                ->exists();
            $outstandingFines = $member->fines()
                ->whereNull('paid_at')
                ->whereNull('waived_at')
                ->where('amount_pkr', '>', 0)
                ->sum('amount_pkr');
            $hasExcessiveFines = $outstandingFines > $policy->issue_fine_limit_pkr;
            $requiresOverride = $hasOverdueLoans || $hasExcessiveFines;
            $overrideReason = trim((string) ($validated['override_reason'] ?? ''));

            if ($requiresOverride && $overrideReason === '') {
                $blocks = [];
                if ($hasOverdueLoans) {
                    $blocks[] = 'one or more overdue loans';
                }
                if ($hasExcessiveFines) {
                    $blocks[] = 'an outstanding fine balance of PKR '.number_format($outstandingFines)
                        .' above the PKR '.number_format($policy->issue_fine_limit_pkr).' limit';
                }

                throw ValidationException::withMessages([
                    'member_card' => 'Issuing is blocked because the member has '.implode(' and ', $blocks).'. An administrator may override this block with a reason.',
                    'override_reason' => 'Enter a justification to override this issue block.',
                ]);
            }

            $wasAvailable = $copy->status === 'available';
            $copy->update(['status' => 'on_loan']);
            if ($wasAvailable) {
                $book->decrement('available_copies');
            }

            $loan = Loan::create([
                'user_id' => $member->id,
                'book_copy_id' => $copy->id,
                'issued_by' => $request->user()->id,
                'issued_at' => today(),
                'due_at' => today()->addDays($policy->loan_period_days),
                'reservation_id' => $reservation?->id,
                'issue_override_reason' => $requiresOverride ? $overrideReason : null,
                'issue_overridden_by' => $requiresOverride ? $request->user()->id : null,
                'issue_overridden_at' => $requiresOverride ? now() : null,
            ]);

            if ($reservation) {
                $reservation->update([
                    'status' => 'fulfilled',
                    'fulfilled_at' => now(),
                    'book_copy_id' => null,
                    'hold_expires_at' => null,
                ]);
            }
        });

        return redirect()->route('loans.index')->with('status', 'Book issued. It is due in '.$policy->loan_period_days.' days.');
    }

    public function return(Loan $loan, Request $request, ReservationService $reservations): RedirectResponse
    {
        $validated = $request->validate([
            'condition' => ['nullable', Rule::in(['good', 'damaged'])],
            'damage_condition_note' => ['required_if:condition,damaged', 'nullable', 'string', 'min:5', 'max:2000'],
            'damage_fee_pkr' => ['required_if:condition,damaged', 'nullable', 'integer', 'between:0,1000000000'],
        ]);

        DB::transaction(function () use ($loan, $request, $reservations, $validated): void {
            $lockedLoan = Loan::query()->lockForUpdate()->findOrFail($loan->id);

            if (! $lockedLoan->isActive()) {
                throw ValidationException::withMessages(['loan' => 'This loan has already been returned.']);
            }

            $copyRecord = BookCopy::query()->findOrFail($lockedLoan->book_copy_id);
            $book = Book::query()->lockForUpdate()->findOrFail($copyRecord->book_id);
            $copy = BookCopy::query()->lockForUpdate()->findOrFail($lockedLoan->book_copy_id);

            if ($copy->status !== 'on_loan') {
                throw ValidationException::withMessages(['loan' => 'The copy is not marked as on loan.']);
            }

            $returnedAt = now();
            $isDamaged = ($validated['condition'] ?? 'good') === 'damaged';
            $lockedLoan->update([
                'returned_at' => $returnedAt,
                'returned_by' => $request->user()->id,
                'damage_fee_pkr' => $isDamaged ? (int) $validated['damage_fee_pkr'] : 0,
                'damage_condition_note' => $isDamaged ? $validated['damage_condition_note'] : null,
            ]);
            $copy->update([
                'status' => $isDamaged ? 'damaged' : 'available',
                'condition_note' => $isDamaged ? $validated['damage_condition_note'] : null,
            ]);
            if (! $isDamaged) {
                $book->increment('available_copies');
                $reservations->allocateNext($book, $copy);
            }

            $overdueFine = 0;
            if ($returnedAt->toDateString() > $lockedLoan->due_at->toDateString()) {
                $daysOverdue = $lockedLoan->due_at->diffInDays($returnedAt->toDateString());
                $overdueFine = $daysOverdue * LibraryPolicy::current()->fine_per_day_pkr;
            }

            $totalCharge = $overdueFine + ($isDamaged ? (int) $validated['damage_fee_pkr'] : 0);
            if ($totalCharge > 0) {
                $fine = $lockedLoan->fine()->create(['amount_pkr' => $totalCharge]);
                $charges = [];
                if ($overdueFine > 0) {
                    $charges[] = 'an overdue fine of PKR '.number_format($overdueFine);
                }
                if ($isDamaged && (int) $validated['damage_fee_pkr'] > 0) {
                    $charges[] = 'a damage fee of PKR '.number_format((int) $validated['damage_fee_pkr']);
                }
                $lockedLoan->member->notify(new LibraryNotification(
                    'Loan charges assessed',
                    'A total charge of PKR '.number_format($fine->amount_pkr).' ('.implode(' and ', $charges).') was assessed for "'.$lockedLoan->copy->book->title.'". Please contact library staff to record payment.',
                    route('loans.mine'),
                ));
            }
        });

        $fine = $loan->fresh()->fine;
        $message = 'Book returned'.($loan->fresh()->copy->status === 'damaged' ? ' and marked damaged' : '').'.';
        if ($fine) {
            $message .= ' Total fine/charges assessed: PKR '.number_format($fine->amount_pkr).'.';
        }

        return redirect()->route('loans.index')->with('status', $message);
    }

    public function mine(Request $request): View
    {
        $loans = $request->user()->loans()
            ->with(['copy.book', 'fine'])
            ->orderByRaw('returned_at IS NULL DESC')
            ->orderByDesc('issued_at')
            ->paginate(15);
        $policy = LibraryPolicy::current();
        $hasUnpaidFines = $request->user()->fines()
            ->whereNull('paid_at')
            ->whereNull('waived_at')
            ->where('amount_pkr', '>', 0)
            ->exists();

        return view('loans.mine', compact('loans', 'hasUnpaidFines', 'policy'));
    }

    public function renew(Loan $loan, Request $request): RedirectResponse
    {
        abort_unless($request->user()->isAdmin() || $loan->user_id === $request->user()->id, 403);
        $policy = LibraryPolicy::current();

        DB::transaction(function () use ($loan, $policy): void {
            $lockedLoan = Loan::query()->lockForUpdate()->findOrFail($loan->id);
            $member = User::query()->lockForUpdate()->findOrFail($lockedLoan->user_id);

            if (! $lockedLoan->isActive()) {
                throw ValidationException::withMessages(['loan' => 'A returned loan cannot be renewed.']);
            }

            if ($member->effective_membership_status !== 'active') {
                throw ValidationException::withMessages(['loan' => 'Only members with an active membership can renew loans.']);
            }

            if ($member->fines()->whereNull('paid_at')->whereNull('waived_at')->where('amount_pkr', '>', 0)->exists()) {
                throw ValidationException::withMessages(['loan' => 'Unpaid fines must be paid before renewing a loan.']);
            }

            if ($lockedLoan->due_at->isBefore(today())) {
                throw ValidationException::withMessages(['loan' => 'Overdue loans cannot be renewed.']);
            }

            if ($lockedLoan->renewal_count >= $policy->max_renewals) {
                throw ValidationException::withMessages(['loan' => 'This loan has already been renewed.']);
            }

            $lockedLoan->update([
                'due_at' => $lockedLoan->due_at->copy()->addDays($policy->loan_period_days),
                'renewal_count' => $lockedLoan->renewal_count + 1,
            ]);
        });

        $route = $request->user()->isAdmin() ? 'loans.index' : 'loans.mine';

        return redirect()->route($route)->with('status', 'Loan renewed for '.$policy->loan_period_days.' days.');
    }
}
