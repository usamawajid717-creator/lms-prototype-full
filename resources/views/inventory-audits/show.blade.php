@extends('layouts.app')

@section('title', $audit->name.' — Inventory Audit')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <a href="{{ route('inventory-audits.index') }}" class="text-decoration-none">&larr; All audits</a>
        <h4 class="fw-bold mt-2 mb-1">{{ $audit->name }}</h4>
        <p class="text-muted mb-0">Started by {{ $audit->starter->name }} on {{ $audit->started_at->format('M j, Y g:i A') }} · {{ ucfirst($audit->status) }}</p>
    </div>
    @if ($audit->status === 'open')
        <form method="POST" action="{{ route('inventory-audits.complete', $audit) }}" onsubmit="return confirm('Stop scanning and finalize this audit? Unscanned shelf copies will be listed as missing for review.')">
            @csrf @method('PATCH')
            <button type="submit" class="btn btn-outline-success">Complete audit</button>
        </form>
    @endif
</div>
@if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if ($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<div class="row g-3 mb-4">
    @foreach ([
        ['Expected shelf copies', $expectedCount, 'primary'],
        ['Scanned / found', $scannedCount, 'success'],
        ['Missing to review', $missingCount, 'danger'],
        ['Unexpected scans', $unexpectedCount, 'warning'],
    ] as [$label, $count, $color])
        <div class="col-6 col-xl-3"><div class="card form-card border-0 h-100"><div class="card-body"><div class="small text-muted">{{ $label }}</div><div class="fs-3 fw-bold text-{{ $color }}">{{ $count }}</div></div></div></div>
    @endforeach
</div>
@if ($audit->status === 'open')
    <div class="card form-card border-0 mb-4"><div class="card-body p-4">
        <h5 class="fw-bold">Scan a barcode</h5>
        <p class="small text-muted">Use a USB barcode scanner (keyboard mode) or type a barcode. Copies on loan or reserved are not part of the shelf snapshot.</p>
        <form method="POST" action="{{ route('inventory-audits.scan', $audit) }}" class="d-flex flex-wrap gap-2">
            @csrf
            <input id="audit-barcode" name="barcode" class="form-control form-control-lg flex-grow-1" style="max-width: 32rem" autocomplete="off" autofocus placeholder="Scan or enter barcode" maxlength="40" required>
            <button class="btn btn-primary btn-submit text-white" type="submit">Record scan</button>
        </form>
    </div></div>
@endif
<div class="card form-card border-0">
    <div class="card-header bg-white border-bottom"><h5 class="mb-0">Audit copy details</h5><p class="small text-muted mb-0">Unscanned expected copies become missing candidates after completion. Confirm before marking any copy lost.</p></div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr><th>Barcode</th><th>Book</th><th>Expected status</th><th>Audit result</th><th>Action</th></tr></thead>
            <tbody>
            @forelse ($items as $item)
                @php
                    $result = ! $item->expected ? 'Unexpected scan' : ($item->scanned_at ? 'Found' : ($audit->status === 'completed' ? 'Missing' : 'Not scanned'));
                @endphp
                <tr>
                    <td><code>{{ $item->barcode }}</code></td>
                    <td>{{ $item->book_title ?: 'Unregistered barcode' }}@if ($item->copy_number) <span class="text-muted">· Copy {{ $item->copy_number }}</span>@endif</td>
                    <td>{{ $item->expected_status ? ucfirst(str_replace('_', ' ', $item->expected_status)) : 'Not in snapshot' }}</td>
                    <td>
                        <span class="badge text-bg-{{ $result === 'Found' ? 'success' : ($result === 'Missing' ? 'danger' : ($result === 'Unexpected scan' ? 'warning' : 'secondary')) }}">{{ $result }}</span>
                        @if ($item->scanned_at)<div class="small text-muted">{{ $item->scanned_at->format('M j, g:i A') }}@if ($item->scanner) · {{ $item->scanner->name }}@endif</div>@endif
                        @if ($item->reconciled_at)<div class="small text-success">Marked lost {{ $item->reconciled_at->format('M j, Y') }}@if ($item->reconciler) · {{ $item->reconciler->name }}@endif</div>@endif
                    </td>
                    <td>
                        @if ($audit->status === 'completed' && $item->expected && ! $item->scanned_at && ! $item->reconciled_at && in_array($item->copy?->status, ['available', 'unavailable'], true))
                            <form method="POST" action="{{ route('inventory-audits.items.lost', [$audit, $item]) }}" onsubmit="return confirm('Mark this copy as lost? Its availability count will be adjusted.')">
                                @csrf @method('PATCH')
                                <button class="btn btn-sm btn-outline-danger" type="submit">Confirm lost</button>
                            </form>
                        @elseif ($audit->status === 'completed' && $item->expected && ! $item->scanned_at && ! $item->reconciled_at)
                            <span class="text-muted small">Current status: {{ $item->copy?->status ? str_replace('_', ' ', $item->copy->status) : 'record unavailable' }}</span>
                        @elseif ($item->reconciled_at)
                            <span class="text-muted small">Reconciled</span>
                        @else
                            <span class="text-muted small">—</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center text-muted py-4">This audit has no inventory records.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-body">{{ $items->links() }}</div>
</div>
@endsection
