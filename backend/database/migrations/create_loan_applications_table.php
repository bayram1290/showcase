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
            $table->integer('tenure'); // in months
            $table->decimal('interest_rate', 5, 2);
            $table->enum('status', [
                'draft',
                'submitted',
                'under_review',
                'approved',
                'rejected',
                'disbursed',
                'closed'
            ])->default('draft');
            $table->text('purpose')->nullable();
            $table->json('application_data')->nullable(); // Stores all form data
            $table->decimal('monthly_installment', 10, 2)->nullable();
            $table->decimal('total_payable', 12, 2)->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('disbursed_at')->nullable();
            $table->timestamps();

            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('loan_product_id')->constrained()->onDelete('cascade');
            $table->foreignId('assigned_officer_id')->nullable()->constrained('users');

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
