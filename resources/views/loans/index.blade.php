@extends('layouts.app')

@section('title', 'Loan Management')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h4 class="fw-bold text-dark mb-1">Loan Management</h4>
        <p class="text-muted mb-0">Issue and return book copies; members can renew eligible loans from My Loans.</p>
    </div>
    <a href="{{ route('loans.create') }}" class="btn btn-primary btn-submit text-white">Issue a Book</a>
</div>

@if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if ($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

<div class="card form-card border-0 mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('loans.index') }}" class="row g-3 align-items-end">
            <div class="col-md-7">
                <label for="search" class="form-label">Search member, card, title, or barcode</label>
                <input id="search" name="search" class="form-control" value="{{ request('search') }}">
            </div>
            <div class="col-md-3">
                <label for="status" class="form-label">Loan status</label>
                <select id="status" name="status" class="form-select">
                    <option value="">All loans</option>
                    <option value="active" @selected(request('status') === 'active')>Active</option>
                    <option value="returned" @selected(request('status') === 'returned')>Returned</option>
                </select>
            </div>
            <div class="col-md-2 d-grid"><button class="btn btn-outline-primary">Filter</button></div>
        </form>
    </div>
</div>

<div class="card form-card border-0">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr><th>Member</th><th>Book / Barcode</th><th>Issued</th><th>Due</th><th>Status</th><th>Fine</th><th>Action</th></tr></thead>
            <tbody>
                @forelse ($loans as $loan)
                    <tr>
                        <td>{{ $loan->member->name }}<br><span class="text-muted small">{{ $loan->member->library_card_number }}</span></td>
                        <td>{{ $loan->copy->book->title }}<br><code class="small">{{ $loan->copy->barcode }}</code></td>
                        <td>
                            {{ $loan->issued_at->format('Y-m-d') }}
                            @if ($loan->issue_override_reason)
                                <details class="small mt-1">
                                    <summary class="text-warning-emphasis">Issue override</summary>
                                    <div class="text-muted mt-1">
                                        {{ $loan->issue_override_reason }}<br>
                                        By {{ $loan->issueOverrider?->name ?? 'Deleted administrator' }}
                                        at {{ $loan->issue_overridden_at?->format('Y-m-d H:i') }}
                                    </div>
                                </details>
                            @endif
                        </td>
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
                                @if ($loan->fine->adjustments->isNotEmpty())
                                    <details class="mt-1">
                                        <summary class="small">Adjustment history</summary>
                                        @foreach ($loan->fine->adjustments as $adjustment)
                                            <div class="small text-muted mt-1">
                                                {{ ucfirst($adjustment->action) }} by {{ $adjustment->admin?->name ?? 'Deleted admin' }}:
                                                PKR {{ number_format($adjustment->previous_amount_pkr) }} → PKR {{ number_format($adjustment->new_amount_pkr) }}.
                                                {{ $adjustment->reason }}
                                            </div>
                                        @endforeach
                                    </details>
                                @endif
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            @if ($loan->isActive())
                                <form method="POST" action="{{ route('loans.return', $loan) }}" class="d-flex flex-column gap-1" onsubmit="return confirm('Record this book return?')">
                                    @csrf
                                    @method('PATCH')
                                    <select name="condition" class="form-select form-select-sm" aria-label="Returned condition" required>
                                        <option value="good">Good condition</option>
                                        <option value="damaged" @selected($loan->damage_condition_note)>Damaged</option>
                                    </select>
                                    <textarea name="damage_condition_note" class="form-control form-control-sm" rows="2" minlength="5" maxlength="2000" placeholder="Condition notes (required if damaged)">{{ old('damage_condition_note') }}</textarea>
                                    <input name="damage_fee_pkr" type="number" min="0" max="1000000000" class="form-control form-control-sm" value="0" aria-label="Damage fee in PKR">
                                    <button class="btn btn-sm btn-outline-success" type="submit">Process return</button>
                                </form>
                            @endif
                            @if ($loan->damage_condition_note)
                                <div class="small text-danger mt-1">Damage: {{ $loan->damage_condition_note }}@if ($loan->damage_fee_pkr > 0) · PKR {{ number_format($loan->damage_fee_pkr) }} charge @endif</div>
                            @endif
                            @if ($loan->fine && ! $loan->fine->isSettled())
                                <form method="POST" action="{{ route('fines.pay', $loan->fine) }}" class="mt-1" onsubmit="return confirm('Record simulated payment of PKR {{ number_format($loan->fine->amount_pkr) }}?')">
                                    @csrf
                                    @method('PATCH')
                                    <button class="btn btn-sm btn-outline-primary" type="submit">Record payment</button>
                                </form>
                                <details class="mt-1">
                                    <summary class="small text-primary">Adjust / waive</summary>
                                    <form method="POST" action="{{ route('fines.adjust', $loan->fine) }}" class="mt-2">
                                        @csrf
                                        @method('PATCH')
                                        <select name="action" class="form-select form-select-sm mb-1" required>
                                            <option value="adjust">Adjust amount</option>
                                            <option value="waive">Waive fine</option>
                                        </select>
                                        <input name="amount_pkr" type="number" min="1" max="999999999999999" class="form-control form-control-sm mb-1" placeholder="New amount (PKR)">
                                        <textarea name="reason" class="form-control form-control-sm mb-1" rows="2" minlength="5" maxlength="2000" placeholder="Required reason" required></textarea>
                                        <button class="btn btn-sm btn-outline-warning" type="submit">Apply</button>
                                    </form>
                                </details>
                            @elseif (! $loan->isActive())
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">No loans found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-body">{{ $loans->links() }}</div>
</div>
@endsection
