<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportsController extends Controller
{
    public function index(Request $request): View
    {
        [$from, $to] = $this->dateRange($request);

        $loans = DB::table('loans')
            ->whereDate('issued_at', '>=', $from)
            ->whereDate('issued_at', '<=', $to);

        $loanActivity = (clone $loans)
            ->selectRaw('DATE(issued_at) as day, COUNT(*) as total')
            ->groupByRaw('DATE(issued_at)')
            ->orderBy('day')
            ->get();

        $popularBooks = DB::table('loans')
            ->join('book_copies', 'loans.book_copy_id', '=', 'book_copies.id')
            ->join('books', 'book_copies.book_id', '=', 'books.id')
            ->whereDate('loans.issued_at', '>=', $from)
            ->whereDate('loans.issued_at', '<=', $to)
            ->select('books.id', 'books.title', 'books.authors')
            ->selectRaw('COUNT(loans.id) as loan_count')
            ->groupBy('books.id', 'books.title', 'books.authors')
            ->orderByDesc('loan_count')
            ->orderBy('books.title')
            ->limit(10)
            ->get();

        $mostActiveMembers = DB::table('loans')
            ->join('users', 'loans.user_id', '=', 'users.id')
            ->whereDate('loans.issued_at', '>=', $from)
            ->whereDate('loans.issued_at', '<=', $to)
            ->select('users.id', 'users.name', 'users.library_card_number')
            ->selectRaw('COUNT(loans.id) as loan_count')
            ->groupBy('users.id', 'users.name', 'users.library_card_number')
            ->orderByDesc('loan_count')
            ->orderBy('users.name')
            ->limit(10)
            ->get();

        return view('reports.index', [
            'from' => $from,
            'to' => $to,
            'metrics' => [
                'loans_issued' => (clone $loans)->count(),
                'loans_returned' => (clone $loans)->whereNotNull('returned_at')->count(),
                'active_loans' => DB::table('loans')->whereNull('returned_at')->count(),
                'overdue_loans' => DB::table('loans')->whereNull('returned_at')->whereDate('due_at', '<', today())->count(),
                'available_copies' => DB::table('book_copies')->where('status', 'available')->count(),
                'waiting_reservations' => DB::table('reservations')->where('status', 'waiting')->count(),
                'ready_reservations' => DB::table('reservations')->where('status', 'ready')->count(),
                'unpaid_fines_pkr' => (int) DB::table('fines')->whereNull('paid_at')->whereNull('waived_at')->sum('amount_pkr'),
                'paid_fines_pkr' => (int) DB::table('fines')->whereNotNull('paid_at')->whereBetween('paid_at', [$from.' 00:00:00', $to.' 23:59:59'])->sum('amount_pkr'),
            ],
            'loanActivity' => $loanActivity,
            'popularBooks' => $popularBooks,
            'mostActiveMembers' => $mostActiveMembers,
        ]);
    }

    public function exportLoans(Request $request): StreamedResponse
    {
        [$from, $to] = $this->dateRange($request);
        $loans = DB::table('loans')
            ->join('users', 'loans.user_id', '=', 'users.id')
            ->join('book_copies', 'loans.book_copy_id', '=', 'book_copies.id')
            ->join('books', 'book_copies.book_id', '=', 'books.id')
            ->leftJoin('fines', 'fines.loan_id', '=', 'loans.id')
            ->whereDate('loans.issued_at', '>=', $from)
            ->whereDate('loans.issued_at', '<=', $to)
            ->orderBy('loans.issued_at')
            ->orderBy('loans.id')
            ->select([
                'loans.id',
                'users.name as member_name',
                'users.library_card_number',
                'books.title as book_title',
                'book_copies.barcode',
                'loans.issued_at',
                'loans.due_at',
                'loans.returned_at',
                'loans.renewal_count',
                'fines.amount_pkr as fine_amount_pkr',
                'fines.paid_at as fine_paid_at',
            ]);

        return response()->streamDownload(function () use ($loans): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, [
                'Loan ID',
                'Member',
                'Library Card',
                'Book',
                'Barcode',
                'Issued At',
                'Due At',
                'Returned At',
                'Renewals',
                'Fine (PKR)',
                'Fine Paid At',
            ]);

            $loans->chunk(500, function ($batch) use ($output): void {
                foreach ($batch as $loan) {
                    fputcsv($output, [
                        $loan->id,
                        $this->safeCsvText($loan->member_name),
                        $this->safeCsvText($loan->library_card_number),
                        $this->safeCsvText($loan->book_title),
                        $this->safeCsvText($loan->barcode),
                        $loan->issued_at,
                        $loan->due_at,
                        $loan->returned_at,
                        $loan->renewal_count,
                        $loan->fine_amount_pkr,
                        $loan->fine_paid_at,
                    ]);
                }
            });

            fclose($output);
        }, "loan-report-{$from}-{$to}.csv", [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function fineReport(Request $request): View
    {
        [$from, $to] = $this->dateRange($request);
        $filters = $this->fineFilters($request);
        $fines = $this->filteredFinesQuery($from, $to, $filters);

        $rows = (clone $fines)
            ->orderByDesc('fines.created_at')
            ->orderByDesc('fines.id')
            ->paginate(25)
            ->withQueryString();

        $totals = (clone $fines)
            ->selectRaw('COUNT(*) as fine_count')
            ->selectRaw('COALESCE(SUM(fines.amount_pkr), 0) as assessed_pkr')
            ->selectRaw('COALESCE(SUM(CASE WHEN fines.paid_at IS NOT NULL THEN fines.amount_pkr ELSE 0 END), 0) as paid_pkr')
            ->selectRaw('COALESCE(SUM(CASE WHEN fines.paid_at IS NULL AND fines.waived_at IS NULL THEN fines.amount_pkr ELSE 0 END), 0) as outstanding_pkr')
            ->first();

        $waivedPkr = (clone $fines)
            ->join('fine_adjustments as waiver_adjustments', function ($join): void {
                $join->on('waiver_adjustments.fine_id', '=', 'fines.id')
                    ->where('waiver_adjustments.action', '=', 'waive');
            })
            ->sum('waiver_adjustments.previous_amount_pkr');

        $fineActivity = (clone $fines)
            ->selectRaw('DATE(fines.created_at) as day, COUNT(*) as fine_count, COALESCE(SUM(fines.amount_pkr), 0) as amount_pkr')
            ->groupByRaw('DATE(fines.created_at)')
            ->orderBy('day')
            ->get();

        $unpaidFilters = array_merge($filters, ['status' => 'unpaid']);
        $topDefaulters = $this->filteredFinesQuery($from, $to, $unpaidFilters)
            ->select('users.id', 'users.name', 'users.library_card_number')
            ->selectRaw('SUM(fines.amount_pkr) as outstanding_pkr')
            ->groupBy('users.id', 'users.name', 'users.library_card_number')
            ->orderByDesc('outstanding_pkr')
            ->orderBy('users.name')
            ->limit(10)
            ->get();

        return view('reports.fines', [
            'from' => $from,
            'to' => $to,
            'filters' => $filters,
            'members' => DB::table('users')->where('role', 'member')->orderBy('name')->get(['id', 'name', 'library_card_number']),
            'fines' => $rows,
            'totals' => [
                'count' => (int) $totals->fine_count,
                'assessed_pkr' => (int) $totals->assessed_pkr,
                'paid_pkr' => (int) $totals->paid_pkr,
                'outstanding_pkr' => (int) $totals->outstanding_pkr,
                'waived_pkr' => (int) $waivedPkr,
            ],
            'fineActivity' => $fineActivity,
            'topDefaulters' => $topDefaulters,
        ]);
    }

    public function exportFines(Request $request): StreamedResponse
    {
        [$from, $to] = $this->dateRange($request);
        $filters = $this->fineFilters($request);
        $fines = $this->filteredFinesQuery($from, $to, $filters)
            ->orderBy('fines.created_at')
            ->orderBy('fines.id');

        return response()->streamDownload(function () use ($fines): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, [
                'Fine ID',
                'Member',
                'Library Card',
                'Book',
                'Amount (PKR)',
                'Status',
                'Assessed At',
                'Paid At',
                'Waived At',
                'Waived Reason',
            ]);

            $fines->chunk(500, function ($batch) use ($output): void {
                foreach ($batch as $fine) {
                    fputcsv($output, [
                        $fine->id,
                        $this->safeCsvText($fine->member_name),
                        $this->safeCsvText($fine->library_card_number),
                        $this->safeCsvText($fine->book_title),
                        $fine->amount_pkr,
                        $fine->status,
                        $fine->created_at,
                        $fine->paid_at,
                        $fine->waived_at,
                        $this->safeCsvText($fine->waived_reason),
                    ]);
                }
            });

            fclose($output);
        }, "fine-report-{$from}-{$to}.csv", [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function fineFilters(Request $request): array
    {
        return $request->validate([
            'status' => ['nullable', Rule::in(['all', 'paid', 'unpaid', 'waived'])],
            'member' => ['nullable', 'integer', Rule::exists('users', 'id')->where('role', 'member')],
        ]);
    }

    private function filteredFinesQuery(string $from, string $to, array $filters)
    {
        return DB::table('fines')
            ->join('loans', 'fines.loan_id', '=', 'loans.id')
            ->join('users', 'loans.user_id', '=', 'users.id')
            ->join('book_copies', 'loans.book_copy_id', '=', 'book_copies.id')
            ->join('books', 'book_copies.book_id', '=', 'books.id')
            ->whereDate('fines.created_at', '>=', $from)
            ->whereDate('fines.created_at', '<=', $to)
            ->when(isset($filters['member']), fn ($query) => $query->where('users.id', $filters['member']))
            ->when(($filters['status'] ?? 'all') !== 'all', function ($query) use ($filters): void {
                match ($filters['status']) {
                    'paid' => $query->whereNotNull('fines.paid_at'),
                    'waived' => $query->whereNotNull('fines.waived_at'),
                    'unpaid' => $query->whereNull('fines.paid_at')->whereNull('fines.waived_at'),
                };
            })
            ->select([
                'fines.id',
                'users.name as member_name',
                'users.library_card_number',
                'books.title as book_title',
                'fines.amount_pkr',
                'fines.created_at',
                'fines.paid_at',
                'fines.waived_at',
                'fines.waived_reason',
            ])
            ->selectRaw("CASE WHEN fines.waived_at IS NOT NULL THEN 'waived' WHEN fines.paid_at IS NOT NULL THEN 'paid' ELSE 'unpaid' END as status");
    }

    private function dateRange(Request $request): array
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'to' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
        ]);

        $from = $validated['from'] ?? today()->subDays(29)->toDateString();
        $to = $validated['to'] ?? today()->toDateString();

        if ($from > $to) {
            throw ValidationException::withMessages([
                'from' => 'The start date must be on or before the end date.',
            ]);
        }

        return [$from, $to];
    }

    private function safeCsvText(?string $value): ?string
    {
        if ($value !== null && preg_match('/^[\s]*[=+\-@]/u', $value)) {
            return "'".$value;
        }

        return $value;
    }
}
