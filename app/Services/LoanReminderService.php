<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\LibraryPolicy;
use App\Models\LoanReminder;
use App\Notifications\LibraryNotification;
use Illuminate\Support\Facades\DB;

class LoanReminderService
{
    public function sendDueDateReminders(): int
    {
        $dueSoonDate = today()->addDays(LibraryPolicy::current()->due_reminder_days);
        $loanIds = Loan::query()
            ->whereNull('returned_at')
            ->whereDate('due_at', $dueSoonDate)
            ->whereDoesntHave('reminders', fn ($query) => $query->where('reminder_type', 'due_soon'))
            ->orderBy('id')
            ->pluck('id');

        $sent = 0;
        foreach ($loanIds as $loanId) {
            $sent += $this->sendReminder($loanId, 'due_soon', $dueSoonDate);
        }

        $overdueLoanIds = Loan::query()
            ->whereNull('returned_at')
            ->whereDate('due_at', '<', today())
            ->whereDoesntHave('reminders', fn ($query) => $query->where('reminder_type', 'overdue'))
            ->orderBy('id')
            ->pluck('id');

        foreach ($overdueLoanIds as $loanId) {
            $sent += $this->sendReminder($loanId, 'overdue');
        }

        return $sent;
    }

    private function sendReminder(int $loanId, string $type, ?object $dueSoonDate = null): int
    {
        return DB::transaction(function () use ($loanId, $type, $dueSoonDate): int {
            $loan = Loan::query()->lockForUpdate()->with(['member', 'copy.book'])->find($loanId);

            if (! $loan || ! $loan->isActive()) {
                return 0;
            }

            if ($type === 'due_soon' && ! $loan->due_at->isSameDay($dueSoonDate)) {
                return 0;
            }
            if ($type === 'overdue' && ! $loan->due_at->isBefore(today())) {
                return 0;
            }
            if (LoanReminder::query()->where('loan_id', $loan->id)->where('reminder_type', $type)->exists()) {
                return 0;
            }

            if ($type === 'due_soon') {
                $title = 'Book due soon';
                $message = '"'.$loan->copy->book->title.'" is due on '.$loan->due_at->format('Y-m-d').'. Please return or renew it before the due date.';
            } else {
                $daysOverdue = $loan->due_at->diffInDays(today());
                $title = 'Book overdue';
                $message = '"'.$loan->copy->book->title.'" was due on '.$loan->due_at->format('Y-m-d').' and is '.$daysOverdue.' day(s) overdue. Please return it to avoid additional fines.';
            }

            $loan->member->notify(new LibraryNotification(
                $title,
                $message,
                route('loans.mine'),
            ));

            LoanReminder::query()->create([
                'loan_id' => $loan->id,
                'reminder_type' => $type,
                'sent_on' => today(),
            ]);

            return 1;
        });
    }
}
