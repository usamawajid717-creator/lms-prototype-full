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
                @if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
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
        <section class="card form-card border-0 mt-4">
            <div class="card-body p-4">
                @php($averageRating = $book->reviews->avg('rating'))
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                    <h4 class="fw-bold mb-0">Member Reviews</h4>
                    <span class="text-muted">{{ $averageRating ? number_format($averageRating, 1).' / 5 · ' : '' }}{{ $book->reviews->count() }} {{ \Illuminate\Support\Str::plural('review', $book->reviews->count()) }}</span>
                </div>
                @auth
                    @unless (auth()->user()->isAdmin())
                        <form method="POST" action="{{ route('book-reviews.store', $book) }}" class="border rounded p-3 mb-4">
                            @csrf @method('PUT')
                            <h5 class="mb-3">{{ $myReview ? 'Update your review' : 'Write a review' }}</h5>
                            <div class="mb-3">
                                <label for="rating" class="form-label">Rating *</label>
                                <select id="rating" name="rating" class="form-select" required>
                                    @foreach (range(5, 1) as $rating)
                                        <option value="{{ $rating }}" @selected((int) old('rating', $myReview?->rating ?? 5) === $rating)>{{ $rating }} / 5</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-3"><label for="comment" class="form-label">Comment</label><textarea id="comment" name="comment" class="form-control" rows="3" maxlength="2000">{{ old('comment', $myReview?->comment) }}</textarea></div>
                            <button class="btn btn-primary btn-submit text-white" type="submit">Save review</button>
                        </form>
                    @endunless
                @endauth
                @forelse ($book->reviews as $review)
                    <article class="border-top py-3">
                        <div class="d-flex justify-content-between gap-2">
                            <strong>{{ $review->member->name }}</strong>
                            <span class="text-warning" aria-label="{{ $review->rating }} out of 5 stars">{{ $review->rating }} / 5</span>
                        </div>
                        @if ($review->comment)<p class="mb-0 mt-2" style="white-space: pre-line">{{ $review->comment }}</p>@endif
                        <small class="text-muted">{{ $review->created_at->format('M j, Y') }}</small>
                        @if ($myReview?->id === $review->id)
                            <form method="POST" action="{{ route('book-reviews.destroy', $book) }}" class="mt-2">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger" type="submit">Remove my review</button>
                            </form>
                        @endif
                    </article>
                @empty
                    <p class="text-muted mb-0">No reviews yet.</p>
                @endforelse
            </div>
        </section>
    </div>
</div>
@endsection
