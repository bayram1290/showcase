<?php

namespace Database\Seeders;

use App\Models\InstallmentRepaymentMethod;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class InstallmentRepaymentMethodSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $methods = [
            ['name' => 'Bank Transfer', 'code' => 'bank_transfer', 'borrower_applicable' => true],
            ['name' => 'Cash', 'code' => 'cash', 'borrower_applicable' => false],
            ['name' => 'Credit/Debt bank card', 'code' => 'card', 'borrower_applicable' => true],
            ['name' => 'Cheque', 'code' => 'cheque', 'borrower_applicable' => false],
            ['name' => 'Mobile payment', 'code' => 'mobile_payment', 'borrower_applicable' => true],
            ['name' => 'Internet banking', 'code' => 'internet_banking', 'borrower_applicable' => true],
            ['name' => 'Payment terminal', 'code' => 'terminal', 'borrower_applicable' => true],
            ['name' => 'Bank ATM', 'code' => 'atm', 'borrower_applicable' => false],
        ];

        foreach ($methods as $method) {
            InstallmentRepaymentMethod::updateOrCreate(
                ['code' => $method['code']],
                $method
            );
        }
    }
}
