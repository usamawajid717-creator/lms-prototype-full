@extends('layouts.app')

@section('title', ($book->exists ? 'Edit Book' : 'Add Book').' — Library Catalog')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-9">
        <div class="mb-4">
            <h4 class="fw-bold text-dark mb-1">{{ $book->exists ? 'Edit Book' : 'Add Book to Catalog' }}</h4>
            <p class="text-muted mb-0">Enter bibliographic details and the number of registered copies.</p>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif

        @if ($categories->isEmpty())
            <div class="alert alert-warning">Create an active category before adding a book. <a href="{{ route('catalog.categories.index') }}">Manage categories</a>.</div>
        @endif

        <div class="card form-card border-0">
            <div class="card-body p-4">
                <form method="POST" enctype="multipart/form-data" action="{{ $book->exists ? route('catalog.admin.update', $book) : route('catalog.admin.store') }}">
                    @csrf
                    @if ($book->exists)
                        @method('PUT')
                    @endif

                    <div class="row g-3">
                        @unless ($book->exists)
                            <div class="col-12">
                                <label for="open-library-search" class="form-label">Find book details <span class="text-muted fw-normal">(Open Library)</span></label>
                                <div class="position-relative">
                                    <div class="input-group">
                                        <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                                        <input id="open-library-search" class="form-control" autocomplete="off" placeholder="Search by book title or author" aria-autocomplete="list" aria-expanded="false" aria-controls="open-library-suggestions">
                                    </div>
                                    <div id="open-library-suggestions" class="list-group position-absolute top-100 start-0 end-0 shadow d-none" role="listbox" aria-label="Open Library book suggestions" style="z-index: 1050; max-height: 20rem; overflow-y: auto;"></div>
                                </div>
                                <div id="open-library-status" class="form-text" role="status">Search to fill in the book's title, author, ISBN, publisher, and year. Review the details before adding it.</div>
                            </div>
                        @endunless
                        <div class="col-md-8">
                            <label for="title" class="form-label">Title *</label>
                            <input id="title" name="title" class="form-control" value="{{ old('title', $book->title) }}" maxlength="255" required>
                        </div>
                        <div class="col-md-4">
                            <label for="isbn" class="form-label">ISBN *</label>
                            <input id="isbn" name="isbn" class="form-control" value="{{ old('isbn', $book->isbn) }}" maxlength="20" required>
                        </div>
                        <div class="col-md-6">
                            <label for="authors" class="form-label">Author(s) *</label>
                            <input id="authors" name="authors" class="form-control" value="{{ old('authors', $book->authors) }}" maxlength="255" required>
                        </div>
                        <div class="col-md-6">
                            <label for="category_id" class="form-label">Category *</label>
                            <select id="category_id" name="category_id" class="form-select" required>
                                <option value="">Select a category</option>
                                @foreach ($categories as $category)
                                    <option value="{{ $category->id }}" @selected((string) old('category_id', $book->category_id) === (string) $category->id)>
                                        {{ $category->name }}{{ $category->is_active ? '' : ' (Inactive)' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="publisher" class="form-label">Publisher</label>
                            <input id="publisher" name="publisher" class="form-control" value="{{ old('publisher', $book->publisher) }}" maxlength="150">
                        </div>
                        <div class="col-md-3">
                            <label for="publication_year" class="form-label">Publication Year</label>
                            <input id="publication_year" name="publication_year" type="number" min="1000" max="{{ now()->year + 1 }}" class="form-control" value="{{ old('publication_year', $book->publication_year) }}">
                        </div>
                        <div class="col-md-3">
                            <label for="edition" class="form-label">Edition</label>
                            <input id="edition" name="edition" class="form-control" value="{{ old('edition', $book->edition) }}" maxlength="100">
                        </div>
                        <div class="col-md-4">
                            <label for="total_copies" class="form-label">Total Copies *</label>
                            <input id="total_copies" name="total_copies" type="number" min="0" max="100000" class="form-control" value="{{ old('total_copies', $book->total_copies) }}" required>
                            @if ($book->exists)
                                <div class="form-text">Unavailable copies are preserved when you change this total.</div>
                            @endif
                        </div>
                    </div>

                    <div class="mt-3">
                        <label for="cover_image" class="form-label">Book cover image</label>
                        <input id="cover_image" name="cover_image" type="file" accept=".jpg,.jpeg,.png,.webp" class="form-control">
                        <div class="form-text">Optional. JPG, PNG, or WebP; maximum 5 MB.</div>
                        @if ($book->cover_image_path)
                            <div class="mt-2 d-flex align-items-center gap-3">
                                <img src="{{ Storage::disk('public')->url($book->cover_image_path) }}" alt="Current cover for {{ $book->title }}" style="width: 80px; height: 110px; object-fit: cover;">
                                <label class="form-check-label">
                                    <input class="form-check-input" type="checkbox" name="remove_cover" value="1">
                                    Remove current cover
                                </label>
                            </div>
                        @endif
                    </div>

                    <div class="d-flex justify-content-end gap-2 mt-4">
                        <a href="{{ route('catalog.admin.index') }}" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary btn-submit text-white" @disabled($categories->isEmpty())>{{ $book->exists ? 'Save Changes' : 'Add Book' }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@unless ($book->exists)
    @section('scripts')
    <script>
        (() => {
            const search = document.getElementById('open-library-search');
            const suggestions = document.getElementById('open-library-suggestions');
            const status = document.getElementById('open-library-status');
            const fields = {
                title: document.getElementById('title'),
                authors: document.getElementById('authors'),
                isbn: document.getElementById('isbn'),
                publisher: document.getElementById('publisher'),
                publication_year: document.getElementById('publication_year'),
            };

            if (!search || !suggestions || !status) {
                return;
            }

            let timer;
            let request;

            const clearSuggestions = () => {
                suggestions.replaceChildren();
                suggestions.classList.add('d-none');
                search.setAttribute('aria-expanded', 'false');
            };

            const makeText = (tag, className, value) => {
                const element = document.createElement(tag);
                element.className = className;
                element.textContent = value;
                return element;
            };

            const applyBook = (book) => {
                Object.entries(fields).forEach(([key, field]) => {
                    if (field && book[key] !== null && book[key] !== '') {
                        field.value = book[key];
                        field.dispatchEvent(new Event('input', { bubbles: true }));
                    }
                });

                search.value = '';
                clearSuggestions();
                status.textContent = 'Book details filled in. Review or edit them before adding the book.';
                fields.title?.focus();
            };

            const showSuggestions = (books) => {
                suggestions.replaceChildren();

                if (!books.length) {
                    suggestions.append(makeText('div', 'list-group-item small text-muted', 'No matching books found. You can enter the details manually.'));
                }

                books.forEach((book) => {
                    const option = document.createElement('button');
                    option.type = 'button';
                    option.className = 'list-group-item list-group-item-action';
                    option.setAttribute('role', 'option');
                    option.append(makeText('div', 'fw-semibold', book.title));

                    const details = [book.authors, book.publication_year, book.isbn ? `ISBN ${book.isbn}` : null]
                        .filter(Boolean)
                        .join(' · ');
                    if (details) {
                        option.append(makeText('div', 'small text-muted', details));
                    }

                    if (book.publisher) {
                        option.append(makeText('div', 'small text-muted', book.publisher));
                    }

                    option.addEventListener('click', () => applyBook(book));
                    suggestions.append(option);
                });

                suggestions.classList.remove('d-none');
                search.setAttribute('aria-expanded', 'true');
            };

            const lookup = async () => {
                const query = search.value.trim();
                if (query.length < 2) {
                    if (request) {
                        request.abort();
                        request = null;
                    }
                    clearSuggestions();
                    status.textContent = 'Search for a title or author to fill in the book details.';
                    return;
                }

                if (request) {
                    request.abort();
                }

                const currentRequest = new AbortController();
                request = currentRequest;
                status.textContent = 'Searching Open Library...';

                try {
                    const url = new URL(@json(route('catalog.admin.book-suggestions')), window.location.origin);
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
                        status.textContent = payload.message || 'Book suggestions are unavailable. Please enter the details manually.';
                        return;
                    }

                    showSuggestions(payload.data);
                    status.textContent = payload.data.length
                        ? 'Select a suggestion to fill in its details. Review them before adding the book.'
                        : 'No matches found. You can enter the book details manually.';
                } catch (error) {
                    if (error.name !== 'AbortError' && request === currentRequest) {
                        clearSuggestions();
                        status.textContent = 'Could not reach Open Library. Please enter the book details manually.';
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
                status.textContent = search.value.trim().length < 2
                    ? 'Type at least two characters to search Open Library.'
                    : 'Searching Open Library...';
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
@endunless
