@extends('layouts.app')

@section('title', 'Fine Report')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h4 class="fw-bold text-dark mb-1">Fine Report</h4>
        <p class="text-muted mb-0">Fine ledger, collection, outstanding balances, waivers, and daily trends.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('reports.fines.export', array_merge(['from' => $from, 'to' => $to], $filters)) }}" class="btn btn-outline-primary">
            <i class="bi bi-download me-1"></i>Export fines CSV
        </a>
        <a href="{{ route('reports.index') }}" class="btn btn-outline-secondary">Operational Reports</a>
    </div>
</div>

<div class="card form-card border-0 mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('reports.fines') }}" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label for="from" class="form-label">Assessed from</label>
                <input id="from" name="from" type="date" class="form-control" value="{{ $from }}" max="{{ today()->toDateString() }}" required>
            </div>
            <div class="col-md-3">
                <label for="to" class="form-label">Assessed through</label>
                <input id="to" name="to" type="date" class="form-control" value="{{ $to }}" max="{{ today()->toDateString() }}" required>
            </div>
            <div class="col-md-2">
                <label for="status" class="form-label">Status</label>
                <select id="status" name="status" class="form-select">
                    <option value="all" @selected(($filters['status'] ?? 'all') === 'all')>All</option>
                    <option value="paid" @selected(($filters['status'] ?? '') === 'paid')>Paid</option>
                    <option value="unpaid" @selected(($filters['status'] ?? '') === 'unpaid')>Unpaid</option>
                    <option value="waived" @selected(($filters['status'] ?? '') === 'waived')>Waived</option>
                </select>
            </div>
            <div class="col-md-3">
                <label for="member" class="form-label">Member</label>
                <select id="member" name="member" class="form-select">
                    <option value="">All members</option>
                    @foreach ($members as $member)
                        <option value="{{ $member->id }}" @selected((string) ($filters['member'] ?? '') === (string) $member->id)>
                            {{ $member->name }} ({{ $member->library_card_number }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-1 d-grid">
                <button class="btn btn-outline-primary" type="submit">Filter</button>
            </div>
        </form>
        @if ($errors->any())
            <div class="alert alert-danger mt-3 mb-0"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif
    </div>
</div>

<div class="row g-3 mb-4">
    @foreach ([
        ['Fines in report', number_format($totals['count']), 'primary'],
        ['Current fine amount', 'PKR '.number_format($totals['assessed_pkr']), 'info'],
        ['Collected', 'PKR '.number_format($totals['paid_pkr']), 'success'],
        ['Outstanding', 'PKR '.number_format($totals['outstanding_pkr']), 'danger'],
        ['Waived amount', 'PKR '.number_format($totals['waived_pkr']), 'warning'],
    ] as [$label, $value, $color])
        <div class="col-sm-6 col-lg">
            <div class="card form-card border-0 h-100">
                <div class="card-body">
                    <div class="text-muted small">{{ $label }}</div>
                    <div class="fs-4 fw-bold text-{{ $color }}">{{ $value }}</div>
                </div>
            </div>
        </div>
    @endforeach
</div>

<div class="card form-card border-0 mb-4">
    <div class="card-header bg-white"><strong>Fines assessed by day</strong></div>
    <div class="card-body">
        @php($maxDailyFines = max((int) $fineActivity->max('fine_count'), 1))
        @forelse ($fineActivity as $day)
            <div class="d-flex align-items-center gap-3 mb-2">
                <span class="small text-muted" style="width: 6rem">{{ $day->day }}</span>
                <div class="progress flex-grow-1" role="progressbar" aria-label="Fines assessed on {{ $day->day }}" aria-valuenow="{{ $day->fine_count }}" aria-valuemin="0" aria-valuemax="{{ $maxDailyFines }}">
                    <div class="progress-bar" style="width: {{ ($day->fine_count / $maxDailyFines) * 100 }}%"></div>
                </div>
                <span class="small fw-semibold">{{ $day->fine_count }} · PKR {{ number_format($day->amount_pkr) }}</span>
            </div>
        @empty
            <p class="text-muted mb-0">No fines were assessed for these filters.</p>
        @endforelse
    </div>
</div>

<div class="card form-card border-0 mb-4">
    <div class="card-header bg-white"><strong>Top outstanding balances</strong></div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr><th>Member</th><th>Library card</th><th>Outstanding (PKR)</th></tr></thead>
            <tbody>
                @forelse ($topDefaulters as $member)
                    <tr><td>{{ $member->name }}</td><td>{{ $member->library_card_number }}</td><td>PKR {{ number_format($member->outstanding_pkr) }}</td></tr>
                @empty
                    <tr><td colspan="3" class="text-center text-muted py-4">No outstanding balances for these filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="card form-card border-0">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr><th>Member</th><th>Book</th><th>Amount</th><th>Status</th><th>Assessed</th><th>Settled</th></tr>
            </thead>
            <tbody>
                @forelse ($fines as $fine)
                    <tr>
                        <td>{{ $fine->member_name }}<br><span class="text-muted small">{{ $fine->library_card_number }}</span></td>
                        <td>{{ $fine->book_title }}</td>
                        <td>PKR {{ number_format($fine->amount_pkr) }}</td>
                        <td>
                            <span class="badge {{ $fine->status === 'paid' ? 'text-bg-success' : ($fine->status === 'waived' ? 'text-bg-secondary' : 'text-bg-danger') }}">
                                {{ ucfirst($fine->status) }}
                            </span>
                        </td>
                        <td>{{ \Illuminate\Support\Carbon::parse($fine->created_at)->format('Y-m-d') }}</td>
                        <td>
                            @if ($fine->paid_at)
                                Paid {{ \Illuminate\Support\Carbon::parse($fine->paid_at)->format('Y-m-d') }}
                            @elseif ($fine->waived_at)
                                Waived {{ \Illuminate\Support\Carbon::parse($fine->waived_at)->format('Y-m-d') }}
                                @if ($fine->waived_reason)<br><span class="small text-muted">{{ $fine->waived_reason }}</span>@endif
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">No fines found for these filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-body">{{ $fines->links() }}</div>
</div>
@endsection
