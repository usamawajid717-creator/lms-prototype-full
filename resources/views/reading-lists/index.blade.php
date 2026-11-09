@extends('layouts.app')

@section('title', 'Course Reading Lists')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div><h4 class="fw-bold mb-1">Course Reading Lists</h4><p class="text-muted mb-0">Faculty-curated titles for courses and semesters, with live catalog availability.</p></div>
</div>
@if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if ($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

@if ($canCreate)
    <div class="card form-card border-0 mb-4">
        <div class="card-body p-4">
            <h5 class="fw-bold">Create a reading list</h5>
            <form method="POST" action="{{ route('reading-lists.store') }}" class="row g-3">
                @csrf
                <div class="col-md-3"><label for="course_code" class="form-label">Course code *</label><input id="course_code" name="course_code" class="form-control" value="{{ old('course_code') }}" maxlength="50" required></div>
                <div class="col-md-5"><label for="course_name" class="form-label">Course name *</label><input id="course_name" name="course_name" class="form-control" value="{{ old('course_name') }}" maxlength="150" required></div>
                <div class="col-md-2"><label for="semester" class="form-label">Semester *</label><input id="semester" name="semester" class="form-control" value="{{ old('semester') }}" maxlength="50" placeholder="Fall" required></div>
                <div class="col-md-2"><label for="academic_year" class="form-label">Year *</label><input id="academic_year" name="academic_year" type="number" min="2000" max="2100" class="form-control" value="{{ old('academic_year', now()->year) }}" required></div>
                <div class="col-12"><label for="description" class="form-label">Description</label><textarea id="description" name="description" class="form-control" rows="2" maxlength="2000">{{ old('description') }}</textarea></div>
                <div class="col-12 d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <label class="form-check-label"><input type="checkbox" class="form-check-input me-1" name="is_published" value="1" @checked(old('is_published'))> Publish for students</label>
                    <button class="btn btn-primary btn-submit text-white" type="submit">Create list</button>
                </div>
            </form>
        </div>
    </div>
@endif

<div class="row g-3">
    @forelse ($lists as $list)
        <div class="col-md-6 col-xl-4">
            <article class="card form-card border-0 h-100">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between gap-2 mb-2">
                        <span class="badge text-bg-primary">{{ $list->course_code }}</span>
                        @unless ($list->is_published)<span class="badge text-bg-secondary">Draft · only you can see this</span>@endunless
                    </div>
                    <h5 class="fw-bold">{{ $list->course_name }}</h5>
                    <p class="text-muted mb-2">{{ $list->semester }} {{ $list->academic_year }}</p>
                    <p class="small mb-3">By {{ $list->faculty->name }} · {{ $list->items_count }} {{ \Illuminate\Support\Str::plural('book', $list->items_count) }}</p>
                    @if ($list->description)<p class="text-muted small">{{ $list->description }}</p>@endif
                    <a href="{{ route('reading-lists.show', $list) }}" class="btn btn-outline-primary">View reading list</a>
                </div>
            </article>
        </div>
    @empty
        <div class="col-12"><div class="alert alert-light border">{{ $canCreate ? 'No reading lists yet. Create one for your course above.' : 'No published course reading lists are available yet.' }}</div></div>
    @endforelse
</div>
<div class="mt-4">{{ $lists->links() }}</div>
@endsection
