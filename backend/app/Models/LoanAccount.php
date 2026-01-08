<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoanAccount extends Model
{
    protected $fillable = [
        'loan_application_id',
        'account_number',
        'disbursed_amount',
        'outstanding_balance',
        'principal_paid',
        'interest_paid',
        'installments_paid',
        'next_installment_date',
        'status',
        'closed_at'
    ];

    protected $casts = [
        'disbursed_amount' => 'decimal:2',
        'outstanding_balance' => 'decimal:2',
        'principal_paid' => 'decimal:2',
        'interest_paid' => 'decimal:2',
        'next_installment_date' => 'date',
        'closed_at' => 'date'
    ];

    public function loanApplication(): BelongsTo
    {
        return $this->belongsTo(LoanApplication::class);
    }

    public function installments(): HasMany
    {
        return $this->hasMany(Installment::class);
    }

    public function generateAccountNumber(): string
    {
        return  'LN'. date('Ymd') . str_pad($this->id, 8, '0', STR_PAD_LEFT);
    }

    public function calculateOutstandingBalance(): float
    {
        $total_installment_amount = $this->installments()
                                ->where('status', 'paid')
                                ->sum('paid_amount');

        $outstanding_balance = $this->disbursed_amount - $total_installment_amount;

        return (float) round($outstanding_balance, 2);
    }

    public function updateOutstandingBalance(): void
    {
        $this->outstanding_balance = $this->calculateOutstandingBalance();
        $this->save();
    }

    public function disburse($amount): void
{

        $this->update([
            'disbursed_amount' => $amount,
            'outstanding_balance' => $amount,
            'status' => 'active'
        ]);

        $this->createInstallments();
    }

    private function createInstallments(): void
    {
        $application = $this->loanApplication;
        $installment_amount = $application->monthly_installment;
        $principal_per_installment = $application->amount / $application->tenure;
        $interest_per_installment = $installment_amount - $principal_per_installment;

        for ($j = 1; $j <= $application->tenure; $j++) {
            Installment::create([
                'loan_account_id' => $this->id,
                'installment_number' => $j,
                'due_date' => now()->addMonths($j)->startOfMonth()->addDays(5),
                'due_amount' => $installment_amount,
                'principal_amount' => $principal_per_installment,
                'interest_amount' => $interest_per_installment,
                'status' => 'pending'
            ]);
        }

        $this->update([
            'next_installment_date' => now()->addMonths(1)->startOfMonth()->addDays(5)
        ]);
    }

    public function scopeActive($query): Builder
    {
        return $query->where('status', 'active');
    }
}
