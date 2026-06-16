<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankBranch extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'address',
        'district',
        'phones',
        'fax',
        'is_headquarters',
        'is_active',
    ];

    protected $casts = [
        'is_headquarters' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function isHeadquarters(): bool
    {
        return $this->id == 1 || $this->is_headquarters;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
