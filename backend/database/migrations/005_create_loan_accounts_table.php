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
        Schema::create('loan_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('account_number')->unique();
            $table->decimal('disbursed_amount', 12, 2);
            $table->decimal('outstanding_balance', 12, 2);
            $table->decimal('principal_paid', 12, 2)->default(0);
            $table->decimal('interest_paid', 12, 2)->default(0);
            $table->integer('installments_paid')->default(0);
            $table->date('next_installment_date')->nullable();
            $table->enum('status', ['active', 'closed', 'defaulted', 'settled'])->default('active');
            $table->date('closed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreignId('loan_application_id')->constrained()->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_accounts');
    }
};
