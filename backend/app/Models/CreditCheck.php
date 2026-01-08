<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditCheck extends Model
{
    
    protected $fillable = [
        'loan_application_id',
        'credit_score',
        'credit_report_data',
        'debt_to_income_ratio',
        'remarks',
        'checked_by'
    ];

    protected $casts = [
        'credit_report_data' => 'array',
        'debt_to_income_ratio' => 'decimal:2',
    ];

    public function loanApplication(): BelongsTo
    {
        return $this->belongsTo(LoanApplication::class);
    }

    public function checkedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by');
    }

     public function getCreditRatingAttribute(): string
    {
        if ($this->credit_score >= 750) return 'Excellent';
        if ($this->credit_score >= 700) return 'Good';
        if ($this->credit_score >= 650) return 'Fair';
        if ($this->credit_score >= 600) return 'Insufficient';
        return 'None';
    }

    public function isEligible(int $minScore = 650): bool
    {
        return $this->credit_score >= $minScore;
    }
}
