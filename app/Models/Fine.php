<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Fine extends Model
{
    protected $fillable = [
        'loan_id',
        'amount_pkr',
        'paid_at',
        'paid_by',
        'waived_at',
        'waived_by',
        'waived_reason',
    ];

    protected function casts(): array
    {
        return [
            'amount_pkr' => 'integer',
            'paid_at' => 'datetime',
            'waived_at' => 'datetime',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function waiverAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'waived_by');
    }

    public function adjustments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(FineAdjustment::class);
    }

    public function isSettled(): bool
    {
        return $this->paid_at !== null || $this->waived_at !== null;
    }
}
