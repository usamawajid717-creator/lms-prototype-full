<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LibraryPolicy extends Model
{
    public const SINGLETON_ID = 1;

    protected $fillable = [
        'loan_period_days',
        'max_active_loans',
        'max_renewals',
        'fine_per_day_pkr',
        'issue_fine_limit_pkr',
        'max_active_reservations',
        'reservation_hold_days',
        'due_reminder_days',
    ];

    protected function casts(): array
    {
        return [
            'loan_period_days' => 'integer',
            'max_active_loans' => 'integer',
            'max_renewals' => 'integer',
            'fine_per_day_pkr' => 'integer',
            'issue_fine_limit_pkr' => 'integer',
            'max_active_reservations' => 'integer',
            'reservation_hold_days' => 'integer',
            'due_reminder_days' => 'integer',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate(
            ['id' => self::SINGLETON_ID],
            [
                'loan_period_days' => 14,
                'max_active_loans' => 5,
                'max_renewals' => 2,
                'fine_per_day_pkr' => 50,
                'issue_fine_limit_pkr' => 0,
                'max_active_reservations' => 5,
                'reservation_hold_days' => 2,
                'due_reminder_days' => 2,
            ],
        );
    }
}
