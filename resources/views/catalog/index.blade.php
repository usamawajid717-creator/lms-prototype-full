@extends('layouts.app')

@section('title', 'Library Catalog')

@section('content')
<div class="mb-4">
    <h4 class="fw-bold text-dark mb-1">Library Catalog</h4>
    <p class="text-muted mb-0">Search and browse books by title, author, ISBN, or publisher.</p>
</div>

<div class="card form-card border-0 mb-4">
    <div class="card-body">
        <form id="catalog-search-form" action="{{ route('catalog.index') }}" method="GET" class="row g-3 align-items-end">
            <div class="col-lg-5 col-md-6">
                <label for="search" class="form-label">Search</label>
                <div class="position-relative">
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                    <input id="search" name="search" class="form-control" value="{{ request('search') }}" placeholder="Start typing a title, author, ISBN, or publisher" autocomplete="off" aria-autocomplete="list" aria-expanded="false" aria-controls="catalog-suggestions">
                </div>
                <div id="catalog-suggestions" class="list-group position-absolute top-100 start-0 end-0 shadow d-none" role="listbox" aria-label="Book suggestions" style="z-index: 1050; max-height: 20rem; overflow-y: auto;"></div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <label for="category" class="form-label">Category</label>
                <select id="category" name="category" class="form-select">
                    <option value="">All categories</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected((string) request('category') === (string) $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-2 col-md-6">
                <label for="year" class="form-label">Publication Year</label>
                <input id="year" name="year" type="number" min="1000" max="{{ now()->year + 1 }}" class="form-control" value="{{ request('year') }}">
            </div>
            <div class="col-lg-2 col-md-6">
                <label for="availability" class="form-label">Availability</label>
                <select id="availability" name="availability" class="form-select">
                    <option value="">Any</option>
                    <option value="available" @selected(request('availability') === 'available')>Available</option>
                    <option value="unavailable" @selected(request('availability') === 'unavailable')>Checked out</option>
                </select>
            </div>
            <div class="col-12 d-flex justify-content-end gap-2">
                <a href="{{ route('catalog.index') }}" class="btn btn-outline-secondary">Clear</a>
                <button class="btn btn-primary btn-submit text-white" type="submit"><i class="bi bi-search me-1"></i>Search</button>
            </div>
        </form>
    </div>
</div>

<div class="d-flex justify-content-between align-items-center mb-3">
    <span class="small text-muted" id="catalog-result-count" aria-live="polite">{{ $books->total() }} {{ \Illuminate\Support\Str::plural('book', $books->total()) }} found</span>
    <span class="small text-primary d-none" id="catalog-search-status" role="status"><span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Updating results...</span>
</div>

<div id="catalog-results" class="row g-3">
    @forelse ($books as $book)
        <div class="col-lg-4 col-md-6">
            <div class="card form-card border-0 h-100">
                @if ($book->cover_image_path)
                    <img src="{{ Storage::disk('public')->url($book->cover_image_path) }}" alt="Cover of {{ $book->title }}" class="card-img-top" style="height: 220px; object-fit: contain; background: #f8f9fa;">
                @endif
                <div class="card-body d-flex flex-column">
                    <span class="badge text-bg-light align-self-start mb-2">{{ $book->category->name }}</span>
                    <h5 class="fw-bold mb-1">{{ $book->title }}</h5>
                    <p class="text-muted mb-3">by {{ $book->authors }}</p>
                    <div class="small text-muted mb-3">
                        <div>ISBN: {{ $book->isbn }}</div>
                        @if ($book->publication_year)
                            <div>Published: {{ $book->publication_year }}</div>
                        @endif
                        @if ($book->publisher)
                            <div>Publisher: {{ $book->publisher }}</div>
                        @endif
                    </div>
                    <div class="mt-auto d-flex justify-content-between align-items-center">
                        <span class="badge {{ $book->hasAvailableCopies() ? 'text-bg-success' : 'text-bg-secondary' }}">
                            {{ $book->available_copies }} of {{ $book->total_copies }} available
                        </span>
                        <a href="{{ route('catalog.show', $book) }}" class="btn btn-sm btn-outline-primary">Details</a>
                    </div>
                </div>
            </div>
        </div>
    @empty
        <div class="col-12">
            <div class="card form-card border-0">
                <div class="card-body text-center text-muted py-5">
                    <i class="bi bi-search fs-2 d-block mb-2"></i>
                    No books found. Try a different search term or broaden your filters.
                </div>
            </div>
        </div>
    @endforelse
</div>

<div id="catalog-pagination" class="mt-4">{{ $books->links() }}</div>
@endsection

@section('scripts')
<script>
    (() => {
        const form = document.getElementById('catalog-search-form');
        const results = document.getElementById('catalog-results');
        const pagination = document.getElementById('catalog-pagination');
        const status = document.getElementById('catalog-search-status');
        const resultCount = document.getElementById('catalog-result-count');
        const searchInput = document.getElementById('search');
        const suggestions = document.getElementById('catalog-suggestions');

        if (!form || !results || !pagination || !status || !resultCount || !searchInput || !suggestions) {
            return;
        }

        let timer;
        let activeRequest;
        let suggestionTimer;
        let suggestionRequest;
        let activeSuggestion = -1;

        const clearSuggestions = () => {
            suggestions.replaceChildren();
            suggestions.classList.add('d-none');
            searchInput.setAttribute('aria-expanded', 'false');
            searchInput.removeAttribute('aria-activedescendant');
            activeSuggestion = -1;
        };

        const showSuggestions = (books) => {
            suggestions.replaceChildren();
            activeSuggestion = -1;

            books.forEach((book, index) => {
                const option = document.createElement('a');
                option.className = 'list-group-item list-group-item-action py-2';
                option.href = book.url;
                option.id = `catalog-suggestion-${index}`;
                option.setAttribute('role', 'option');
                option.setAttribute('aria-selected', 'false');

                const title = document.createElement('div');
                title.className = 'fw-semibold';
                title.textContent = book.title;
                option.append(title);

                const details = document.createElement('div');
                details.className = 'small text-muted';
                details.textContent = `${book.authors} · ISBN ${book.isbn}`;
                option.append(details);

                option.addEventListener('click', clearSuggestions);
                suggestions.append(option);
            });

            if (!books.length) {
                const empty = document.createElement('div');
                empty.className = 'list-group-item small text-muted';
                empty.textContent = 'No matching books found.';
                suggestions.append(empty);
            }

            suggestions.classList.remove('d-none');
            searchInput.setAttribute('aria-expanded', 'true');
        };

        const fetchSuggestions = async () => {
            const query = searchInput.value.trim();
            if (query.length < 2) {
                if (suggestionRequest) {
                    suggestionRequest.abort();
                    suggestionRequest = null;
                }
                clearSuggestions();
                return;
            }

            if (suggestionRequest) {
                suggestionRequest.abort();
            }

            const request = new AbortController();
            suggestionRequest = request;

            try {
                const url = new URL(@json(route('catalog.suggestions')), window.location.origin);
                url.searchParams.set('search', query);
                const response = await fetch(url, {
                    headers: { 'Accept': 'application/json' },
                    signal: request.signal,
                });

                if (!response.ok) {
                    clearSuggestions();
                    return;
                }

                const payload = await response.json();
                if (suggestionRequest === request && searchInput.value.trim() === query) {
                    showSuggestions(payload.data);
                }
            } catch (error) {
                if (error.name !== 'AbortError') {
                    clearSuggestions();
                }
            } finally {
                if (suggestionRequest === request) {
                    suggestionRequest = null;
                }
            }
        };

        const updateResults = async (url, updateHistory = true) => {
            if (activeRequest) {
                activeRequest.abort();
            }

            const request = new AbortController();
            activeRequest = request;
            status.classList.remove('d-none');
            results.setAttribute('aria-busy', 'true');

            try {
                const response = await fetch(url, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    signal: request.signal,
                });

                if (!response.ok) {
                    window.location.assign(url);
                    return;
                }

                const document = new DOMParser().parseFromString(await response.text(), 'text/html');
                const nextResults = document.getElementById('catalog-results');
                const nextPagination = document.getElementById('catalog-pagination');
                const nextCount = document.getElementById('catalog-result-count');

                if (!nextResults || !nextPagination || !nextCount) {
                    window.location.assign(url);
                    return;
                }

                results.innerHTML = nextResults.innerHTML;
                pagination.innerHTML = nextPagination.innerHTML;
                resultCount.textContent = nextCount.textContent;

                if (updateHistory) {
                    window.history.pushState({}, '', url);
                }
            } catch (error) {
                if (error.name !== 'AbortError') {
                    window.location.assign(url);
                }
            } finally {
                if (activeRequest === request) {
                    activeRequest = null;
                    status.classList.add('d-none');
                    results.removeAttribute('aria-busy');
                }
            }
        };

        const search = (resetPage = true) => {
            window.clearTimeout(timer);
            timer = window.setTimeout(() => {
                const url = new URL(form.action, window.location.origin);
                new FormData(form).forEach((value, key) => {
                    if (value) {
                        url.searchParams.set(key, value);
                    } else {
                        url.searchParams.delete(key);
                    }
                });

                if (resetPage) {
                    url.searchParams.delete('page');
                }

                updateResults(url);
            }, 300);
        };

        searchInput.addEventListener('input', () => {
            window.clearTimeout(suggestionTimer);
            clearSuggestions();
            suggestionTimer = window.setTimeout(fetchSuggestions, 200);
            search();
        });

        searchInput.addEventListener('keydown', (event) => {
            const options = suggestions.querySelectorAll('[role="option"]');

            if (event.key === 'Escape') {
                clearSuggestions();
                return;
            }

            if (!options.length || !['ArrowDown', 'ArrowUp', 'Enter'].includes(event.key)) {
                return;
            }

            if (event.key === 'Enter' && activeSuggestion >= 0) {
                event.preventDefault();
                const url = options[activeSuggestion].href;
                clearSuggestions();
                window.location.assign(url);
                return;
            }

            if (event.key === 'Enter') {
                return;
            }

            event.preventDefault();
            if (activeSuggestion >= 0) {
                options[activeSuggestion].setAttribute('aria-selected', 'false');
                options[activeSuggestion].classList.remove('active');
            }
            const direction = event.key === 'ArrowDown' ? 1 : -1;
            activeSuggestion = (activeSuggestion + direction + options.length) % options.length;
            options[activeSuggestion].setAttribute('aria-selected', 'true');
            options[activeSuggestion].classList.add('active');
            searchInput.setAttribute('aria-activedescendant', options[activeSuggestion].id);
        });

        searchInput.addEventListener('blur', () => {
            window.setTimeout(clearSuggestions, 150);
        });

        searchInput.addEventListener('focus', () => {
            if (searchInput.value.trim().length >= 2 && !suggestions.children.length) {
                fetchSuggestions();
            }
        });
        form.querySelectorAll('select, input[type="number"]').forEach((filter) => {
            filter.addEventListener('change', () => search());
        });

        form.addEventListener('submit', (event) => {
            event.preventDefault();
            clearSuggestions();
            search();
        });

        pagination.addEventListener('click', (event) => {
            const link = event.target.closest('a');
            if (!link || link.origin !== window.location.origin) {
                return;
            }

            event.preventDefault();
            updateResults(link.href);
        });

        window.addEventListener('popstate', () => {
            const url = new URL(window.location.href);
            form.querySelectorAll('[name]').forEach((field) => {
                field.value = url.searchParams.get(field.name) || '';
            });
            updateResults(url, false);
        });
    })();
</script>
@endsection
