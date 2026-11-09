<?php

namespace App\Http\Controllers;

use App\Services\ReservationService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MemberDashboardController extends Controller
{
    public function index(Request $request, ReservationService $reservations): View
    {
        $reservations->expireHolds();

        $member = $request->user();
        $activeLoans = $member->loans()
            ->whereNull('returned_at')
            ->with(['copy.book', 'fine'])
            ->orderBy('due_at')
            ->limit(5)
            ->get();
        $activeReservations = $member->reservations()
            ->whereIn('status', ['ready', 'waiting'])
            ->with(['book', 'copy'])
            ->orderByRaw("CASE status WHEN 'ready' THEN 0 ELSE 1 END")
            ->orderBy('created_at')
            ->limit(5)
            ->get();
        $unpaidFines = $member->fines()
            ->whereNull('paid_at')
            ->whereNull('waived_at')
            ->where('amount_pkr', '>', 0)
            ->with('loan.copy.book')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        return view('dashboard.member', [
            'member' => $member,
            'activeLoans' => $activeLoans,
            'activeReservations' => $activeReservations,
            'unpaidFines' => $unpaidFines,
            'metrics' => [
                'active_loans' => $member->loans()->whereNull('returned_at')->count(),
                'overdue_loans' => $member->loans()->whereNull('returned_at')->whereDate('due_at', '<', today())->count(),
                'ready_reservations' => $member->reservations()->where('status', 'ready')->count(),
                'waiting_reservations' => $member->reservations()->where('status', 'waiting')->count(),
                'outstanding_fines_pkr' => (int) $member->fines()
                    ->whereNull('paid_at')
                    ->whereNull('waived_at')
                    ->where('amount_pkr', '>', 0)
                    ->sum('amount_pkr'),
                'unread_notifications' => $member->unreadNotifications()->count(),
            ],
        ]);
    }
}
