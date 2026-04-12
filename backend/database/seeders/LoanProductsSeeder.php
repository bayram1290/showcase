<?php

namespace Database\Seeders;

use App\Models\LoanProduct;
use Illuminate\Database\Seeder;

class LoanProductsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $products = [
            [
                'name' => 'Personal Loan',
                'description' => 'Unsecured personal loan for various purposes',
                'min_amount' => 1000,
                'max_amount' => 50000,
                'interest_rate' => 12.5,
                'interest_type' => 'fixed',
                'min_tenure' => 12,
                'max_tenure' => 60,
                'type' => 'personal',
                'eligibility_criteria' => [
                    'min_income' => 2500,
                    'min_age' => 21,
                    'max_age' => 60,
                    'employment_status' => ['salaried', 'self_employed']
                ],
                'required_documents' => [
                    'identity',
                ],
                'processing_fee_percentage' => 2,
                'late_fee' => 500
            ],
            [
                'name' => 'Home Mortgage',
                'description' => 'Loan for purchasing residential property',
                'min_amount' => 50000,
                'max_amount' => 5000000,
                'interest_rate' => 8.5,
                'interest_type' => 'fixed',
                'min_tenure' => 60,
                'max_tenure' => 240,
                'type' => 'mortgage',
                'eligibility_criteria' => [
                    'min_income' => 50000,
                    'min_age' => 25,
                    'max_age' => 65,
                    'employment_status' => ['salaried', 'self_employed']
                ],
                'required_documents' => [
                    'identity',
                    'address',
                    'income',
                    'property_documents',
                    'bank_statements'
                ],
                'processing_fee_percentage' => 1.5,
                'late_fee' => 1000
            ],
            [
                'name' => 'Auto Loan',
                'description' => 'Loan for purchasing new or used vehicles',
                'min_amount' => 100000,
                'max_amount' => 2000000,
                'interest_rate' => 9.5,
                'interest_type' => 'fixed',
                'min_tenure' => 12,
                'max_tenure' => 84,
                'type' => 'auto',
                'eligibility_criteria' => [
                    'min_income' => 30000,
                    'min_age' => 21,
                    'max_age' => 65,
                    'employment_status' => ['salaried', 'self_employed']
                ],
                'required_documents' => [
                    'identity',
                    'address',
                    'income',
                    'vehicle_details',
                    'bank_statements'
                ],
                'processing_fee_percentage' => 1.8,
                'late_fee' => 750
            ]
        ];

        foreach ($products as $product) {
            LoanProduct::create($product);
        }
    }
}
