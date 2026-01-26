<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
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
        'device_name'
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

    // public function loanApplication()
    // {
    //     return $this->hasMany(LoanApplication::class);
    // }

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

    public function canManageUsers(): bool
    {
        return $this->role === 'admin';
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
        $this->increment('faoiled_login_attempts');

        if ($this->failed_login_attempts >= 5) {
            $this->update([
                'is_locked' => true
            ]);
        }
    }

}
