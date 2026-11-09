<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseReadingListItem extends Model
{
    protected $fillable = ['course_reading_list_id', 'book_id', 'position', 'notes'];

    public function readingList(): BelongsTo
    {
        return $this->belongsTo(CourseReadingList::class, 'course_reading_list_id');
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }
}
