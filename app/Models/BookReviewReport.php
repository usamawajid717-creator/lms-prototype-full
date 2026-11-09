<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookReviewReport extends Model
{
    protected $fillable = ['book_review_id', 'user_id', 'reason', 'resolved_at', 'resolved_by'];

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime'];
    }

    public function review(): BelongsTo
    {
        return $this->belongsTo(BookReview::class, 'book_review_id');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
