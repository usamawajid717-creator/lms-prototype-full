<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CourseReadingList extends Model
{
    protected $fillable = [
        'user_id',
        'course_code',
        'course_name',
        'semester',
        'academic_year',
        'description',
        'is_published',
    ];

    protected function casts(): array
    {
        return ['academic_year' => 'integer', 'is_published' => 'boolean'];
    }

    public function faculty(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(CourseReadingListItem::class)->orderBy('position');
    }
}
