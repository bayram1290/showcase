<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Str;

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
        'user_id',
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
        'disbursed_at'
    ];

    protected $casts = [
        'application_data' => 'array',
        'amount' => 'decimal:2',
        'interest_rate' => 'decimal:2',
        'monthly_installment' => 'decimal:2',
        'total_payable' => 'decimal:2',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'disbursed_at' => 'datetime'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function loanProduct()
    {
        return $this->belongsTo(LoanProduct::class);
    }

    public function assignedOfficer()
    {
        return $this->belongsTo(User::class, 'assigned_officer_id');
    }

    public function documents()
    {
        return $this->hasMany(Document::class);
    }

    public function creditCheck()
    {
        return $this->hasOne(CreditCheck::class);
    }

    public function loanAccount()
    {
        return $this->hasOne(LoanAccount::class);
    }

    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class);
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'submitted');
    }

    public function scopeUnderReview($query)
    {
        return $query->where('status', 'under_review');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function calculateMonthlyInstallment()
    {
        $principal = $this->amount;
        $monthlyRate = ($this->interest_rate / 100) / 12;
        $months = $this->tenure;

        if ($monthlyRate == 0) {
            return $principal / $months;
        }

        return ($principal * $monthlyRate * pow(1 + $monthlyRate, $months)) / (pow(1 + $monthlyRate, $months) - 1);
    }

    public function calculateTotalPayable()
    {
        return $this->calculateMonthlyInstallment() * $this->tenure;
    }

    public function generateApplicationRef()
    {
        return 'APP' . date('Ymd') . str_pad($this->id, 6, '0', STR_PAD_LEFT);
    }
}
