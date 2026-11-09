<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FineAdjustment extends Model
{
    protected $fillable = [
        'fine_id',
        'admin_id',
        'action',
        'previous_amount_pkr',
        'new_amount_pkr',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'previous_amount_pkr' => 'integer',
            'new_amount_pkr' => 'integer',
        ];
    }

    public function fine(): BelongsTo
    {
        return $this->belongsTo(Fine::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
