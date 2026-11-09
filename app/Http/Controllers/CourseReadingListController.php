<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\BookCategory;
use App\Models\CourseReadingList;
use App\Models\CourseReadingListItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CourseReadingListController extends Controller
{
    public function index(Request $request): View
    {
        $lists = CourseReadingList::query()
            ->with('faculty:id,name')
            ->withCount('items')
            ->where(function ($query) use ($request): void {
                $query->where('is_published', true);
                if ($this->isFaculty($request)) {
                    $query->orWhere('user_id', $request->user()->id);
                }
            })
            ->orderByDesc('academic_year')
            ->orderBy('course_code')
            ->paginate(18);

        return view('reading-lists.index', [
            'lists' => $lists,
            'canCreate' => $this->isFaculty($request),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($this->isFaculty($request), 403, 'Only faculty members can create course reading lists.');

        $validated = $request->validate([
            'course_code' => ['required', 'string', 'max:50'],
            'course_name' => ['required', 'string', 'max:150'],
            'semester' => ['required', 'string', 'max:50'],
            'academic_year' => ['required', 'integer', 'between:2000,2100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_published' => ['nullable', 'boolean'],
        ]);
        $validated['is_published'] = $request->boolean('is_published');
        $list = $request->user()->courseReadingLists()->create($validated);

        return redirect()->route('reading-lists.show', $list)->with('status', 'Course reading list created.');
    }

    public function show(Request $request, CourseReadingList $readingList): View
    {
        abort_unless(
            $readingList->is_published || $readingList->user_id === $request->user()->id,
            404,
        );

        return view('reading-lists.show', [
            'readingList' => $readingList->load(['faculty:id,name', 'items.book.category']),
            'canManage' => $this->isFaculty($request) && $readingList->user_id === $request->user()->id,
            'books' => Book::query()
                ->whereHas('category', fn ($query) => $query->where('is_active', true))
                ->orderBy('title')
                ->get(['id', 'title', 'authors', 'isbn', 'available_copies']),
        ]);
    }

    public function update(Request $request, CourseReadingList $readingList): RedirectResponse
    {
        $this->authorizeFacultyOwner($request, $readingList);
        $validated = $request->validate([
            'course_code' => ['required', 'string', 'max:50'],
            'course_name' => ['required', 'string', 'max:150'],
            'semester' => ['required', 'string', 'max:50'],
            'academic_year' => ['required', 'integer', 'between:2000,2100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_published' => ['nullable', 'boolean'],
        ]);
        $validated['is_published'] = $request->boolean('is_published');
        $readingList->update($validated);

        return redirect()->route('reading-lists.show', $readingList)->with('status', 'Reading list updated.');
    }

    public function destroy(Request $request, CourseReadingList $readingList): RedirectResponse
    {
        $this->authorizeFacultyOwner($request, $readingList);
        $readingList->delete();

        return redirect()->route('reading-lists.index')->with('status', 'Reading list deleted.');
    }

    public function addBook(Request $request, CourseReadingList $readingList): RedirectResponse
    {
        $this->authorizeFacultyOwner($request, $readingList);
        $validated = $request->validate([
            'book_id' => ['required', 'integer', Rule::exists('books', 'id')->where(function ($query): void {
                $query->whereIn('category_id', BookCategory::query()->select('id')->where('is_active', true));
            })],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($readingList->items()->where('book_id', $validated['book_id'])->exists()) {
            throw ValidationException::withMessages(['book_id' => 'This book is already on the reading list.']);
        }

        $readingList->items()->create([
            'book_id' => $validated['book_id'],
            'position' => (int) $readingList->items()->max('position') + 1,
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()->route('reading-lists.show', $readingList)->with('status', 'Book added to the reading list.');
    }

    public function removeBook(Request $request, CourseReadingList $readingList, CourseReadingListItem $item): RedirectResponse
    {
        $this->authorizeFacultyOwner($request, $readingList);
        abort_unless($item->course_reading_list_id === $readingList->id, 404);
        $item->delete();

        return redirect()->route('reading-lists.show', $readingList)->with('status', 'Book removed from the reading list.');
    }

    private function isFaculty(Request $request): bool
    {
        return $request->user()?->role === 'member'
            && strcasecmp((string) $request->user()->membership_type, 'Faculty') === 0;
    }

    private function authorizeFacultyOwner(Request $request, CourseReadingList $readingList): void
    {
        abort_unless($this->isFaculty($request) && $readingList->user_id === $request->user()->id, 403);
    }
}
