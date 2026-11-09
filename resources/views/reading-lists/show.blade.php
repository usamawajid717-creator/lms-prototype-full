@extends('layouts.app')

@section('title', $readingList->course_code.' — Reading List')

@section('content')
<a href="{{ route('reading-lists.index') }}" class="text-decoration-none">&larr; All reading lists</a>
@if (session('status'))<div class="alert alert-success mt-3">{{ session('status') }}</div>@endif
@if ($errors->any())<div class="alert alert-danger mt-3"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<section class="card form-card border-0 mt-3 mb-4">
    <div class="card-body p-4 p-lg-5">
        <div class="d-flex flex-wrap justify-content-between gap-3">
            <div>
                <span class="badge text-bg-primary">{{ $readingList->course_code }}</span>
                <h1 class="h3 fw-bold mt-2 mb-1">{{ $readingList->course_name }}</h1>
                <p class="text-muted">{{ $readingList->semester }} {{ $readingList->academic_year }} · {{ $readingList->faculty->name }}</p>
                @if ($readingList->description)<p class="mb-0">{{ $readingList->description }}</p>@endif
            </div>
            @if ($canManage)
                <div><span class="badge text-bg-{{ $readingList->is_published ? 'success' : 'secondary' }}">{{ $readingList->is_published ? 'Published' : 'Draft' }}</span></div>
            @endif
        </div>
    </div>
</section>

@if ($canManage)
    <div class="card form-card border-0 mb-4">
        <div class="card-body p-4">
            <h5 class="fw-bold">Edit course details</h5>
            <form method="POST" action="{{ route('reading-lists.update', $readingList) }}" class="row g-3">
                @csrf @method('PUT')
                <div class="col-md-3"><label class="form-label" for="course_code">Course code</label><input id="course_code" name="course_code" class="form-control" value="{{ $readingList->course_code }}" maxlength="50" required></div>
                <div class="col-md-5"><label class="form-label" for="course_name">Course name</label><input id="course_name" name="course_name" class="form-control" value="{{ $readingList->course_name }}" maxlength="150" required></div>
                <div class="col-md-2"><label class="form-label" for="semester">Semester</label><input id="semester" name="semester" class="form-control" value="{{ $readingList->semester }}" maxlength="50" required></div>
                <div class="col-md-2"><label class="form-label" for="academic_year">Year</label><input id="academic_year" name="academic_year" type="number" min="2000" max="2100" class="form-control" value="{{ $readingList->academic_year }}" required></div>
                <div class="col-12"><label class="form-label" for="description">Description</label><textarea id="description" name="description" class="form-control" rows="2" maxlength="2000">{{ $readingList->description }}</textarea></div>
                <div class="col-12 d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <label class="form-check-label"><input type="checkbox" class="form-check-input me-1" name="is_published" value="1" @checked($readingList->is_published)> Publish for students</label>
                    <div class="d-flex gap-2">
                        <button class="btn btn-outline-primary" type="submit">Save changes</button>
                    </div>
                </div>
            </form>
            <form method="POST" action="{{ route('reading-lists.destroy', $readingList) }}" class="mt-3" onsubmit="return confirm('Delete this reading list?')">
                @csrf @method('DELETE')
                <button class="btn btn-sm btn-outline-danger" type="submit">Delete reading list</button>
            </form>
        </div>
    </div>
    <div class="card form-card border-0 mb-4"><div class="card-body p-4">
        <h5 class="fw-bold">Add a catalog book</h5>
        <form method="POST" action="{{ route('reading-lists.items.store', $readingList) }}" class="row g-2 align-items-end">
            @csrf
            <div class="col-lg-7"><label for="book_id" class="form-label">Active catalog book</label><select id="book_id" name="book_id" class="form-select" required><option value="">Select a book</option>@foreach ($books as $book)<option value="{{ $book->id }}">{{ $book->title }} — {{ $book->authors }} ({{ $book->available_copies }} available)</option>@endforeach</select></div>
            <div class="col-lg-3"><label for="notes" class="form-label">Reading note</label><input id="notes" name="notes" class="form-control" maxlength="1000" placeholder="Chapter or required/optional"></div>
            <div class="col-lg-2"><button class="btn btn-primary btn-submit text-white w-100" type="submit">Add book</button></div>
        </form>
    </div></div>
@endif

<div class="card form-card border-0">
    <div class="card-header bg-white border-bottom"><h2 class="h5 fw-bold mb-0">Assigned reading · {{ $readingList->items->count() }} books</h2></div>
    <div class="list-group list-group-flush">
        @forelse ($readingList->items as $item)
            <article class="list-group-item p-4">
                <div class="d-flex flex-wrap justify-content-between gap-3">
                    <div>
                        <h3 class="h6 fw-bold mb-1"><a href="{{ route('catalog.show', $item->book) }}" class="text-decoration-none">{{ $item->book->title }}</a></h3>
                        <div class="small text-muted">{{ $item->book->authors }} · {{ $item->book->category->name }}</div>
                        <div class="small mt-2">
                            @if ($item->book->available_copies > 0)
                                <span class="text-success">{{ $item->book->available_copies }} available now</span>
                            @else
                                <span class="text-danger">Currently unavailable</span>
                            @endif
                            · {{ $item->book->isbn }}
                        </div>
                        @if ($item->notes)<p class="small mb-0 mt-2"><strong>Faculty note:</strong> {{ $item->notes }}</p>@endif
                    </div>
                    @if ($canManage)
                        <form method="POST" action="{{ route('reading-lists.items.destroy', [$readingList, $item]) }}">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger" type="submit">Remove</button>
                        </form>
                    @endif
                </div>
            </article>
        @empty
            <div class="list-group-item text-muted p-4">No books have been added to this reading list yet.</div>
        @endforelse
    </div>
</div>
@endsection
