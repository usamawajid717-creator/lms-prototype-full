<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\BookCopy;
use App\Models\BookCategory;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Illuminate\Http\Client\ConnectionException;
use App\Services\ReservationService;

class CatalogController extends Controller
{
    public function bookSuggestions(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['required', 'string', 'min:2', 'max:150'],
        ]);
        $search = trim($validated['search']);

        try {
            $response = Http::acceptJson()
                ->withUserAgent('LibraryManagementSystem/1.0 (Open Library catalog lookup)')
                ->connectTimeout(3)
                ->timeout(6)
                ->get('https://openlibrary.org/search.json', [
                    'q' => $search,
                    'fields' => 'title,author_name,isbn,first_publish_year,publisher',
                    'limit' => 8,
                ])
                ->throw();
        } catch (ConnectionException|\Illuminate\Http\Client\RequestException $exception) {
            Log::warning('Open Library book lookup failed.', [
                'exception' => $exception::class,
                'search_length' => mb_strlen($search),
            ]);

            return response()->json([
                'message' => 'Book suggestions are temporarily unavailable. Please try again or enter the details manually.',
            ], 503);
        }

        $documents = $response->json('docs');
        if (! is_array($documents)) {
            Log::warning('Open Library returned an unexpected book lookup response.', [
                'search_length' => mb_strlen($search),
            ]);

            return response()->json([
                'message' => 'Book suggestions are temporarily unavailable. Please try again or enter the details manually.',
            ], 503);
        }

        $books = collect($documents)
            ->filter(fn ($document): bool => is_array($document) && is_string($document['title'] ?? null) && filled($document['title']))
            ->map(function (array $document): array {
                $authors = is_array($document['author_name'] ?? null) ? $document['author_name'] : [];
                $publishers = is_array($document['publisher'] ?? null) ? $document['publisher'] : [];
                $isbns = is_array($document['isbn'] ?? null) ? $document['isbn'] : [];
                $isbn = collect($isbns)
                    ->filter(fn ($value): bool => is_string($value))
                    ->map(fn (string $value): string => preg_replace('/[^0-9Xx]/', '', $value))
                    ->first(fn (string $value): bool => in_array(strlen($value), [10, 13], true));
                $year = $document['first_publish_year'] ?? null;
                $year = is_numeric($year) && (int) $year >= 1000 && (int) $year <= now()->year + 1
                    ? (int) $year
                    : null;

                return [
                    'title' => mb_substr(trim($document['title']), 0, 255),
                    'authors' => mb_substr(implode(', ', array_slice(array_filter($authors, 'is_string'), 0, 5)), 0, 255),
                    'isbn' => $isbn,
                    'publisher' => mb_substr(implode(', ', array_slice(array_filter($publishers, 'is_string'), 0, 3)), 0, 150),
                    'publication_year' => $year,
                ];
            })
            ->values()
            ->all();

        return response()->json(['data' => $books]);
    }

    public function suggestions(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
        ]);
        $search = trim($validated['search'] ?? '');

        if (mb_strlen($search) < 2) {
            return response()->json(['data' => []]);
        }

        $books = Book::query()
            ->whereHas('category', fn ($query) => $query->where('is_active', true))
            ->where(function ($query) use ($search): void {
                $query->where('title', 'like', "%{$search}%")
                    ->orWhere('authors', 'like', "%{$search}%")
                    ->orWhere('isbn', 'like', "%{$search}%")
                    ->orWhere('publisher', 'like', "%{$search}%");
            })
            ->orderBy('title')
            ->limit(8)
            ->get(['id', 'title', 'authors', 'isbn', 'publication_year', 'available_copies'])
            ->map(fn (Book $book): array => [
                'id' => $book->id,
                'title' => $book->title,
                'authors' => $book->authors,
                'isbn' => $book->isbn,
                'publication_year' => $book->publication_year,
                'available_copies' => $book->available_copies,
                'url' => route('catalog.show', $book),
            ]);

        return response()->json(['data' => $books]);
    }

    public function index(Request $request): View
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'integer', Rule::exists('book_categories', 'id')->where('is_active', true)],
            'year' => ['nullable', 'integer', 'between:1000,'.(now()->year + 1)],
            'availability' => ['nullable', Rule::in(['available', 'unavailable'])],
        ]);

        $books = Book::query()
            ->with('category')
            ->whereHas('category', fn ($query) => $query->where('is_active', true))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->trim()->toString();
                $query->where(function ($query) use ($search) {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('authors', 'like', "%{$search}%")
                        ->orWhere('isbn', 'like', "%{$search}%")
                        ->orWhere('publisher', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('category'), fn ($query) => $query->where('category_id', $request->integer('category')))
            ->when($request->filled('year'), fn ($query) => $query->where('publication_year', $request->integer('year')))
            ->when($request->input('availability') === 'available', fn ($query) => $query->where('available_copies', '>', 0))
            ->when($request->input('availability') === 'unavailable', fn ($query) => $query->where('available_copies', 0))
            ->orderBy('title')
            ->paginate(12)
            ->withQueryString();

        $categories = BookCategory::query()->where('is_active', true)->orderBy('name')->get();

        return view('catalog.index', compact('books', 'categories'));
    }

    public function show(Book $book, Request $request, ReservationService $reservations): View
    {
        abort_unless($book->category()->where('is_active', true)->exists(), 404);
        $reservations->expireHolds($book->id);
        $book->refresh();
        $activeReservation = $request->user()?->reservations()
            ->where('book_id', $book->id)
            ->whereIn('status', ['waiting', 'ready'])
            ->exists() ?? false;

        return view('catalog.show', [
            'book' => $book->load('category'),
            'activeReservation' => $activeReservation,
        ]);
    }

    public function adminIndex(): View
    {
        $books = Book::query()->with('category')->orderBy('title')->paginate(15);

        return view('catalog.admin-index', compact('books'));
    }

    public function create(): View
    {
        return view('catalog.form', [
            'book' => new Book(['total_copies' => 1]),
            'categories' => BookCategory::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->bookRules());
        $coverPath = $request->file('cover_image')?->storePublicly('book-covers', 'public');
        if ($request->hasFile('cover_image') && ! $coverPath) {
            return back()->withErrors(['cover_image' => 'The cover image could not be saved. Please try again.'])->withInput();
        }

        unset($validated['cover_image'], $validated['remove_cover']);
        $validated['available_copies'] = $validated['total_copies'];
        $validated['cover_image_path'] = $coverPath;
        $book = Book::create($validated);

        return redirect()->route('catalog.admin.index')
            ->with('status', "\"{$book->title}\" was added to the catalog.");
    }

    public function edit(Book $book): View
    {
        $categories = BookCategory::query()
            ->where('is_active', true)
            ->orWhereKey($book->category_id)
            ->orderBy('name')
            ->get();

        return view('catalog.form', compact('book', 'categories'));
    }

    public function update(Request $request, Book $book, ReservationService $reservations)
    {
        $validated = $request->validate($this->bookRules($book));
        $coverPath = $request->file('cover_image')?->storePublicly('book-covers', 'public');
        if ($request->hasFile('cover_image') && ! $coverPath) {
            return back()->withErrors(['cover_image' => 'The cover image could not be saved. Please try again.'])->withInput();
        }

        $removeCover = $request->boolean('remove_cover');
        unset($validated['cover_image'], $validated['remove_cover']);
        if ($coverPath || $removeCover) {
            $validated['cover_image_path'] = $coverPath;
        }

        $unavailableCopies = 0;
        $updated = $book->getConnection()->transaction(function () use ($book, $validated, &$unavailableCopies, $reservations) {
            $lockedBook = Book::query()->lockForUpdate()->findOrFail($book->id);
            $availableCopies = $lockedBook->copies()->where('status', 'available')->count();
            $unavailableCopies = $lockedBook->total_copies - $availableCopies;

            if ($validated['total_copies'] < $unavailableCopies) {
                return false;
            }

            $copyDifference = $validated['total_copies'] - $lockedBook->total_copies;

            if ($copyDifference < 0) {
                $copyIds = $lockedBook->copies()
                    ->where('status', 'available')
                    ->orderByDesc('copy_number')
                    ->limit(abs($copyDifference))
                    ->pluck('id');
                $lockedBook->copies()->whereIn('id', $copyIds)->update(['status' => 'retired']);
            } elseif ($copyDifference > 0) {
                $this->addAvailableCopies($lockedBook, $copyDifference, $reservations);
            }

            $validated['available_copies'] = $validated['total_copies'] - $unavailableCopies;
            $lockedBook->update($validated);

            return true;
        });

        if (! $updated) {
            if ($coverPath) {
                Storage::disk('public')->delete($coverPath);
            }

            return back()->withErrors([
                'total_copies' => "Total copies cannot be lower than the {$unavailableCopies} copies currently unavailable.",
            ])->withInput();
        }

        if ($coverPath || $removeCover) {
            if ($book->cover_image_path) {
                Storage::disk('public')->delete($book->cover_image_path);
            }
        }

        return redirect()->route('catalog.admin.index')->with('status', 'Book details updated.');
    }

    public function destroy(Book $book)
    {
        $removalError = $book->getConnection()->transaction(function () use ($book) {
            $lockedBook = Book::query()->lockForUpdate()->findOrFail($book->id);

            if ($lockedBook->copies()->whereIn('status', ['on_loan', 'reserved', 'unavailable', 'lost'])->exists()) {
                return 'unavailable';
            }

            if ($lockedBook->copies()->whereHas('loans')->exists() || $lockedBook->reservations()->exists()) {
                return 'history';
            }

            $lockedBook->copies()->delete();
            $lockedBook->delete();

            return null;
        });

        if ($removalError === 'unavailable') {
            return back()->withErrors([
                'book' => 'This book cannot be removed while copies are unavailable. Resolve active loans before removal.',
            ]);
        } elseif ($removalError === 'history') {
            return back()->withErrors([
                'book' => 'This book cannot be removed because its copy history is part of the loan record.',
            ]);
        }

        return redirect()->route('catalog.admin.index')->with('status', 'Book removed from the catalog.');
    }

    public function copies(Book $book): View
    {
        $copies = $book->copies()->orderBy('copy_number')->paginate(30);

        return view('catalog.copies', compact('book', 'copies'));
    }

    public function updateCopyStatus(Request $request, Book $book, BookCopy $copy, ReservationService $reservations)
    {
        abort_unless($copy->book_id === $book->id, 404);

        $validated = $request->validate([
            'status' => ['required', Rule::in(['available', 'unavailable', 'lost'])],
        ]);

        $updated = $book->getConnection()->transaction(function () use ($book, $copy, $validated, $reservations) {
            $lockedBook = Book::query()->lockForUpdate()->findOrFail($book->id);
            $lockedCopy = BookCopy::query()->lockForUpdate()->findOrFail($copy->id);

            if (in_array($lockedCopy->status, ['on_loan', 'reserved', 'retired'], true)) {
                return false;
            }

            $wasAvailable = $lockedCopy->status === 'available';
            $willBeAvailable = $validated['status'] === 'available';
            $lockedCopy->update(['status' => $validated['status']]);

            if ($wasAvailable !== $willBeAvailable) {
                $lockedBook->update([
                    'available_copies' => $lockedBook->available_copies + ($willBeAvailable ? 1 : -1),
                ]);
            }

            if (! $wasAvailable && $willBeAvailable) {
                $reservations->allocateNext($lockedBook, $lockedCopy);
            }

            return true;
        });

        if (! $updated) {
            return back()->withErrors(['copy' => 'Copies on loan or retired cannot be changed here.']);
        }

        return back()->with('status', 'Copy status updated.');
    }

    public function categories(): View
    {
        $categories = BookCategory::query()
            ->withCount('books')
            ->orderBy('name')
            ->paginate(20);

        return view('catalog.categories', compact('categories'));
    }

    public function storeCategory(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:book_categories,name'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);
        $validated['is_active'] = true;

        BookCategory::create($validated);

        return redirect()->route('catalog.categories.index')->with('status', 'Category created.');
    }

    public function updateCategory(Request $request, BookCategory $category)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('book_categories', 'name')->ignore($category->id)],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['required', 'boolean'],
        ]);

        $category->update($validated);

        return redirect()->route('catalog.categories.index')->with('status', 'Category updated.');
    }

    private function bookRules(?Book $book = null): array
    {
        $allowedCategoryIds = BookCategory::query()
            ->where('is_active', true)
            ->when($book, fn ($query) => $query->orWhere('id', $book->category_id))
            ->pluck('id')
            ->all();

        return [
            'isbn' => ['required', 'string', 'max:20', Rule::unique('books', 'isbn')->ignore($book?->id)],
            'title' => ['required', 'string', 'max:255'],
            'authors' => ['required', 'string', 'max:255'],
            'publisher' => ['nullable', 'string', 'max:150'],
            'publication_year' => ['nullable', 'integer', 'between:1000,'.(now()->year + 1)],
            'edition' => ['nullable', 'string', 'max:100'],
            'category_id' => ['required', Rule::in($allowedCategoryIds)],
            'total_copies' => ['required', 'integer', 'min:0', 'max:100000'],
            'cover_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'remove_cover' => ['nullable', 'boolean'],
        ];
    }

    private function addAvailableCopies(Book $book, int $count, ReservationService $reservations): void
    {
        $nextNumber = (int) $book->copies()->max('copy_number') + 1;
        $now = now();
        $copies = [];

        for ($number = $nextNumber; $number < $nextNumber + $count; $number++) {
            $copies[] = [
                'book_id' => $book->id,
                'copy_number' => $number,
                'barcode' => 'LMS-'.$book->id.'-'.str_pad((string) $number, 6, '0', STR_PAD_LEFT),
                'status' => 'available',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($copies, 500) as $chunk) {
            BookCopy::query()->insert($chunk);
        }

        for ($number = $nextNumber; $number < $nextNumber + $count; $number++) {
            $copy = $book->copies()->where('copy_number', $number)->firstOrFail();
            if (! $reservations->allocateNext($book, $copy)) {
                break;
            }
        }
    }
}
