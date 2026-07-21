<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Negotiation extends Model
{
    protected $fillable = [
        'loan_account_id',
        'type',
        'note',
        'terms',
        'accepted_amount',
        'expires_at',
        'is_active',
        'expired_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'expired_at' => 'datetime',
        'is_active' => 'boolean',
        'accepted_amount' => 'decimal:2',
        'terms' => 'array',
    ];

    public function loanAccount(): BelongsTo
    {
        return $this->belongsTo(LoanAccount::class);
    }
}