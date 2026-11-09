<?php

namespace App\Services;

use App\Models\Book;
use App\Models\BookCopy;
use App\Models\LibraryPolicy;
use App\Models\Reservation;
use App\Models\User;
use App\Notifications\LibraryNotification;
use Illuminate\Support\Facades\DB;

class ReservationService
{
    public function expireHolds(?int $bookId = null): void
    {
        $expiredIds = Reservation::query()
            ->where('status', 'ready')
            ->where('hold_expires_at', '<=', now())
            ->when($bookId, fn ($query) => $query->where('book_id', $bookId))
            ->orderBy('id')
            ->pluck('id');

        foreach ($expiredIds as $reservationId) {
            DB::transaction(function () use ($reservationId): void {
                $reservationRecord = Reservation::query()->find($reservationId);

                if (! $reservationRecord || ! $reservationRecord->book_copy_id) {
                    return;
                }

                $book = Book::query()->lockForUpdate()->findOrFail($reservationRecord->book_id);
                $reservation = Reservation::query()->lockForUpdate()->findOrFail($reservationId);

                if ($reservation->status !== 'ready' || $reservation->hold_expires_at?->isFuture()) {
                    return;
                }

                $copy = BookCopy::query()->lockForUpdate()->findOrFail($reservation->book_copy_id);
                $reservation->update([
                    'status' => 'expired',
                    'book_copy_id' => null,
                    'hold_expires_at' => null,
                ]);
                $reservation->member->notify(new LibraryNotification(
                    'Reservation expired',
                    'Your pickup window for "'.$reservation->book->title.'" expired. If you are still interested, join the waitlist again.',
                    route('catalog.show', $reservation->book_id),
                ));

                if ($copy->status === 'reserved') {
                    $copy->update(['status' => 'available']);
                    $book->increment('available_copies');
                    $this->allocateNext($book, $copy);
                }
            });
        }
    }

    public function allocateNext(Book $book, BookCopy $copy): ?Reservation
    {
        $holdDays = LibraryPolicy::current()->reservation_hold_days;
        while ($reservation = Reservation::query()
            ->where('book_id', $book->id)
            ->where('status', 'waiting')
            ->orderBy('created_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->first()) {
            $member = User::query()->lockForUpdate()->findOrFail($reservation->user_id);

            if ($member->effective_membership_status !== 'active') {
                $reservation->update(['status' => 'cancelled']);

                continue;
            }

            $reservation->update([
                'status' => 'ready',
                'book_copy_id' => $copy->id,
                'hold_expires_at' => now()->addDays($holdDays),
            ]);
            $copy->update(['status' => 'reserved']);
            $book->decrement('available_copies');
            $member->notify(new LibraryNotification(
                'Book ready for pickup',
                'A copy of "'.$book->title.'" is ready. Please collect it by '.$reservation->hold_expires_at->format('Y-m-d H:i').'.',
                route('reservations.mine'),
            ));

            return $reservation;
        }

        return null;
    }
}
