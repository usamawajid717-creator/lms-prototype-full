@extends('layouts.app')

@section('title', 'Inventory Audits')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div><h4 class="fw-bold mb-1">Barcode Inventory Audits</h4><p class="text-muted mb-0">Scan physical copies, then review missing and unexpected inventory.</p></div>
    <form method="POST" action="{{ route('inventory-audits.store') }}" class="d-flex gap-2">
        @csrf
        <input name="name" class="form-control" maxlength="150" placeholder="Audit name" aria-label="Audit name" required>
        <button class="btn btn-primary btn-submit text-white text-nowrap" type="submit">Start audit</button>
    </form>
</div>
@if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if ($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<div class="card form-card border-0"><div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr><th>Audit</th><th>Started by</th><th>Started</th><th>Status</th><th>Expected / scanned</th><th>Unexpected</th></tr></thead>
        <tbody>
        @forelse ($audits as $audit)
            <tr>
                <td><a href="{{ route('inventory-audits.show', $audit) }}" class="fw-semibold">{{ $audit->name }}</a></td>
                <td>{{ $audit->starter->name }}</td>
                <td>{{ $audit->started_at->format('M j, Y g:i A') }}</td>
                <td><span class="badge text-bg-{{ $audit->status === 'open' ? 'primary' : 'success' }}">{{ ucfirst($audit->status) }}</span></td>
                <td>{{ $audit->expected_count }} / {{ $audit->scanned_count }}</td>
                <td>{{ $audit->unexpected_count }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="text-center text-muted py-4">No inventory audits yet. Start an audit to snapshot current shelf copies.</td></tr>
        @endforelse
        </tbody>
    </table>
</div><div class="card-body">{{ $audits->links() }}</div></div>
@endsection
