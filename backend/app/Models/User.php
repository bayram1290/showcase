<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;


class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'login',
        'password',
        'role',
        'phone',
        'date_of_birth',
        'address',
        'city',
        'state',
        'zip_code',
        'country',
        'monthly_income',
        'employment_status',
        'employer_name',
        'employment_duration',
        'ssn',
        'is_verified'
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'ssn'
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'monthly_income' => 'decimal:2',
        'is_verified' => 'boolean',
        'application_data' => 'array'
    ];


    public function loanApplication()
    {
        return $this->hasMany(LoanApplication::class);
    }

    public function assignedApplications()
    {
        return $this->hasMany(LoanApplication::class, 'assigned_officer_id');
    }

    public function isCustomer()
    {
        return $this->role === 'customer';
    }

    public function isLoanOfficer()
    {
        return $this->role === 'loan_officer';
    }

    public function isAdmin()
    {
        return $this->role === 'admin';
    }
}
