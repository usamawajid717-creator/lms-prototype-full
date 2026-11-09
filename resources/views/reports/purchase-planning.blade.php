@extends('layouts.app')

@section('title', 'Purchase Planning')

@section('content')
<div class="mb-4">
    <h4 class="fw-bold mb-1">Purchase Planning</h4>
    <p class="text-muted mb-0">Prioritize titles using published course-list adoption, active member requests, waiting reservations, and loans issued in the last {{ $periodDays }} days. Score weights each adopted course list ×4, active request ×3, waiting reservation ×2, and recent loan ×1.</p>
</div>
<div class="card form-card border-0 mb-4">
    <div class="card-header bg-white border-bottom"><h5 class="fw-bold mb-0">Catalog demand</h5></div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr><th>Title</th><th>Available</th><th>Course lists</th><th>Requests</th><th>Waiting reservations</th><th>Loans · {{ $periodDays }}d</th><th>Priority score</th><th></th></tr></thead>
            <tbody>
            @forelse ($books as $book)
                <tr>
                    <td><strong>{{ $book->title }}</strong><div class="small text-muted">{{ $book->authors }} · {{ $book->category->name }}</div></td>
                    <td>
                        <span class="badge text-bg-{{ $book->available_copies > 0 ? 'success' : 'danger' }}">{{ $book->available_copies }} / {{ $book->total_copies }}</span>
                        @if ($book->is_unavailable_course_title)<div class="small text-danger fw-semibold mt-1">Required by courses · unavailable</div>@endif
                    </td>
                    <td>{{ $book->published_course_lists_count }}</td>
                    <td>{{ $book->active_requests_count }}</td>
                    <td>{{ $book->waiting_reservations_count }}</td>
                    <td>{{ $book->recent_loans_count }}</td>
                    <td><span class="badge text-bg-{{ $book->demand_score >= 5 ? 'danger' : ($book->demand_score > 0 ? 'warning' : 'secondary') }}">{{ $book->demand_score }}</span></td>
                    <td class="text-nowrap"><a href="{{ route('catalog.show', $book) }}" class="btn btn-sm btn-outline-secondary">Catalog</a><a href="{{ route('catalog.admin.copies', $book) }}" class="btn btn-sm btn-outline-primary ms-1">Copies</a></td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-muted py-4">No catalog books found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="card form-card border-0">
    <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
        <div><h5 class="fw-bold mb-1">Unfulfilled member requests</h5><p class="small text-muted mb-0">Titles not yet linked to an acquired catalog book.</p></div>
        <a href="{{ route('book-requests.admin') }}" class="btn btn-sm btn-outline-primary">Manage requests</a>
    </div>
    <div class="table-responsive"><table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr><th>Requested title</th><th>Requested by</th><th>Status</th><th>Supplier / order details</th></tr></thead>
        <tbody>
        @forelse ($activeRequests as $request)
            <tr>
                <td><strong>{{ $request->title }}</strong><div class="small text-muted">{{ $request->authors ?: 'Author not specified' }}@if ($request->isbn) · {{ $request->isbn }}@endif</div></td>
                <td>{{ $request->member->name }}</td>
                <td><span class="badge text-bg-{{ ['pending' => 'warning', 'approved' => 'primary', 'ordered' => 'info', 'received' => 'success'][$request->status] }}">{{ ucfirst($request->status) }}</span></td>
                <td>{{ $request->supplier ?: 'Not ordered' }}@if ($request->ordered_at) · {{ $request->ordered_at->format('M j, Y') }}@endif</td>
            </tr>
        @empty
            <tr><td colspan="4" class="text-center text-muted py-4">No active member requests.</td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>
@endsection
