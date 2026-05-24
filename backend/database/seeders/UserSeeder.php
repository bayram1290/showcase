<?php

namespace Database\Seeders;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::create([
            'login' => 'admin',
            'email' => 'admin@demo_a.com',
            'password'=> bcrypt('admin123'),
            'first_name' => 'System',
            'last_name' => 'Administrator',
            'role' => 'admin',
            'employee_id' => 'ADM001',
            'department' => 'Adminstration',
            'date_of_joining' => Carbon::now()->subDays(50),
            'is_active' => 1
        ]);

        User::create([
            'login' => 'loanofficer1',
            'email' => 'loanofficer1@demo_a.com',
            'password'=> bcrypt('officer123'),
            'first_name' => 'John',
            'last_name' => 'Smith',
            'role' => 'loan_officer',
            'employee_id' => 'LO001',
            'department' => 'Loan_Department',
            'date_of_joining' => Carbon::now()->subDays(20),
            'is_active' => 1
        ]);

        User::create([
            'login' => 'loanofficer2',
            'email' => 'loanofficer2@demo_a.com',
            'password'=> bcrypt('officer124'),
            'first_name' => 'Sarah',
            'last_name' => 'Johnson',
            'role' => 'loan_officer',
            'employee_id' => 'LO002',
            'department' => 'Loan_Department',
            'date_of_joining' => Carbon::now()->subDays(10),
            'is_active' => 1
        ]);

        User::create([
            'login'=> 'moderator1',
            'email'=> 'moderator1@demo_a.com',
            'password'=> bcrypt('moderator123'),
            'first_name'=> 'Micheal',
            'last_name'=> 'Drown',
            'role'=> 'moderator',
            'employee_id'=> 'MD001',
            'department'=> 'Customer Support',
            'date_of_joining'=> Carbon::now()->subDays(20),
            'is_active' => 1
        ]);

        User::create([
            'login'=> 'officer1',
            'email'=> 'officer3@demo_a.com',
            'password'=> bcrypt('officer1234'),
            'first_name'=> 'Teddy',
            'last_name'=> 'Smith',
            'role'=> 'officer',
            'employee_id'=> 'OFF001',
            'department'=> 'Loan Department',
            'date_of_joining'=> Carbon::now()->subDays(20),
            'is_active' => 1
        ]);

        User::create([
            'login' => 'cashier1',
            'email' => 'cashier1@demo_a.com',
            'password'=> bcrypt('cashier123'),
            'first_name' => 'Natalie',
            'last_name' => 'Garcia',
            'role' => 'cashier',
            'employee_id' => 'CASH001',
            'department' => 'Operations Department',
            'date_of_joining' => Carbon::now()->subDays(20),
            'is_active' => 1
        ]);
    }
}
