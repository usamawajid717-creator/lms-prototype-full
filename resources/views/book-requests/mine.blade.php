@extends('layouts.app')

@section('title', 'Book Requests')

@section('content')
<div class="row g-4">
    <div class="col-lg-5">
        <div class="mb-3">
            <h4 class="fw-bold mb-1">Request a Book</h4>
            <p class="text-muted mb-0">Suggest a title that is not currently in the library catalog.</p>
        </div>
        @if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
        @if ($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        <div class="card form-card border-0">
            <div class="card-body p-4">
                <form method="POST" action="{{ route('book-requests.store') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label" for="book-request-search">Search Open Library <span class="text-muted fw-normal">(optional)</span></label>
                        <div class="position-relative">
                            <input id="book-request-search" class="form-control" autocomplete="off" placeholder="Search by title or author" aria-autocomplete="list" aria-expanded="false" aria-controls="book-request-suggestions">
                            <div id="book-request-suggestions" class="list-group position-absolute top-100 start-0 end-0 shadow d-none" role="listbox" aria-label="Book suggestions" style="z-index: 1050; max-height: 16rem; overflow-y: auto;"></div>
                        </div>
                        <div id="book-request-search-status" class="form-text" role="status">Choose a suggestion to fill in the title, author, and ISBN, or enter details manually.</div>
                    </div>
                    <div class="mb-3"><label class="form-label" for="title">Book title *</label><input id="title" name="title" class="form-control" value="{{ old('title') }}" maxlength="255" required></div>
                    <div class="mb-3"><label class="form-label" for="authors">Author(s)</label><input id="authors" name="authors" class="form-control" value="{{ old('authors') }}" maxlength="255"></div>
                    <div class="mb-3"><label class="form-label" for="isbn">ISBN</label><input id="isbn" name="isbn" class="form-control" value="{{ old('isbn') }}" maxlength="20"></div>
                    <div class="mb-3"><label class="form-label" for="notes">Why should the library add it?</label><textarea id="notes" name="notes" class="form-control" rows="3" maxlength="2000">{{ old('notes') }}</textarea></div>
                    <button class="btn btn-primary btn-submit text-white" type="submit">Submit request</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <h4 class="fw-bold mb-3">My Requests</h4>
        @forelse ($requests as $item)
            <div class="card form-card border-0 mb-3"><div class="card-body">
                <div class="d-flex justify-content-between gap-2"><h5 class="mb-1">{{ $item->title }}</h5><span class="badge text-bg-{{ ['pending' => 'warning', 'approved' => 'primary', 'ordered' => 'info', 'received' => 'success', 'declined' => 'danger', 'fulfilled' => 'success'][$item->status] }}">{{ ucfirst($item->status) }}</span></div>
                <div class="text-muted small">{{ $item->authors ?: 'Author not specified' }}@if ($item->isbn) · ISBN {{ $item->isbn }}@endif</div>
                @if ($item->supplier)<p class="mt-2 mb-0">Ordered from {{ $item->supplier }}@if ($item->ordered_at) on {{ $item->ordered_at->format('M j, Y') }}@endif</p>@endif
                @if ($item->received_at)<p class="mt-1 mb-0 text-success">Received by the library {{ $item->received_at->format('M j, Y') }}. Catalog processing is in progress.</p>@endif
                @if ($item->decision_reason)<p class="mt-2 mb-0"><strong>Admin note:</strong> {{ $item->decision_reason }}</p>@endif
                @if ($item->book)<p class="mt-2 mb-0">Added to the catalog: <a href="{{ route('catalog.show', $item->book) }}">{{ $item->book->title }}</a></p>@endif
            </div></div>
        @empty
            <div class="alert alert-light border">You have not submitted any book requests.</div>
        @endforelse
        {{ $requests->links() }}
    </div>
</div>
@endsection

@section('scripts')
<script>
    (() => {
        const search = document.getElementById('book-request-search');
        const suggestions = document.getElementById('book-request-suggestions');
        const status = document.getElementById('book-request-search-status');
        const fields = {
            title: document.getElementById('title'),
            authors: document.getElementById('authors'),
            isbn: document.getElementById('isbn'),
        };
        let timer;
        let request;

        const clearSuggestions = () => {
            suggestions.replaceChildren();
            suggestions.classList.add('d-none');
            search.setAttribute('aria-expanded', 'false');
        };

        const textElement = (tag, className, value) => {
            const element = document.createElement(tag);
            element.className = className;
            element.textContent = value;
            return element;
        };

        const selectBook = (book) => {
            Object.entries(fields).forEach(([key, field]) => {
                if (book[key]) {
                    field.value = book[key];
                    field.dispatchEvent(new Event('input', { bubbles: true }));
                }
            });
            search.value = '';
            clearSuggestions();
            status.textContent = 'Book details filled in. Review them before submitting the request.';
        };

        const lookup = async () => {
            const query = search.value.trim();
            if (query.length < 2) {
                request?.abort();
                request = null;
                clearSuggestions();
                status.textContent = 'Type at least two characters to search Open Library.';
                return;
            }

            request?.abort();
            const currentRequest = new AbortController();
            request = currentRequest;
            status.textContent = 'Searching Open Library...';

            try {
                const url = new URL(@json(route('book-requests.book-suggestions')), window.location.origin);
                url.searchParams.set('search', query);
                const response = await fetch(url, {
                    headers: { 'Accept': 'application/json' },
                    signal: currentRequest.signal,
                });
                const payload = await response.json();

                if (request !== currentRequest || search.value.trim() !== query) {
                    return;
                }
                if (!response.ok) {
                    clearSuggestions();
                    status.textContent = payload.message || 'Book suggestions are unavailable. You can enter details manually.';
                    return;
                }

                suggestions.replaceChildren();
                payload.data.forEach((book) => {
                    const option = document.createElement('button');
                    option.type = 'button';
                    option.className = 'list-group-item list-group-item-action';
                    option.setAttribute('role', 'option');
                    option.append(textElement('div', 'fw-semibold', book.title));
                    const details = [book.authors, book.publication_year, book.isbn ? `ISBN ${book.isbn}` : null]
                        .filter(Boolean)
                        .join(' · ');
                    if (details) {
                        option.append(textElement('div', 'small text-muted', details));
                    }
                    option.addEventListener('click', () => selectBook(book));
                    suggestions.append(option);
                });

                if (!payload.data.length) {
                    suggestions.append(textElement('div', 'list-group-item small text-muted', 'No matching books found. Enter the details manually.'));
                }
                suggestions.classList.remove('d-none');
                search.setAttribute('aria-expanded', 'true');
                status.textContent = payload.data.length
                    ? 'Select a suggestion to fill in the request.'
                    : 'No matches found. You can enter the details manually.';
            } catch (error) {
                if (error.name !== 'AbortError' && request === currentRequest) {
                    clearSuggestions();
                    status.textContent = 'Could not reach Open Library. Please enter the details manually.';
                }
            } finally {
                if (request === currentRequest) {
                    request = null;
                }
            }
        };

        search.addEventListener('input', () => {
            window.clearTimeout(timer);
            clearSuggestions();
            timer = window.setTimeout(lookup, 350);
        });
        search.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                clearSuggestions();
            }
        });
        search.addEventListener('blur', () => window.setTimeout(clearSuggestions, 150));
    })();
</script>
@endsection
