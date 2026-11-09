<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\BookCategory;
use App\Models\Loan;
use App\Models\LoanReminder;
use App\Models\LibraryPolicy;
use App\Models\User;
use App\Notifications\LibraryNotification;
use App\Services\LoanReminderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class LoanReminderTest extends TestCase
{
    use RefreshDatabase;

    public function test_reminders_are_sent_two_days_before_due_and_once_when_overdue(): void
    {
        Notification::fake();
        $dueSoonLoan = $this->createLoan(today()->addDays(2));
        $overdueLoan = $this->createLoan(today()->subDays(3));
        $returnedLoan = $this->createLoan(today()->subDay(), ['returned_at' => now()]);

        $sent = app(LoanReminderService::class)->sendDueDateReminders();

        $this->assertSame(2, $sent);
        $this->assertDatabaseHas('loan_reminders', ['loan_id' => $dueSoonLoan->id, 'reminder_type' => 'due_soon']);
        $this->assertDatabaseHas('loan_reminders', ['loan_id' => $overdueLoan->id, 'reminder_type' => 'overdue']);
        $this->assertDatabaseMissing('loan_reminders', ['loan_id' => $returnedLoan->id]);
        Notification::assertSentTo($dueSoonLoan->member, LibraryNotification::class);
        Notification::assertSentTo($overdueLoan->member, LibraryNotification::class);

        $this->assertSame(0, app(LoanReminderService::class)->sendDueDateReminders());
        $this->assertSame(1, LoanReminder::query()->where('loan_id', $overdueLoan->id)->count());
    }

    public function test_reminder_command_is_registered_for_daily_schedule(): void
    {
        Notification::fake();
        $loan = $this->createLoan(today()->addDays(2));

        $this->artisan('loans:send-reminders')
            ->expectsOutput('Sent 1 loan reminder(s).')
            ->assertExitCode(0);

        $this->assertDatabaseHas('loan_reminders', [
            'loan_id' => $loan->id,
            'reminder_type' => 'due_soon',
        ]);
    }

    public function test_configured_due_reminder_lead_time_is_used(): void
    {
        Notification::fake();
        LibraryPolicy::current()->update(['due_reminder_days' => 4]);
        $loan = $this->createLoan(today()->addDays(4));

        $this->assertSame(1, app(LoanReminderService::class)->sendDueDateReminders());
        $this->assertDatabaseHas('loan_reminders', [
            'loan_id' => $loan->id,
            'reminder_type' => 'due_soon',
        ]);
    }

    private function createLoan($dueAt, array $attributes = []): Loan
    {
        $category = BookCategory::create(['name' => 'Reminders '.uniqid(), 'is_active' => true]);
        $book = Book::create([
            'isbn' => '978'.str_pad((string) random_int(0, 9999999999), 10, '0', STR_PAD_LEFT),
            'title' => 'Reminder Test Book',
            'authors' => 'Reminder Author',
            'category_id' => $category->id,
            'total_copies' => 1,
            'available_copies' => 0,
        ]);
        $copy = $book->copies()->firstOrFail();
        $member = User::factory()->create(['role' => 'member']);
        $admin = User::factory()->create(['role' => 'admin']);

        return Loan::create(array_merge([
            'user_id' => $member->id,
            'book_copy_id' => $copy->id,
            'issued_by' => $admin->id,
            'issued_at' => today()->subDays(10),
            'due_at' => $dueAt,
        ], $attributes));
    }
}
