<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class LoanApplication extends Model
{
    use HasFactory;

    protected static function boot(): void {
        parent::boot();

        self::creating(function ($model) {
            $model->application_uuid = Str::uuid();
        });
    }

    protected $fillable = [
        'loan_product_id',
        'application_ref',
        'amount',
        'tenure',
        'interest_rate',
        'status',
        'purpose',
        'application_data',
        'monthly_installment',
        'total_payable',
        'assigned_officer_id',
        'rejection_reason',
        'submitted_at',
        'approved_at',
        'disbursed_at',
        'processing_fee',
        'insurance_fee',
        'total_fees',
        'review_notes',
        'review_score',
        'disbursement_method',
        'bank_account_number',
        'bank_branch',
        'bank_iban',
        'reviewed_at',
        'closed_at',
        'borrower_id',
        'loan_type'
    ];

    protected $casts = [
        'application_data' => 'array',
        'amount' => 'decimal:2',
        'interest_rate' => 'decimal:2',
        'monthly_installment' => 'decimal:2',
        'total_payable' => 'decimal:2',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'disbursed_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'closed_at' => 'datetime'
    ];

    public function getRouteKeyName(): string
    {
        return 'application_uuid';
    }

    // Relationships
    public function borrower(): BelongsTo
    {
        return $this->belongsTo(Borrower::class);
    }

    public function loanProduct(): BelongsTo
    {
        return $this->belongsTo(LoanProduct::class);
    }

    public function assignedOfficer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_officer_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function creditCheck(): HasOne
    {
        return $this->hasOne(CreditCheck::class);
    }

    public function loanAccount(): HasOne
    {
        return $this->hasOne(LoanAccount::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function bankBranch(): BelongsTo
    {
        return $this->belongsTo(BankBranch::class, 'bank_branch');
    }


    // Scopes
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'submitted');
    }

    public function scopeUnderReview(Builder $query): Builder
    {
        return $query->where('status', 'under_review');
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'approved');
    }

    // Accessors and Mutators

    /**
     * Calculate the monthly installment amount for a loan based on the principal amount, interest rate, and tenure.
     *
     * @return float The monthly installment amount rounded down to the nearest whole number.
     */
    public function calculateMonthlyInstallment(): float
    {
        $principal = $this->amount;
        $monthly_rate = ($this->interest_rate / 100) / 12;
        $months = $this->tenure;

        $base_monthly_installment = $monthly_rate == 0 ? ($principal / $months) : $base_monthly_installment = ($principal * $monthly_rate * pow(1 + $monthly_rate, $months)) / (pow(1 + $monthly_rate, $months) - 1);

        return floor($base_monthly_installment);
    }

    public function calculateTotalPayable()
    {
        return $this->calculateMonthlyInstallment() * $this->tenure;
    }

    public function generateApplicationRef(bool $force = false): string
    {
        $ref = 'APP' . date($force ? 'YmdHis': 'Ymd') . str_pad($this->id, 6, '0', STR_PAD_LEFT);
        if (LoanApplication::where('application_ref', $ref)->exists()) {
            return $this->generateApplicationRef(true);
        }
        return $ref;
    }
}
