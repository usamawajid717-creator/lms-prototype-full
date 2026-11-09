@extends('layouts.app')

@section('title', $book->title.' — Library Catalog')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <a href="{{ route('catalog.index') }}" class="text-decoration-none">&larr; Back to catalog</a>
        <div class="card form-card border-0 mt-3">
            <div class="card-body p-4 p-md-5">
                @if ($book->cover_image_path)
                    <img src="{{ Storage::disk('public')->url($book->cover_image_path) }}" alt="Cover of {{ $book->title }}" class="img-fluid mb-4" style="max-height: 320px;">
                @endif
                <span class="badge text-bg-light mb-3">{{ $book->category->name }}</span>
                <h3 class="fw-bold">{{ $book->title }}</h3>
                <p class="text-muted fs-5">by {{ $book->authors }}</p>
                <hr>
                <dl class="row mb-4">
                    <dt class="col-sm-4">ISBN</dt>
                    <dd class="col-sm-8">{{ $book->isbn }}</dd>
                    <dt class="col-sm-4">Publisher</dt>
                    <dd class="col-sm-8">{{ $book->publisher ?: 'Not specified' }}</dd>
                    <dt class="col-sm-4">Publication Year</dt>
                    <dd class="col-sm-8">{{ $book->publication_year ?: 'Not specified' }}</dd>
                    <dt class="col-sm-4">Edition</dt>
                    <dd class="col-sm-8">{{ $book->edition ?: 'Not specified' }}</dd>
                    <dt class="col-sm-4">Copies</dt>
                    <dd class="col-sm-8">{{ $book->available_copies }} available of {{ $book->total_copies }}</dd>
                </dl>
                @if ($errors->any())
                    <div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
                @endif
                @auth
                    @if (auth()->user()->isAdmin())
                        <a href="{{ route('catalog.admin.edit', $book) }}" class="btn btn-outline-primary">Edit Book</a>
                    @elseif ($book->available_copies === 0)
                        @if ($activeReservation)
                            <a href="{{ route('reservations.mine') }}" class="btn btn-outline-primary">View My Reservation</a>
                        @else
                            <form method="POST" action="{{ route('reservations.store', $book) }}">
                                @csrf
                                <button class="btn btn-primary btn-submit text-white" type="submit">Join Reservation Queue</button>
                            </form>
                        @endif
                    @endif
                @else
                    <p class="text-muted small mb-0">Sign in as a member to reserve this book when no copies are available.</p>
                @endauth
            </div>
        </div>
    </div>
</div>
@endsection
