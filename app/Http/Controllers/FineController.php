<?php

namespace App\Http\Controllers;

use App\Models\Fine;
use App\Notifications\LibraryNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;

class FineController extends Controller
{
    public function recordPayment(Fine $fine, Request $request): RedirectResponse
    {
        DB::transaction(function () use ($fine, $request): void {
            $lockedFine = Fine::query()->lockForUpdate()->findOrFail($fine->id);

            if ($lockedFine->isSettled()) {
                throw ValidationException::withMessages(['fine' => 'This fine has already been paid or waived.']);
            }

            $lockedFine->update([
                'paid_at' => now(),
                'paid_by' => $request->user()->id,
            ]);
            $lockedFine->loan->member->notify(new LibraryNotification(
                'Fine payment recorded',
                'Your payment of PKR '.number_format($lockedFine->amount_pkr).' has been recorded.',
                route('loans.mine'),
            ));
        });

        return redirect()->route('loans.index')->with('status', 'Simulated payment recorded.');
    }

    public function adjust(Fine $fine, Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'action' => ['required', Rule::in(['adjust', 'waive'])],
            'amount_pkr' => ['required_if:action,adjust', 'nullable', 'integer', 'min:1', 'max:999999999999999'],
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        DB::transaction(function () use ($fine, $request, $validated): void {
            $lockedFine = Fine::query()->lockForUpdate()->findOrFail($fine->id);

            if ($lockedFine->isSettled()) {
                throw ValidationException::withMessages(['fine' => 'Paid or waived fines cannot be adjusted.']);
            }

            $previousAmount = $lockedFine->amount_pkr;
            $newAmount = $validated['action'] === 'waive' ? 0 : (int) $validated['amount_pkr'];

            if ($newAmount === $previousAmount && $validated['action'] === 'adjust') {
                throw ValidationException::withMessages(['amount_pkr' => 'The adjusted amount must differ from the current amount.']);
            }

            $lockedFine->adjustments()->create([
                'admin_id' => $request->user()->id,
                'action' => $validated['action'],
                'previous_amount_pkr' => $previousAmount,
                'new_amount_pkr' => $newAmount,
                'reason' => $validated['reason'],
            ]);

            $lockedFine->update([
                'amount_pkr' => $newAmount,
                'waived_at' => $validated['action'] === 'waive' ? now() : null,
                'waived_by' => $validated['action'] === 'waive' ? $request->user()->id : null,
                'waived_reason' => $validated['action'] === 'waive' ? $validated['reason'] : null,
            ]);

            $lockedFine->loan->member->notify(new LibraryNotification(
                $validated['action'] === 'waive' ? 'Fine waived' : 'Fine adjusted',
                $validated['action'] === 'waive'
                    ? 'Your fine of PKR '.number_format($previousAmount).' was waived. Reason: '.$validated['reason']
                    : 'Your fine was adjusted from PKR '.number_format($previousAmount).' to PKR '.number_format($newAmount).'. Reason: '.$validated['reason'],
                route('loans.mine'),
            ));
        });

        return redirect()->route('loans.index')->with('status', $validated['action'] === 'waive' ? 'Fine waived.' : 'Fine amount adjusted.');
    }
}
