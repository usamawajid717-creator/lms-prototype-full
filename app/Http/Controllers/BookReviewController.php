<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BookReviewController extends Controller
{
    public function store(Request $request, Book $book): RedirectResponse
    {
        abort_if($request->user()->isAdmin(), 403, 'Only library members can review books.');

        $validated = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $book->reviews()->updateOrCreate(
            ['user_id' => $request->user()->id],
            $validated,
        );

        return redirect()->route('catalog.show', $book)->with('status', 'Your review was saved.');
    }

    public function destroy(Request $request, Book $book): RedirectResponse
    {
        abort_if($request->user()->isAdmin(), 403, 'Only library members can manage book reviews.');

        $book->reviews()->where('user_id', $request->user()->id)->delete();

        return redirect()->route('catalog.show', $book)->with('status', 'Your review was removed.');
    }
}
