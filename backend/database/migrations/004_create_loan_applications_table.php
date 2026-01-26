<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('loan_applications', function (Blueprint $table) {

            $table->id();
            $table->uuid('application_uuid')->unique()->index();
            $table->string('application_ref')->unique();
            $table->decimal('amount', 12, 2);
            $table->integer('tenure')->comment('in months'); // in months
            $table->decimal('interest_rate', 5, 2);
            $table->enum('status', [
                'draft',
                'submitted',
                'under_review',
                'requires_more_info',
                'approved',
                'rejected',
                'cancelled',
                'disbursed',
                'closed'
            ])->default('draft');
            $table->text('purpose')->nullable();
            $table->json('application_data')->nullable()->comment('All application form data'); // Stores all form data
            $table->decimal('monthly_installment', 10, 2)->nullable();
            $table->decimal('total_payable', 12, 2)->nullable();
            $table->decimal('processing_fee', 10, 2)->nullable();
            $table->decimal('insurance_fee', 10, 2)->nullable();
            $table->decimal('total_fees', 10, 2)->nullable();
            $table->json('review_notes')->nullable();
            $table->integer('review_score')->nullable()->comment('0-100 score based on eligibility');
            $table->string('disbursement_method')->nullable();
            $table->string('bank_account_number')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('bank_iban')->nullable()->comment('International Bank Account Number');
            $table->text('rejection_reason')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('disbursed_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreignId('borrower_id')->constrained('borrowers')->onDelete('cascade');
            $table->foreignId('loan_product_id')->constrained()->onDelete('cascade');
            $table->foreignId('assigned_officer_id')->nullable()->constrained('users');

            $table->index(['status', 'created_at']);
            $table->index('borrower_id');
            $table->index('assigned_officer_id');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_applications');
    }
};
