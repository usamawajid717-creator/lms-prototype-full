@extends('layouts.app')

@section('title', 'My Library')

@push('styles')
<style>
    .member-hero {
        position: relative;
        overflow: hidden;
        border: 0;
        border-radius: 1.25rem;
        color: #fff;
        background: linear-gradient(120deg, #163f67, #247da0);
        box-shadow: 0 14px 34px rgba(31, 97, 141, 0.18);
    }

    .member-card {
        border: 1px solid #e8edf3;
        border-radius: 1rem;
        background: #fff;
        box-shadow: 0 6px 20px rgba(15, 23, 42, 0.045);
    }

    .member-stat-icon {
        display: grid;
        width: 2.8rem;
        height: 2.8rem;
        flex: 0 0 2.8rem;
        place-items: center;
        border-radius: 0.85rem;
        font-size: 1.25rem;
    }

    .member-stat-value {
        color: #172b4d;
        font-size: 1.6rem;
        font-weight: 750;
        letter-spacing: -0.04em;
        line-height: 1.1;
    }

    .member-panel {
        overflow: hidden;
        border: 1px solid #e8edf3;
        border-radius: 1rem;
        background: #fff;
        box-shadow: 0 6px 20px rgba(15, 23, 42, 0.045);
    }

    .member-panel-header {
        border-bottom: 1px solid #edf1f5;
        padding: 1.1rem 1.25rem;
    }
</style>
@endpush

@section('content')
<section class="member-hero p-4 p-lg-5 mb-4">
    <div class="row align-items-center position-relative" style="z-index: 1;">
        <div class="col-lg">
            <div class="small text-uppercase fw-semibold opacity-75 mb-2">Your library</div>
            <h1 class="h2 fw-bold mb-2">Welcome, {{ $member->name }}</h1>
            <p class="mb-0 opacity-75">Keep track of your books, reservations, and membership in one place.</p>
        </div>
        <div class="col-lg-auto mt-4 mt-lg-0">
            <div class="small opacity-75 mb-2">Library card</div>
            <div class="h5 fw-semibold mb-3">{{ $member->library_card_number ?: 'Not assigned' }}</div>
            <a href="{{ route('catalog.index') }}" class="btn btn-light fw-semibold">
                <i class="bi bi-search me-1"></i>Browse catalog
            </a>
        </div>
    </div>
</section>

@if ($member->effective_membership_status !== 'active')
    <div class="alert alert-warning d-flex align-items-start gap-2">
        <i class="bi bi-exclamation-triangle-fill mt-1"></i>
        <div>Your membership is {{ $member->effective_membership_status }}. Please contact library staff for help with borrowing or reservations.</div>
    </div>
@endif

@if ($metrics['ready_reservations'])
    <div class="alert alert-success d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div><i class="bi bi-bell-fill me-2"></i><strong>You have {{ $metrics['ready_reservations'] }} book{{ $metrics['ready_reservations'] === 1 ? '' : 's' }} ready for pickup.</strong> Check the reservation details for the barcode and deadline.</div>
        <a href="{{ route('reservations.mine') }}" class="alert-link text-nowrap">View reservation</a>
    </div>
@endif

@if ($metrics['outstanding_fines_pkr'] > 0)
    <div class="alert alert-warning d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div><i class="bi bi-cash-coin me-2"></i>You have <strong>PKR {{ number_format($metrics['outstanding_fines_pkr']) }}</strong> in unpaid fines. Contact library staff to record payment.</div>
        <a href="{{ route('loans.mine') }}" class="alert-link text-nowrap">View loan details</a>
    </div>
@endif

<div class="d-flex align-items-center justify-content-between mb-3">
    <div>
        <h2 class="h5 fw-bold mb-1">Your activity</h2>
        <p class="small text-muted mb-0">A quick look at your current library account.</p>
    </div>
</div>

<div class="row g-3 mb-4">
    @foreach ([
        ['Active loans', number_format($metrics['active_loans']), 'bi-journal-arrow-up', 'primary', 'bg-primary-subtle'],
        ['Overdue loans', number_format($metrics['overdue_loans']), 'bi-exclamation-circle', 'danger', 'bg-danger-subtle'],
        ['Ready for pickup', number_format($metrics['ready_reservations']), 'bi-bell', 'success', 'bg-success-subtle'],
        ['Waiting reservations', number_format($metrics['waiting_reservations']), 'bi-hourglass-split', 'warning', 'bg-warning-subtle'],
        ['Unpaid fines', 'PKR '.number_format($metrics['outstanding_fines_pkr']), 'bi-cash-coin', 'danger', 'bg-danger-subtle'],
    ] as [$label, $value, $icon, $color, $background])
        <div class="col-6 col-lg-4 col-xxl">
            <div class="member-card h-100 p-3 p-xl-4">
                <div class="d-flex align-items-center gap-3">
                    <span class="member-stat-icon {{ $background }} text-{{ $color }}"><i class="bi {{ $icon }}"></i></span>
                    <div class="min-w-0">
                        <div class="small text-muted mb-1">{{ $label }}</div>
                        <div class="member-stat-value">{{ $value }}</div>
                    </div>
                </div>
            </div>
        </div>
    @endforeach
</div>

<div class="row g-4">
    <div class="col-xl-7">
        <section class="member-panel h-100">
            <div class="member-panel-header d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="h6 fw-bold mb-1">Books on loan</h2>
                    <p class="small text-muted mb-0">Your current loans and due dates</p>
                </div>
                <a href="{{ route('loans.mine') }}" class="btn btn-sm btn-outline-primary">All loans</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light"><tr><th class="ps-4">Book</th><th>Due date</th><th>Status</th></tr></thead>
                    <tbody>
                        @forelse ($activeLoans as $loan)
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-semibold">{{ $loan->copy->book->title }}</div>
                                    <code class="small">{{ $loan->copy->barcode }}</code>
                                </td>
                                <td class="text-nowrap">{{ $loan->due_at->format('M j, Y') }}</td>
                                <td>
                                    @if ($loan->due_at->isBefore(today()))
                                        <span class="badge rounded-pill bg-danger-subtle text-danger">Overdue</span>
                                    @else
                                        <span class="badge rounded-pill bg-primary-subtle text-primary">Active</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-center text-muted py-5"><i class="bi bi-journal-x fs-3 d-block mb-2"></i>No active loans. Browse the catalog to find your next read.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <div class="col-xl-5">
        <section class="member-panel h-100">
            <div class="member-panel-header d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="h6 fw-bold mb-1">Reservations</h2>
                    <p class="small text-muted mb-0">Pickup holds and waitlist status</p>
                </div>
                <a href="{{ route('reservations.mine') }}" class="btn btn-sm btn-outline-primary">All reservations</a>
            </div>
            <div class="list-group list-group-flush">
                @forelse ($activeReservations as $reservation)
                    <div class="list-group-item px-4 py-3">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <div>
                                <div class="fw-semibold">{{ $reservation->book->title }}</div>
                                <div class="small text-muted">{{ $reservation->book->authors }}</div>
                                @if ($reservation->status === 'ready')
                                    <div class="small text-success mt-2">
                                        @if ($reservation->copy)
                                            Barcode: <code>{{ $reservation->copy->barcode }}</code><br>
                                        @endif
                                        Collect by {{ $reservation->hold_expires_at?->format('M j, Y g:i A') }}
                                    </div>
                                @else
                                    <div class="small text-muted mt-2">You are on the waiting list.</div>
                                @endif
                            </div>
                            <span class="badge rounded-pill {{ $reservation->status === 'ready' ? 'bg-success-subtle text-success' : 'bg-primary-subtle text-primary' }}">{{ ucfirst($reservation->status) }}</span>
                        </div>
                    </div>
                @empty
                    <div class="text-center text-muted py-5 px-3"><i class="bi bi-bookmark-x fs-3 d-block mb-2"></i>No active reservations.</div>
                @endforelse
            </div>
        </section>
    </div>

    <div class="col-lg-7">
        <section class="member-panel h-100">
            <div class="member-panel-header d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="h6 fw-bold mb-1">Unpaid fines</h2>
                    <p class="small text-muted mb-0">Outstanding items on your account</p>
                </div>
                <a href="{{ route('loans.mine') }}" class="btn btn-sm btn-outline-primary">Loan history</a>
            </div>
            <div class="list-group list-group-flush">
                @forelse ($unpaidFines as $fine)
                    <div class="list-group-item px-4 py-3 d-flex justify-content-between align-items-center gap-3">
                        <div>
                            <div class="fw-semibold">{{ $fine->loan->copy->book->title }}</div>
                            <div class="small text-muted">Assessed {{ $fine->created_at->format('M j, Y') }}</div>
                        </div>
                        <span class="fw-bold text-danger text-nowrap">PKR {{ number_format($fine->amount_pkr) }}</span>
                    </div>
                @empty
                    <div class="text-center text-muted py-5 px-3"><i class="bi bi-check-circle fs-3 d-block mb-2"></i>You have no unpaid fines.</div>
                @endforelse
            </div>
        </section>
    </div>

    <div class="col-lg-5">
        <section class="member-panel h-100">
            <div class="member-panel-header">
                <h2 class="h6 fw-bold mb-1">Membership</h2>
                <p class="small text-muted mb-0">Your account information</p>
            </div>
            <div class="p-4">
                <div class="d-flex justify-content-between py-2 border-bottom">
                    <span class="text-muted">Status</span>
                    <span class="badge rounded-pill {{ $member->effective_membership_status === 'active' ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning' }}">{{ ucfirst($member->effective_membership_status) }}</span>
                </div>
                <div class="d-flex justify-content-between py-2 border-bottom">
                    <span class="text-muted">Type</span>
                    <span class="fw-semibold">{{ $member->membership_type ?: 'Member' }}</span>
                </div>
                <div class="d-flex justify-content-between py-2 border-bottom">
                    <span class="text-muted">Expires</span>
                    <span class="fw-semibold">{{ $member->membership_expires_at?->format('M j, Y') ?: 'No expiry set' }}</span>
                </div>
                <div class="d-flex justify-content-between py-2">
                    <span class="text-muted">Unread notifications</span>
                    <span class="fw-semibold">{{ number_format($metrics['unread_notifications']) }}</span>
                </div>
                <a href="{{ route('profile.edit') }}" class="btn btn-outline-primary w-100 mt-3">Manage my profile</a>
            </div>
        </section>
    </div>
</div>
@endsection
