<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $today = today();
        $trendStart = $today->copy()->subDays(13);

        $activityByDay = DB::table('loans')
            ->whereDate('issued_at', '>=', $trendStart)
            ->whereDate('issued_at', '<=', $today)
            ->selectRaw('DATE(issued_at) as day, COUNT(*) as total')
            ->groupByRaw('DATE(issued_at)')
            ->orderBy('day')
            ->get()
            ->keyBy('day');

        $loanActivity = collect(range(0, 13))->map(function (int $offset) use ($trendStart, $activityByDay): object {
            $date = $trendStart->copy()->addDays($offset);

            return (object) [
                'day' => $date->format('M j'),
                'total' => (int) ($activityByDay[$date->toDateString()]->total ?? 0),
            ];
        });

        $recentLoans = DB::table('loans')
            ->join('users', 'loans.user_id', '=', 'users.id')
            ->join('book_copies', 'loans.book_copy_id', '=', 'book_copies.id')
            ->join('books', 'book_copies.book_id', '=', 'books.id')
            ->orderByDesc('loans.issued_at')
            ->orderByDesc('loans.id')
            ->limit(8)
            ->get([
                'loans.id',
                'loans.issued_at',
                'loans.due_at',
                'loans.returned_at',
                'users.name as member_name',
                'books.title as book_title',
            ]);

        return view('dashboard.index', [
            'metrics' => [
                'books' => DB::table('books')->count(),
                'members' => DB::table('users')->where('role', 'member')->count(),
                'copies' => DB::table('book_copies')->count(),
                'available_copies' => DB::table('book_copies')->where('status', 'available')->count(),
                'active_loans' => DB::table('loans')->whereNull('returned_at')->count(),
                'overdue_loans' => DB::table('loans')->whereNull('returned_at')->whereDate('due_at', '<', $today)->count(),
                'returned_this_month' => DB::table('loans')->whereNotNull('returned_at')->whereDate('returned_at', '>=', $today->copy()->startOfMonth())->whereDate('returned_at', '<=', $today)->count(),
                'waiting_reservations' => DB::table('reservations')->where('status', 'waiting')->count(),
                'ready_reservations' => DB::table('reservations')->where('status', 'ready')->count(),
                'outstanding_fines_pkr' => (int) DB::table('fines')->whereNull('paid_at')->whereNull('waived_at')->sum('amount_pkr'),
            ],
            'loanActivity' => $loanActivity,
            'recentLoans' => $recentLoans,
        ]);
    }
}
