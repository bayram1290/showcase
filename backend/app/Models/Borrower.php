<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Notifications\VerifyEmail;
use Laravel\Sanctum\HasApiTokens;

class Borrower extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $table = 'borrowers';

    protected $fillable = [
        'login',
        'email',
        'password',
        'first_name',
        'last_name',
        'phone',
        'date_of_birth',
        'gender',
        'address',
        'city',
        'region',
        'citizenship',
        'postal_code',
        'monthly_income',
        'employment_status',
        'employer_name',
        'employment_duration',
        'occupation',
        'ssn',
        'deleted_at',
        'government_id_number',
        'government_id_type',
        'total_debt',
        'monthly_expenses',
        'is_active',
        'is_blocked',
        'preferred_contact_method',
        'marital_status',
        'dependents'
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'ssn',
        'government_id_number',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'monthly_income' => 'decimal:2',
        'total_debt' => 'decimal:2',
        'monthly_expenses' => 'decimal:2',
        'is_active' => 'boolean',
        'is_blocked' => 'boolean',
        'last_login' => 'datetime',
        'dependents' => 'integer'
    ];

    public function loanApplications(): HasMany
    {
        return $this->hasMany(LoanApplication::class);
    }

    public function loanAccounts(): HasManyThrough
    {
        return $this->hasManyThrough(LoanAccount::class, LoanApplication::class);
    }

    public function documents(): HasManyThrough
    {
        return $this->hasManyThrough(Document::class, LoanApplication::class);
    }



    public function getFullName(): string
    {
        return $this->first_name . ' ' . $this->last_name;
    }

    public function getAge()
    {
        return Carbon::parse($this->date_of_birth)->age;
    }

    public function getDebtToIncomeRatio(): float
    {
        if ($this->monthly_income === 0) {
            return 0;
        }
        return ($this->total_debt / $this->monthly_income) * 100;
    }

    public function getMonthlySavings(): float
    {
        return $this->monthly_income - $this->monthly_expenses;
    }

    public function isEligibleForLoan(int $min_income = 2500, float $max_debt_to_income_ratio = 50): bool
    {
        if (!$this->email_verified_at ||$this->is_blocked)
        {
            return false;
        }

        if ($this->monthly_income < $min_income)
        {
            return false;
        }
        if ($this->debt_to_income_ratio > $max_debt_to_income_ratio)
        {
            return false;
        }

        return true;
    }

    public function recordLogin(): void
    {
        $this->update([
            'last_login' => Carbon::now(),
            'failed_login_attempts' => 0
        ]);
    }

    public function recordFailedLogin(): void
    {
        $this->increment('failed_login_attempts');

        if ($this->failed_login_attempts >= 5) {
            $this->update(['is_blocked' => true]);
        }
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where('is_blocked', false);
    }

    public function scopeWithHighIncome(Builder $query, int $min_income = 5000): Builder
    {
        return $query->where('monthly_income', '>=', $min_income);
    }

    public function scopeByRegion(Builder $query, string $region): Builder
    {
        return $query->where('region', $region);
    }

    protected function gender(): Attribute
    {
        return Attribute::make(
            get: function (?string $value): ?string {
                if ($value === null) {
                    return null;
                }

                return match ($value) {
                    'M' => 'Male',
                    'F' => 'Female',
                };
            },
            set: function (?string $value): ?string {
                if ($value === null) {
                    return null;
                }

                $value = strtolower(trim($value));

                return match ($value) {
                    'male', 'm' => 'M',
                    'female', 'f' => 'F',
                    default => null,
                };
            }
        );
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmail);
    }

}
