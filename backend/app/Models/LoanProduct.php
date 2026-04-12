<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LoanProduct extends Model
{

    use SoftDeletes;
    protected $fillable = [
        'name',
        'description',
        'min_amount',
        'max_amount',
        'interest_rate',
        'interest_type',
        'min_tenure',
        'max_tenure',
        'type',
        'eligibility_criteria',
        'required_documents',
        'processing_fee_percentage',
        'late_fee',
        'is_active',
        'deleted_at'
    ];

    protected $casts = [
        'min_amount' => 'decimal:2',
        'max_amount' => 'decimal:2',
        'interest_rate' => 'decimal:2',
        'processing_fee_percentage' => 'decimal:2',
        'late_fee' => 'decimal:2',
        'eligibility_criteria' => 'array',
        'required_documents' => 'array',
        'is_active' => 'boolean'
    ];

    protected $dates = ['deleted_at'];

    public function loanApplications(): HasMany
    {
        return $this->hasMany(LoanApplication::class);
    }

    public function scopeActive($query): Builder
    {
        return $query->where('is_active', true);
    }

    public function getMonthlyInterestRateAttribute(): float|int
    {
        return $this->interest_rate / 12 / 100;
    }

    public function isValidForLoan($amount, $tenure): bool
    {
        return $amount >= $this->min_amount &&
               $amount <= $this->max_amount &&
               $tenure >= $this->min_tenure &&
               $tenure <= $this->max_tenure;
    }
}
