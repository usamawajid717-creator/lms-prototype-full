@extends('layouts.app')

@section('title', 'Operational Reports')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h4 class="fw-bold text-dark mb-1">Operational Reports</h4>
        <p class="text-muted mb-0">Circulation, inventory, reservations, fines, and member activity.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('reports.fines', ['from' => $from, 'to' => $to]) }}" class="btn btn-outline-primary">Fine report</a>
        <a href="{{ route('reports.loans.export', ['from' => $from, 'to' => $to]) }}" class="btn btn-outline-primary">
            <i class="bi bi-download me-1"></i>Export loans CSV
        </a>
    </div>
</div>

<div class="card form-card border-0 mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('reports.index') }}" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label for="from" class="form-label">Issued from</label>
                <input id="from" name="from" type="date" class="form-control" value="{{ $from }}" max="{{ today()->toDateString() }}" required>
            </div>
            <div class="col-md-4">
                <label for="to" class="form-label">Issued through</label>
                <input id="to" name="to" type="date" class="form-control" value="{{ $to }}" max="{{ today()->toDateString() }}" required>
            </div>
            <div class="col-md-4 d-grid">
                <button class="btn btn-outline-primary" type="submit">Update report</button>
            </div>
        </form>
        @if ($errors->any())
            <div class="alert alert-danger mt-3 mb-0"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif
    </div>
</div>

<div class="row g-3 mb-4">
    @foreach ([
        ['Loans issued in period', number_format($metrics['loans_issued']), 'primary'],
        ['Returned in period', number_format($metrics['loans_returned']), 'success'],
        ['Active loans', number_format($metrics['active_loans']), 'info'],
        ['Overdue now', number_format($metrics['overdue_loans']), 'danger'],
        ['Available copies', number_format($metrics['available_copies']), 'success'],
        ['Waiting reservations', number_format($metrics['waiting_reservations']), 'primary'],
        ['Pickup holds', number_format($metrics['ready_reservations']), 'warning'],
        ['Unpaid fines', 'PKR '.number_format($metrics['unpaid_fines_pkr']), 'danger'],
        ['Fines paid in period', 'PKR '.number_format($metrics['paid_fines_pkr']), 'success'],
    ] as [$label, $value, $color])
        <div class="col-sm-6 col-lg-4">
            <div class="card form-card border-0 h-100">
                <div class="card-body">
                    <div class="text-muted small">{{ $label }}</div>
                    <div class="fs-4 fw-bold text-{{ $color }}">{{ $value }}</div>
                </div>
            </div>
        </div>
    @endforeach
</div>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card form-card border-0 h-100">
            <div class="card-header bg-white"><strong>Loans issued by day</strong></div>
            <div class="card-body">
                @php($maxDailyLoans = max((int) $loanActivity->max('total'), 1))
                @forelse ($loanActivity as $day)
                    <div class="d-flex align-items-center gap-3 mb-2">
                        <span class="small text-muted" style="width: 6rem">{{ $day->day }}</span>
                        <div class="progress flex-grow-1" role="progressbar" aria-label="Loans issued on {{ $day->day }}" aria-valuenow="{{ $day->total }}" aria-valuemin="0" aria-valuemax="{{ $maxDailyLoans }}">
                            <div class="progress-bar" style="width: {{ ($day->total / $maxDailyLoans) * 100 }}%"></div>
                        </div>
                        <span class="small fw-semibold">{{ $day->total }}</span>
                    </div>
                @empty
                    <p class="text-muted mb-0">No loans were issued in this period.</p>
                @endforelse
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card form-card border-0 h-100">
            <div class="card-header bg-white"><strong>Most borrowed books</strong></div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light"><tr><th>Book</th><th>Author</th><th>Loans</th></tr></thead>
                    <tbody>
                        @forelse ($popularBooks as $book)
                            <tr><td>{{ $book->title }}</td><td>{{ $book->authors }}</td><td>{{ $book->loan_count }}</td></tr>
                        @empty
                            <tr><td colspan="3" class="text-center text-muted py-4">No loan activity in this period.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card form-card border-0 h-100">
            <div class="card-header bg-white"><strong>Most active members</strong></div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light"><tr><th>Member</th><th>Library card</th><th>Loans</th></tr></thead>
                    <tbody>
                        @forelse ($mostActiveMembers as $member)
                            <tr><td>{{ $member->name }}</td><td>{{ $member->library_card_number }}</td><td>{{ $member->loan_count }}</td></tr>
                        @empty
                            <tr><td colspan="3" class="text-center text-muted py-4">No member activity in this period.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
