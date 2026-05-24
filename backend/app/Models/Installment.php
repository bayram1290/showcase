<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Installment extends Model
{
    protected static function boot(): void
    {
        parent::boot();

        self::creating(function ($model) {
            $model->installment_uuid = Str::uuid();
        });
    }
    
    protected $fillable = [
        'installment_uuid',
        'loan_account_id',
        'installment_number',
        'due_date',
        'due_amount',
        'principal_amount',
        'interest_amount',
        'paid_date',
        'paid_amount',
        'late_fee',
        'status',
        'repayment_method_id',
    ];

    protected $casts = [
        'due_amount' => 'decimal:2',
        'principal_amount' => 'decimal:2',
        'interest_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'late_fee' => 'decimal:2',
        'due_date' => 'date',
        'paid_date' => 'date'
    ];

    public function getRouteKeyName(): string
    {
        return 'installment_uuid';
    }

    public function loanAccount(): BelongsTo
    {
        return $this->belongsTo(LoanAccount::class);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where('status', 'overdue');
    }

    public function scopeDueToday(Builder $query): Builder
    {
        return $query->where('due_date', today())->where('status', 'pending');
    }

    public function repaymentMethod(): BelongsTo
    {
        return $this->belongsTo(InstallmentRepaymentMethod::class);
    }

    public function markAsPaid(float $amount, int $repaymentMethodId): void
    {
        $new_paid_amount = $this->paid_amount + $amount;

        $this->update([
            'status' => $new_paid_amount < $this->due_amount ? 'partial' : 'paid',
            'paid_amount' => $new_paid_amount,
            'paid_date' => Carbon::now(),
            'repayment_method_id' => $repaymentMethodId
        ]);

        $this->loanAccount->update([
            'installments_paid' => $this->loanAccount->installments()->where('status', 'paid')->count(),
            'next_installment_date' => $this->loanAccount->installments()
                ->where('status', 'pending')
                ->min('due_date')
        ]);

        $paid_principal = min($this->principal_amount, $this->paid_amount);
        $paid_interest = $this->paid_amount - $paid_principal;

        $this->loanAccount->increment('principal_paid', $paid_principal);
        $this->loanAccount->increment('interest_paid', $paid_interest);
        $this->loanAccount->updateOutstandingBalance();

        if ($this->loanAccount->installments()->where('status', '!=', 'paid')->count() === 0) {
            $this->loanAccount->close();
        }
    }

    public function isOverdue($amount = null): bool
    {
        return $this->due_date < today() && $this->status === 'pending';
    }

    public function addLateFee($fee): void
    {
        $this->update([
            'late_fee' => $fee,
            'due_amount' => $this->due_amount + $fee
        ]);
    }
}
