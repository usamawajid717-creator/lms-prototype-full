<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\BookCopy;
use App\Models\LibraryPolicy;
use App\Models\Reservation;
use App\Models\User;
use App\Services\ReservationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ReservationController extends Controller
{
    public function index(Request $request, ReservationService $reservations): View
    {
        $reservations->expireHolds();

        $query = Reservation::query()
            ->with(['member', 'book', 'copy'])
            ->when(! $request->user()->isAdmin(), fn ($query) => $query->where('user_id', $request->user()->id))
            ->orderByRaw("CASE status WHEN 'ready' THEN 0 WHEN 'waiting' THEN 1 ELSE 2 END")
            ->orderBy('created_at')
            ->orderBy('id');
        $items = $query->paginate(20);
        $queuePositions = [];

        if ($request->user()->isAdmin()) {
            $position = [];
            $waiting = Reservation::query()
                ->whereIn('book_id', $items->getCollection()->pluck('book_id')->unique())
                ->where('status', 'waiting')
                ->orderBy('book_id')
                ->orderBy('created_at')
                ->orderBy('id')
                ->get(['id', 'book_id']);

            foreach ($waiting as $reservation) {
                $queuePositions[$reservation->id] = $position[$reservation->book_id] = ($position[$reservation->book_id] ?? 0) + 1;
            }
        }

        return view($request->user()->isAdmin() ? 'reservations.admin-index' : 'reservations.mine', [
            'reservations' => $items,
            'queuePositions' => $queuePositions,
        ]);
    }

    public function store(Book $book, Request $request, ReservationService $reservations): RedirectResponse
    {
        abort_if($request->user()->isAdmin(), 403, 'Only library members can reserve books.');
        $reservations->expireHolds($book->id);

        DB::transaction(function () use ($book, $request): void {
            $lockedBook = Book::query()->lockForUpdate()->findOrFail($book->id);
            $member = User::query()->lockForUpdate()->findOrFail($request->user()->id);

            if ($member->effective_membership_status !== 'active') {
                throw ValidationException::withMessages([
                    'reservation' => 'Only members with an active, unexpired membership can reserve books.',
                ]);
            }

            if ($lockedBook->available_copies > 0) {
                throw ValidationException::withMessages([
                    'reservation' => 'This book has available copies. Please borrow one instead of reserving it.',
                ]);
            }

            if ($member->reservations()->whereIn('status', ['waiting', 'ready'])->where('book_id', $lockedBook->id)->exists()) {
                throw ValidationException::withMessages([
                    'reservation' => 'You already have an active reservation for this book.',
                ]);
            }

            $maxActiveReservations = LibraryPolicy::current()->max_active_reservations;
            if ($member->reservations()->whereIn('status', ['waiting', 'ready'])->count() >= $maxActiveReservations) {
                throw ValidationException::withMessages([
                    'reservation' => 'A member may have no more than '.$maxActiveReservations.' active reservations.',
                ]);
            }

            Reservation::create([
                'user_id' => $member->id,
                'book_id' => $lockedBook->id,
                'status' => 'waiting',
            ]);
        });

        return redirect()->route('reservations.mine')->with('status', 'Reservation added to the waiting list.');
    }

    public function cancel(Reservation $reservation, Request $request, ReservationService $reservations): RedirectResponse
    {
        abort_unless($request->user()->isAdmin() || $reservation->user_id === $request->user()->id, 403);
        $reservations->expireHolds($reservation->book_id);

        DB::transaction(function () use ($reservation, $reservations): void {
            $book = Book::query()->lockForUpdate()->findOrFail($reservation->book_id);
            $lockedReservation = Reservation::query()->lockForUpdate()->findOrFail($reservation->id);

            if (! in_array($lockedReservation->status, ['waiting', 'ready'], true)) {
                throw ValidationException::withMessages([
                    'reservation' => 'Only waiting or ready reservations can be cancelled.',
                ]);
            }

            $copy = null;
            if ($lockedReservation->status === 'ready' && $lockedReservation->book_copy_id) {
                $copy = BookCopy::query()->lockForUpdate()->findOrFail($lockedReservation->book_copy_id);
            }

            $lockedReservation->update([
                'status' => 'cancelled',
                'book_copy_id' => null,
                'hold_expires_at' => null,
            ]);

            if ($copy && $copy->status === 'reserved') {
                $copy->update(['status' => 'available']);
                $book->increment('available_copies');
                $reservations->allocateNext($book, $copy);
            }
        });

        $route = $request->user()->isAdmin() ? 'reservations.index' : 'reservations.mine';

        return redirect()->route($route)->with('status', 'Reservation cancelled.');
    }
}
