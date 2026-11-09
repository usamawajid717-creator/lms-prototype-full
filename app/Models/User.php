<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'cnic',
        'phone',
        'address',
        'profile_photo_path',
        'sms_notifications_enabled',
        'membership_type',
        'membership_status',
        'membership_expires_at',
        'library_card_number',
    ];

    protected $hidden = ['password', 'remember_token'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'membership_expires_at' => 'date',
            'sms_notifications_enabled' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    public function fines(): HasManyThrough
    {
        return $this->hasManyThrough(Fine::class, Loan::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function bookRequests(): HasMany
    {
        return $this->hasMany(BookRequest::class);
    }

    public function bookReviews(): HasMany
    {
        return $this->hasMany(BookReview::class);
    }

    public function courseReadingLists(): HasMany
    {
        return $this->hasMany(CourseReadingList::class);
    }

    public function pushSubscriptions(): HasMany
    {
        return $this->hasMany(PushSubscription::class);
    }

    public function getEffectiveMembershipStatusAttribute(): string
    {
        if ($this->membership_status === 'suspended') {
            return 'suspended';
        }

        if ($this->membership_expires_at?->isBefore(today())) {
            return 'expired';
        }

        return 'active';
    }
}
