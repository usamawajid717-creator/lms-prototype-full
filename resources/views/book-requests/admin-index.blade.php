@extends('layouts.app')

@section('title', 'Book Requests — Administration')

@section('content')
<div class="mb-4"><h4 class="fw-bold mb-1">Member Book Requests</h4><p class="text-muted mb-0">Review suggestions and track approved books from ordering through catalog acquisition.</p></div>
@if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if ($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<div class="card form-card border-0"><div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr><th>Requested book</th><th>Member</th><th>Status</th><th>Admin action</th></tr></thead>
        <tbody>
        @forelse ($requests as $item)
            <tr>
                <td><strong>{{ $item->title }}</strong><div class="small text-muted">{{ $item->authors ?: 'Author not specified' }}@if ($item->isbn) · ISBN {{ $item->isbn }}@endif</div>@if ($item->notes)<div class="small mt-1">{{ $item->notes }}</div>@endif</td>
                <td>{{ $item->member->name }}<div class="small text-muted">{{ $item->member->email }}</div></td>
                <td>
                    <span class="badge text-bg-{{ ['pending' => 'warning', 'approved' => 'primary', 'ordered' => 'info', 'received' => 'success', 'declined' => 'danger', 'fulfilled' => 'success'][$item->status] }}">{{ ucfirst($item->status) }}</span>
                    @if ($item->supplier)<div class="small mt-1">Supplier: {{ $item->supplier }}</div>@endif
                    @if ($item->ordered_at)<div class="small">Ordered: {{ $item->ordered_at->format('M j, Y') }}</div>@endif
                    @if ($item->received_at)<div class="small">Received: {{ $item->received_at->format('M j, Y') }}</div>@endif
                    @if ($item->book)<div class="small mt-1">{{ $item->book->title }}</div>@endif
                </td>
                <td style="min-width: 260px">
                    @if ($item->status === 'pending')
                        <form method="POST" action="{{ route('book-requests.update', $item) }}" class="mb-2">
                            @csrf @method('PATCH')<input type="hidden" name="status" value="approved">
                            <button class="btn btn-sm btn-outline-primary" type="submit">Approve</button>
                        </form>
                        <form method="POST" action="{{ route('book-requests.update', $item) }}">
                            @csrf @method('PATCH')<input type="hidden" name="status" value="declined">
                            <input name="reason" class="form-control form-control-sm mb-2" placeholder="Reason required to decline" minlength="5" maxlength="2000" required>
                            <button class="btn btn-sm btn-outline-danger" type="submit">Decline</button>
                        </form>
                    @elseif ($item->status === 'approved')
                        <form method="POST" action="{{ route('book-requests.update', $item) }}" class="mb-2">
                            @csrf @method('PATCH')<input type="hidden" name="status" value="ordered">
                            <input name="supplier" class="form-control form-control-sm mb-2" placeholder="Supplier" maxlength="255" required>
                            <label class="form-label small mb-1" for="ordered-at-{{ $item->id }}">Order date</label>
                            <input id="ordered-at-{{ $item->id }}" name="ordered_at" type="date" class="form-control form-control-sm mb-2" value="{{ today()->toDateString() }}" max="{{ today()->toDateString() }}" required>
                            <button class="btn btn-sm btn-outline-primary" type="submit">Mark ordered</button>
                        </form>
                    @elseif ($item->status === 'ordered')
                        <form method="POST" action="{{ route('book-requests.update', $item) }}">
                            @csrf @method('PATCH')<input type="hidden" name="status" value="received">
                            <button class="btn btn-sm btn-outline-success" type="submit">Mark received</button>
                        </form>
                    @elseif ($item->status === 'received')
                        <form method="POST" action="{{ route('book-requests.update', $item) }}">
                            @csrf @method('PATCH')<input type="hidden" name="status" value="fulfilled">
                            <select name="book_id" class="form-select form-select-sm mb-2" required>
                                <option value="">Select acquired catalog book</option>
                                @foreach ($books as $book)<option value="{{ $book->id }}">{{ $book->title }} ({{ $book->isbn }})</option>@endforeach
                            </select>
                            <button class="btn btn-sm btn-outline-success" type="submit">Add to catalog / fulfill</button>
                        </form>
                    @else
                        <span class="text-muted small">Reviewed {{ $item->reviewed_at?->format('M j, Y') }}</span>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="4" class="text-center text-muted py-4">No book requests have been submitted.</td></tr>
        @endforelse
        </tbody>
    </table>
</div><div class="card-body">{{ $requests->links() }}</div></div>
@endsection
