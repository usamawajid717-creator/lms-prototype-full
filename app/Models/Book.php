<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Book extends Model
{
    protected $fillable = [
        'isbn',
        'title',
        'authors',
        'publisher',
        'publication_year',
        'edition',
        'category_id',
        'cover_image_path',
        'total_copies',
        'available_copies',
    ];

    protected function casts(): array
    {
        return [
            'publication_year' => 'integer',
            'total_copies' => 'integer',
            'available_copies' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (Book $book): void {
            $now = now();
            $copies = [];

            for ($number = 1; $number <= $book->total_copies; $number++) {
                $copies[] = [
                    'book_id' => $book->id,
                    'copy_number' => $number,
                    'barcode' => 'LMS-'.$book->id.'-'.str_pad((string) $number, 6, '0', STR_PAD_LEFT),
                    'status' => $number <= $book->available_copies ? 'available' : 'unavailable',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            foreach (array_chunk($copies, 500) as $chunk) {
                BookCopy::query()->insert($chunk);
            }
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(BookCategory::class, 'category_id');
    }

    public function copies(): HasMany
    {
        return $this->hasMany(BookCopy::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(BookReview::class);
    }

    public function hasAvailableCopies(): bool
    {
        return $this->available_copies > 0;
    }
}
