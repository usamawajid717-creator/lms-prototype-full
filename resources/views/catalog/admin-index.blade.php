@extends('layouts.app')

@section('title', 'Manage Catalog')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h4 class="fw-bold text-dark mb-1">Catalog Management</h4>
        <p class="text-muted mb-0">Add and update books and their registered copy counts.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('catalog.categories.index') }}" class="btn btn-outline-primary">Manage Categories</a>
        <a href="{{ route('catalog.admin.create') }}" class="btn btn-primary btn-submit text-white"><i class="bi bi-plus-lg me-1"></i>Add Book</a>
    </div>
</div>

@if (session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif
@if ($errors->any())
    <div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif

<div class="card form-card border-0">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Title / Author</th>
                    <th>ISBN</th>
                    <th>Category</th>
                    <th>Copies</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($books as $book)
                    <tr>
                        <td><strong>{{ $book->title }}</strong><br><span class="text-muted small">{{ $book->authors }}</span></td>
                        <td>{{ $book->isbn }}</td>
                        <td>{{ $book->category->name }} @if (! $book->category->is_active)<span class="badge text-bg-secondary">Inactive</span>@endif</td>
                        <td>{{ $book->available_copies }} / {{ $book->total_copies }} available</td>
                        <td class="text-nowrap">
                            <a href="{{ route('catalog.admin.copies', $book) }}" class="btn btn-sm btn-outline-secondary">Copies</a>
                            <a href="{{ route('catalog.admin.edit', $book) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                            <form action="{{ route('catalog.admin.destroy', $book) }}" method="POST" class="d-inline" onsubmit="return confirm('Remove this book from the catalog?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">Remove</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-4">No books have been added.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-body">{{ $books->links() }}</div>
</div>
@endsection
