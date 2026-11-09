@extends('layouts.app')

@section('title', 'Book Copies')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h4 class="fw-bold text-dark mb-1">Copies: {{ $book->title }}</h4>
        <p class="text-muted mb-0">Each copy has a unique barcode for issue and return.</p>
    </div>
    <a href="{{ route('catalog.admin.index') }}" class="btn btn-outline-secondary">Back to Catalog</a>
</div>

@if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if ($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

<div class="card form-card border-0">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr><th>Barcode</th><th>Copy #</th><th>Status</th><th>Update status</th></tr></thead>
            <tbody>
                @forelse ($copies as $copy)
                    <tr>
                        <td><code>{{ $copy->barcode }}</code></td>
                        <td>{{ $copy->copy_number }}</td>
                        <td><span class="badge {{ $copy->status === 'available' ? 'text-bg-success' : (in_array($copy->status, ['on_loan', 'reserved'], true) ? 'text-bg-warning' : 'text-bg-secondary') }}">{{ ucfirst(str_replace('_', ' ', $copy->status)) }}</span></td>
                        <td>
                            @if (! in_array($copy->status, ['on_loan', 'reserved', 'retired'], true))
                                <form method="POST" action="{{ route('catalog.admin.copies.update', [$book, $copy]) }}" class="d-flex gap-2">
                                    @csrf
                                    @method('PATCH')
                                    <select name="status" class="form-select form-select-sm" aria-label="Status for {{ $copy->barcode }}">
                                        @foreach (['available' => 'Available', 'unavailable' => 'Unavailable', 'lost' => 'Lost'] as $value => $label)
                                            <option value="{{ $value }}" @selected($copy->status === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <button class="btn btn-sm btn-outline-primary" type="submit">Save</button>
                                </form>
                            @else
                                <span class="text-muted">Managed by circulation</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-muted py-4">No copies registered.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-body">{{ $copies->links() }}</div>
</div>
@endsection
