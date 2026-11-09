@extends('layouts.app')

@section('title', 'My Loans')

@section('content')
<div class="mb-4">
    <h4 class="fw-bold text-dark mb-1">My Loans</h4>
    <p class="text-muted mb-0">View current and past loans. Eligible active loans may be renewed up to {{ $policy->max_renewals }} times for {{ $policy->loan_period_days }} days each time.</p>
</div>

@if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if ($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
@if ($hasUnpaidFines)
    <div class="alert alert-warning">You have an unpaid fine. Please contact library staff to record payment before renewing a loan.</div>
@endif

<div class="card form-card border-0">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr><th>Book / Barcode</th><th>Issued</th><th>Due</th><th>Status</th><th>Fine</th><th>Renewal</th></tr></thead>
            <tbody>
                @forelse ($loans as $loan)
                    <tr>
                        <td>{{ $loan->copy->book->title }}<br><code class="small">{{ $loan->copy->barcode }}</code></td>
                        <td>{{ $loan->issued_at->format('Y-m-d') }}</td>
                        <td>{{ $loan->due_at->format('Y-m-d') }}</td>
                        <td>
                            @if ($loan->isActive())
                                <span class="badge {{ $loan->due_at->isBefore(today()) ? 'text-bg-danger' : 'text-bg-primary' }}">{{ $loan->due_at->isBefore(today()) ? 'Overdue' : 'Active' }}</span>
                            @else
                                <span class="badge text-bg-secondary">Returned</span>
                            @endif
                        </td>
                        <td>
                            @if ($loan->fine)
                                PKR {{ number_format($loan->fine->amount_pkr) }}<br>
                                <span class="badge {{ $loan->fine->isSettled() ? 'text-bg-success' : 'text-bg-danger' }}">{{ $loan->fine->waived_at ? 'Waived' : ($loan->fine->paid_at ? 'Paid' : 'Unpaid') }}</span>
                                @if ($loan->damage_fee_pkr > 0)
                                    <div class="small text-muted">Includes damage charge: PKR {{ number_format($loan->damage_fee_pkr) }}</div>
                                @endif
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            @if ($loan->isActive() && $loan->renewal_count < $policy->max_renewals && ! $loan->due_at->isBefore(today()) && ! $hasUnpaidFines)
                                <form method="POST" action="{{ route('loans.renew', $loan) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button class="btn btn-sm btn-outline-primary" type="submit">Renew {{ $policy->loan_period_days }} days</button>
                                </form>
                            @elseif ($loan->renewal_count >= $policy->max_renewals)
                                <span class="text-muted">Renewal limit reached</span>
                            @else
                                <span class="text-muted">Not eligible</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">You have no loans.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-body">{{ $loans->links() }}</div>
</div>
@endsection
