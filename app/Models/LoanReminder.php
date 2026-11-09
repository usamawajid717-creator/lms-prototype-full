<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanReminder extends Model
{
    protected $fillable = [
        'loan_id',
        'reminder_type',
        'sent_on',
    ];

    protected function casts(): array
    {
        return [
            'sent_on' => 'date',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }
}
