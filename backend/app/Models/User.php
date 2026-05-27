<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;


class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;
    protected $table = "users";

    protected $fillable = [
        'login',
        'email',
        'password',
        'first_name',
        'last_name',
        'phone',
        'role',
        'department',
        'employee_id',
        'date_of_joining',
        'is_active',
        'is_locked',
        'last_login',
        'device_name',
        'failed_login_attempts'
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_locked' => 'boolean',
        'last_login' => 'datetime',
        'date_of_joining' => 'date',
        'password_changed' => 'datetime',
    ];

    public function assignedApplications(): HasMany
    {
        return $this->hasMany(LoanApplication::class, 'assigned_officer_id');
    }

    public function verifiedDocuments(): HasMany
    {
        return $this->hasMany(Document::class, 'verified_by');
    }

    public function creditChecks(): HasMany
    {
        return $this->hasMany(CreditCheck::class, 'checked_by');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'user_id');
    }

    public function getFullNameAttribute(): string
    {
        return $this->first_name . ' ' . $this->last_name;
    }

    public function isAdmin()
    {
        return $this->role === 'admin';
    }

    public function isLoanOfficer()
    {
        return $this->role === 'loan_officer';
    }

    public function isModerator()
    {
    return $this->role === 'moderator';
    }

    public function canReviewApplications(): bool
    {
        return in_array($this->role, ['admin', 'loan_officer']);
    }

    public function canVerifyDocuments(): bool
    {
        return $this->role === 'loan_officer';
    }

    public function canManageUsers(): bool
    {
        return $this->role === 'admin';
    }

    public static function scopeActive(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where('is_locked', false);
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
            $this->update([
                'is_locked' => true
            ]);
        }
    }

    public function getAdminPublicField(string $field): mixed
    {
        $admin_public_fields = User::where('role', 'admin')->first()->only(['email', 'first_name', 'last_name', 'department']);

        if (!isset($admin_public_fields[$field])) {
            return null;
        }

        return $admin_public_fields[$field];
    }

    public function getSystemManagers(): Builder
    {
        return self::active()->where('role', 'manager');
    }
}
