<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::create([
            'name' => 'Admin User',
            'login' => 'bank_administrator',
            'password' => Hash::make('admin123'),
            'role' => 'admin',
            'phone' => '1234567890',
            'is_verified' => true
        ]);

        User::create([
            'name' => 'Loan Officer',
            'login' => 'bank_officer',
            'password' => Hash::make('officer123'),
            'role' => 'loan_officer',
            'phone' => '0987654321',
            'is_verified' => true
        ]);
    }
}
