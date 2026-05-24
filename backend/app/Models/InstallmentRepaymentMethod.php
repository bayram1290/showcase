<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class InstallmentRepaymentMethod extends Model
{
    protected $fillable = [
        'name',
        'code',
        'description',
        'is_active',
        'borrower_applicable'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'borrower_applicable' => 'boolean',
    ];

    public function installments(): HasMany
    {
        return $this->hasMany(Installment::class);
    }

    /**
     * Scope to filter active payment methods.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter methods that are applicable for borrowers.
     */
    public function scopeApplicableForBorrower(Builder $query): Builder
    {
        return $query->where('borrower_applicable', true);
    }

    /**
     * Get an array of payment method IDs that are active and allowed for borrowers.
     *
     * @return array<int>
     */
    public static function getBorrowerApplicableMethodIDs(): array
    {
        return self::query()->active()->applicableForBorrower()->pluck('id')->values()->all();
    }


    public static function getMethodIDForCode(string $method): int|null
    {
        $method = self::query()->active()->where('code', $method);
        return $method?->first()->id ?? null;
    }
}
