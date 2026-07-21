<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

use App\Models\User;
use App\Models\Borrower;
use App\Models\LoanProduct;
use App\Models\LoanApplication;
use App\Models\LoanAccount;
use App\Models\Installment;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ReceivablesTestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Log::info('Starting ReceivablesTestSeeder');
        $borrower = Borrower::firstOrCreate(
            ['login' => 'test_borrower'],
            [
            'login' => 'test_borrower',
            'password' => bcrypt('password'),
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
            'phone' => '99364123456',
            'address' => '123 Main St, Ashgabat',
            'date_of_birth' => '1990-01-01',
            'gender' => 'M',
            'citizenship' => 'Turkmenistan',
            'ssn' => '123456789',
            'government_id_type' => 'passport',
            'government_id_number' => 'AB1234567',
            'monthly_income' => 5000.00,
            'total_debt' => 500.00,
            'monthly_expenses' => 1500.00,
            'employment_status' => 'employed',
            'employer_name' => 'ABC Corp',
            'employment_duration' => 24,
            'occupation' => 'Software Engineer',
            'is_active' => true,
            'is_blocked' => false,
            'email_verified_at' => Carbon::now(),
            'preferred_contact_method' => 'email',
            'marital_status' => 'single',
            'dependents' => 0,
            'failed_login_attempts' => 0,
            'last_login' => Carbon::now(),
        ]);
        Log::info('1');

        $product = LoanProduct::first();
        // if (!$product) {
        //     $product = LoanProduct::create([
        //         'name' => 'Personal Loan',
        //         'description' => 'Test product',
        //         'min_amount' => 1000,
        //         'max_amount' => 50000,
        //         'interest_rate' => 12.5,
        //         'interest_type' => 'fixed',
        //         'min_tenure' => 12,
        //         'max_tenure' => 60,
        //         'type' => 'personal',
        //         'processing_fee_percentage' => 2,
        //         'late_fee' => 50,
        //         'is_active' => true,
        //         'required_documents' => ['identity', 'income'],
        //     ]);
        // }

        Log::info('2');
        $application = LoanApplication::find(4);
        // 4. Create a loan application
        // $application = LoanApplication::create([
        //     'borrower_id' => $borrower->id,
        //     'loan_product_id' => $product->id,
        //     'application_uuid' => (string) Str::uuid(),
        //     'amount' => 5000,
        //     'tenure' => 12,
        //     'interest_rate' => 12.5,
        //     'status' => 'disbursed',
        //     'monthly_installment' => 445.41,
        //     'total_payable' => 5344.97,
        //     'bank_branch' => 3,
        //     'purpose' => 'Personal expenses',
        //     'application_data' => json_encode(['source' => 'seeder']),
        //     'submitted_at' => Carbon::now(),
        //     'approved_at' => Carbon::now(),
        //     'disbursed_at' => Carbon::now(),
        // ]);

        Log::info('3');
        // 5. Create a loan account
        // $loanAccount = LoanAccount::create([
        //     'loan_application_id' => $application->id,
        //     'account_number' => 'TKM-' . strtoupper(Str ::random(8)),
        //     'disbursed_amount' => 5000.00,
        //     'outstanding_balance' => 4583.33,
        //     'principal_paid' => 416.67,
        //     'interest_paid' => 28.74,
        //     'installments_paid' => 1,
        //     'status' => 'active',
        //     'next_installment_date' => Carbon::now()->addMonth(),
        // ]);
        $loanAccount = LoanAccount::where('account_number', 'TKM-NPEPMIGL')->first();

        Log::info('4');
        // 6. Create installments
        $installments = [];

        // Paid installments (first 2)
        for ($i = 1; $i <= 2; $i++) {
            $installments[] = [
                'loan_account_id' => $loanAccount->id,
                'installment_number' => $i,
                'due_date' => Carbon::now()->subDays(60 - ($i * 30)),
                'due_amount' => 445.41,
                'principal_amount' => 400,
                'interest_amount' => 45.41,
                'status' => 'paid',
                'paid_amount' => 445.41,
                'paid_date' => Carbon::now()->subDays(30),
                'late_fee' => 0,
                'repayment_method_id' => null,
                'installment_uuid' => (string) Str::uuid(),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ];
        }
        Log::info('5');

        // Overdue installments (next 2)
        for ($i = 3; $i <= 4; $i++) {
            $installments[] = [
                'loan_account_id' => $loanAccount->id,
                'installment_number' => $i,
                'due_date' => Carbon::now()->subDays(30 + ($i - 3) * 10),
                'due_amount' => 445.41,
                'principal_amount' => 400,
                'interest_amount' => 45.41,
                'status' => 'overdue',
                'paid_amount' => null,
                'paid_date' => null,
                'late_fee' => 50,
                'repayment_method_id' => null,
                'installment_uuid' => (string) Str::uuid(),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ];
        }
        Log::info('6');

        // Pending installments (remaining 8)
        for ($i = 5; $i <= 12; $i++) {
            $installments[] = [
                'loan_account_id' => $loanAccount->id,
                'installment_number' => $i,
                'due_date' => Carbon::now()->addDays(30 * ($i - 4)),
                'due_amount' => 445.41,
                'principal_amount' => 400,
                'interest_amount' => 45.41,
                'status' => 'pending',
                'paid_amount' => null,
                'paid_date' => null,
                'late_fee' => 0,
                'repayment_method_id' => null,
                'installment_uuid' => (string) Str::uuid(),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ];
        }
        Log::info($installments);

        // Installment::insert($installments);
       $this->command->info('Test data for Receivables module created!');
    }
}
