@extends('layouts.app')

@section('title', 'Statistics Dashboard')

@push('styles')
<style>
    .dashboard-hero {
        position: relative;
        overflow: hidden;
        border: 0;
        border-radius: 1.25rem;
        color: #fff;
        background: linear-gradient(120deg, var(--library-forest-dark), var(--library-forest));
        box-shadow: 0 14px 34px rgba(29, 57, 47, 0.18);
    }

    .dashboard-hero::after {
        position: absolute;
        top: -5rem;
        right: -2rem;
        width: 18rem;
        height: 18rem;
        border: 1px solid rgba(255, 255, 255, 0.12);
        border-radius: 50%;
        box-shadow: 0 0 0 2rem rgba(255, 255, 255, 0.04), 0 0 0 4rem rgba(255, 255, 255, 0.03);
        content: "";
        pointer-events: none;
    }

    .dashboard-stat {
        position: relative;
        overflow: hidden;
        border: 1px solid var(--library-line);
        border-radius: 1rem;
        background: var(--library-surface);
        box-shadow: 0 6px 20px rgba(58, 46, 31, 0.045);
        transition: transform 160ms ease, box-shadow 160ms ease;
    }

    .dashboard-stat:hover {
        transform: translateY(-2px);
        box-shadow: 0 12px 26px rgba(58, 46, 31, 0.09);
    }

    .dashboard-stat-icon {
        display: grid;
        width: 2.8rem;
        height: 2.8rem;
        flex: 0 0 2.8rem;
        place-items: center;
        border-radius: 0.85rem;
        font-size: 1.25rem;
    }

    .dashboard-stat-value {
        color: var(--library-ink);
        font-size: clamp(1.35rem, 2vw, 1.8rem);
        font-weight: 750;
        letter-spacing: -0.04em;
        line-height: 1.1;
    }

    .dashboard-panel {
        overflow: hidden;
        border: 1px solid var(--library-line);
        border-radius: 1rem;
        background: var(--library-surface);
        box-shadow: 0 6px 20px rgba(58, 46, 31, 0.045);
    }

    .dashboard-panel-header {
        border-bottom: 1px solid var(--library-line);
        padding: 1.1rem 1.25rem;
    }

    .activity-chart {
        display: grid;
        min-height: 13rem;
        grid-template-columns: repeat(14, minmax(2rem, 1fr));
        align-items: end;
        gap: 0.55rem;
        overflow-x: auto;
        padding: 1.1rem 0.25rem 0;
    }

    .activity-day {
        display: flex;
        min-width: 2rem;
        height: 11rem;
        flex-direction: column;
        align-items: center;
        justify-content: flex-end;
        gap: 0.55rem;
    }

    .activity-bar-track {
        display: flex;
        width: 100%;
        height: 8.5rem;
        align-items: flex-end;
        justify-content: center;
        border-radius: 0.45rem;
        background: #f1eadd;
    }

    .activity-bar {
        width: min(1.2rem, 70%);
        min-height: 0.25rem;
        border-radius: 0.4rem 0.4rem 0.15rem 0.15rem;
        background: linear-gradient(180deg, #c99d55, var(--library-terracotta));
    }

    .dashboard-table th {
        color: var(--library-muted);
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.055em;
        text-transform: uppercase;
    }

    .dashboard-table td,
    .dashboard-table th {
        padding: 0.9rem 1.1rem;
    }

    @media (prefers-reduced-motion: reduce) {
        .dashboard-stat {
            transition: none;
        }

        .dashboard-stat:hover {
            transform: none;
        }
    }
</style>
@endpush

@section('content')
<section class="dashboard-hero p-4 p-lg-5 mb-4">
    <div class="row align-items-center position-relative" style="z-index: 1;">
        <div class="col-lg">
            <div class="text-uppercase small fw-semibold opacity-75 mb-2">Library overview</div>
            <h1 class="h2 fw-bold mb-2">Statistics dashboard</h1>
            <p class="mb-0 opacity-75">A real-time snapshot of your collection, circulation, and member activity.</p>
        </div>
        <div class="col-lg-auto mt-4 mt-lg-0">
            <div class="small opacity-75 mb-2">{{ today()->format('l, F j, Y') }}</div>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('loans.create') }}" class="btn btn-light fw-semibold">
                    <i class="bi bi-plus-circle me-1"></i>Issue a loan
                </a>
                <a href="{{ route('reports.index') }}" class="btn btn-outline-light">
                    <i class="bi bi-bar-chart-line me-1"></i>View reports
                </a>
            </div>
        </div>
    </div>
</section>

<div class="d-flex align-items-center justify-content-between mb-3">
    <div>
        <h2 class="h5 fw-bold mb-1">At a glance</h2>
        <p class="small text-muted mb-0">Current totals and items that may need attention.</p>
    </div>
    <a href="{{ route('reports.fines') }}" class="small fw-semibold text-decoration-none">View fine report <i class="bi bi-arrow-right"></i></a>
</div>

<div class="row g-3 mb-4">
    @foreach ([
        ['Book titles', number_format($metrics['books']), 'bi-book', 'primary', 'bg-primary-subtle'],
        ['Members', number_format($metrics['members']), 'bi-people', 'info', 'bg-info-subtle'],
        ['Available copies', number_format($metrics['available_copies']), 'bi-bookmark-check', 'success', 'bg-success-subtle'],
        ['Active loans', number_format($metrics['active_loans']), 'bi-journal-arrow-up', 'primary', 'bg-primary-subtle'],
        ['Overdue loans', number_format($metrics['overdue_loans']), 'bi-exclamation-circle', 'danger', 'bg-danger-subtle'],
    ] as [$label, $value, $icon, $color, $background])
        <div class="col-6 col-lg-4 col-xxl">
            <div class="dashboard-stat h-100 p-3 p-xl-4">
                <div class="d-flex align-items-center gap-3">
                    <span class="dashboard-stat-icon {{ $background }} text-{{ $color }}"><i class="bi {{ $icon }}"></i></span>
                    <div class="min-w-0">
                        <div class="small text-muted mb-1">{{ $label }}</div>
                        <div class="dashboard-stat-value">{{ $value }}</div>
                    </div>
                </div>
            </div>
        </div>
    @endforeach
</div>

<div class="row g-3 mb-4">
    @foreach ([
        ['Physical copies', number_format($metrics['copies']), 'bi-collection', 'secondary'],
        ['Returned this month', number_format($metrics['returned_this_month']), 'bi-journal-check', 'success'],
        ['Waiting reservations', number_format($metrics['waiting_reservations']), 'bi-hourglass-split', 'warning'],
        ['Ready for pickup', number_format($metrics['ready_reservations']), 'bi-bell', 'warning'],
        ['Outstanding fines', 'PKR '.number_format($metrics['outstanding_fines_pkr']), 'bi-cash-coin', 'danger'],
    ] as [$label, $value, $icon, $color])
        <div class="col-6 col-lg-4 col-xxl">
            <div class="dashboard-stat h-100 px-3 py-3">
                <div class="small text-muted mb-1"><i class="bi {{ $icon }} text-{{ $color }} me-1"></i>{{ $label }}</div>
                <div class="fs-5 fw-bold text-dark">{{ $value }}</div>
            </div>
        </div>
    @endforeach
</div>

<div class="row g-4">
    <div class="col-xl-5">
        <section class="dashboard-panel h-100">
            <div class="dashboard-panel-header d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="h6 fw-bold mb-1">Circulation trend</h2>
                    <p class="small text-muted mb-0">Loans issued over the last 14 days</p>
                </div>
                <span class="badge rounded-pill bg-primary-subtle text-primary">14 days</span>
            </div>
            <div class="p-3 p-lg-4">
                @php($maxDailyLoans = max((int) $loanActivity->max('total'), 1))
                <div class="activity-chart" role="img" aria-label="Daily loans issued over the last 14 days">
                    @foreach ($loanActivity as $day)
                        <div class="activity-day" title="{{ $day->day }}: {{ $day->total }} loans">
                            <span class="small fw-semibold text-secondary">{{ $day->total ?: '' }}</span>
                            <div class="activity-bar-track">
                                @if ($day->total)
                                    <div class="activity-bar" style="height: {{ max(($day->total / $maxDailyLoans) * 100, 5) }}%"></div>
                                @endif
                            </div>
                            <span class="small text-muted">{{ $day->day }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    </div>

    <div class="col-xl-7">
        <section class="dashboard-panel h-100">
            <div class="dashboard-panel-header d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="h6 fw-bold mb-1">Recent loans</h2>
                    <p class="small text-muted mb-0">Latest circulation activity</p>
                </div>
                <a href="{{ route('loans.index') }}" class="btn btn-sm btn-outline-primary">All loans</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle dashboard-table mb-0">
                    <thead class="bg-light"><tr><th>Book and member</th><th>Issued</th><th>Due</th><th>Status</th></tr></thead>
                    <tbody>
                        @forelse ($recentLoans as $loan)
                            <tr>
                                <td>
                                    <div class="fw-semibold text-dark">{{ $loan->book_title }}</div>
                                    <div class="small text-muted">{{ $loan->member_name }}</div>
                                </td>
                                <td class="text-nowrap">{{ \Illuminate\Support\Carbon::parse($loan->issued_at)->toDateString() }}</td>
                                <td class="text-nowrap">{{ \Illuminate\Support\Carbon::parse($loan->due_at)->toDateString() }}</td>
                                <td>
                                    @if ($loan->returned_at)
                                        <span class="badge rounded-pill bg-success-subtle text-success">Returned</span>
                                    @elseif (\Illuminate\Support\Carbon::parse($loan->due_at)->isBefore(today()))
                                        <span class="badge rounded-pill bg-danger-subtle text-danger">Overdue</span>
                                    @else
                                        <span class="badge rounded-pill bg-primary-subtle text-primary">Active</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted py-5"><i class="bi bi-journal-x fs-3 d-block mb-2"></i>No loans have been issued yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>
@endsection
