<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BookReview extends Model
{
    protected $fillable = ['book_id', 'user_id', 'rating', 'comment', 'hidden_at', 'hidden_by'];

    protected function casts(): array
    {
        return ['rating' => 'integer', 'hidden_at' => 'datetime'];
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(BookReviewReport::class, 'book_review_id');
    }

    public function hasVerifiedLoan(): bool
    {
        return $this->member->loans()
            ->whereHas('copy', fn ($query) => $query->where('book_id', $this->book_id))
            ->exists();
    }
}
