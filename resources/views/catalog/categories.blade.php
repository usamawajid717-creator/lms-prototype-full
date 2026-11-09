@extends('layouts.app')

@section('title', 'Manage Book Categories')

@section('content')
<div class="mb-4">
    <h4 class="fw-bold text-dark mb-1">Book Categories</h4>
    <p class="text-muted mb-0">Categories organize catalog entries. Inactive categories remain attached to existing books but are hidden publicly.</p>
</div>

@if (session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif
@if ($errors->any())
    <div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif

<div class="card form-card border-0 mb-4">
    <div class="card-header"><h5 class="text-white mb-0 fw-semibold">Add Category</h5></div>
    <div class="card-body p-4">
        <form action="{{ route('catalog.categories.store') }}" method="POST" class="row g-3 align-items-end">
            @csrf
            <div class="col-md-4">
                <label for="new_name" class="form-label">Category Name *</label>
                <input id="new_name" name="name" class="form-control" value="{{ old('name') }}" maxlength="100" required>
            </div>
            <div class="col-md-6">
                <label for="new_description" class="form-label">Description</label>
                <input id="new_description" name="description" class="form-control" value="{{ old('description') }}" maxlength="2000">
            </div>
            <div class="col-md-2 d-grid">
                <button class="btn btn-primary btn-submit text-white" type="submit">Add Category</button>
            </div>
        </form>
    </div>
</div>

<div class="card form-card border-0">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr><th>Name</th><th>Description</th><th>Books</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
                @forelse ($categories as $category)
                    <tr>
                        <td>
                            <form id="category-{{ $category->id }}" action="{{ route('catalog.categories.update', $category) }}" method="POST">
                                @csrf
                                @method('PUT')
                            </form>
                            <input form="category-{{ $category->id }}" name="name" class="form-control" value="{{ $category->name }}" maxlength="100" required>
                        </td>
                        <td><input form="category-{{ $category->id }}" name="description" class="form-control" value="{{ $category->description }}" maxlength="2000"></td>
                        <td>{{ $category->books_count }}</td>
                        <td>
                            <input form="category-{{ $category->id }}" type="hidden" name="is_active" value="0">
                            <div class="form-check">
                                <input form="category-{{ $category->id }}" class="form-check-input" type="checkbox" name="is_active" value="1" id="active-{{ $category->id }}" @checked($category->is_active)>
                                <label class="form-check-label" for="active-{{ $category->id }}">{{ $category->is_active ? 'Active' : 'Inactive' }}</label>
                            </div>
                        </td>
                        <td>
                            <button form="category-{{ $category->id }}" class="btn btn-sm btn-outline-primary" type="submit">Save</button>
                            @if ($category->is_active && $mergeTargets->where('id', '!=', $category->id)->isNotEmpty())
                                <details class="mt-2">
                                    <summary class="small text-danger">Merge category</summary>
                                    <form action="{{ route('catalog.categories.merge', $category) }}" method="POST" class="mt-2" onsubmit="return confirm('Move all books into the selected category and deactivate this category?')">
                                        @csrf
                                        @method('PATCH')
                                        <label for="merge-target-{{ $category->id }}" class="visually-hidden">Destination category</label>
                                        <select id="merge-target-{{ $category->id }}" name="target_category_id" class="form-select form-select-sm mb-1" required>
                                            <option value="">Select destination</option>
                                            @foreach ($mergeTargets->where('id', '!=', $category->id) as $target)
                                                <option value="{{ $target->id }}">{{ $target->name }}</option>
                                            @endforeach
                                        </select>
                                        <button class="btn btn-sm btn-outline-danger" type="submit">Merge & deactivate</button>
                                    </form>
                                </details>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-4">No categories have been created.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-body">{{ $categories->links() }}</div>
</div>
@endsection
