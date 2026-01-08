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
        Schema::create('credit_checks', function (Blueprint $table) {
            $table->id();
            $table->integer('credit_score');
            $table->json('credit_report_data')->nullable();
            $table->decimal('debt_to_income_ratio', 5, 2);
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->foreignId('loan_application_id')->constrained()->onDelete('cascade');
            $table->foreignId('checked_by')->nullable()->constrained('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credit_checks');
    }
};
