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
        Schema::create('loan_products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description');
            $table->decimal('min_amount', 12, 2);
            $table->decimal('max_amount', 12, 2);
            $table->decimal('interest_rate', 5, 2);
            $table->enum('interest_type', ['fixed', 'variable']);
            $table->integer('min_tenure'); // in months
            $table->integer('max_tenure'); // in months
            $table->enum('type', ['personal', 'mortgage', 'auto', 'business', 'education']);
            $table->json('eligibility_criteria')->nullable();
            $table->json('required_documents')->nullable();
            $table->decimal('processing_fee_percentage', 5, 2)->default(0);
            $table->decimal('late_fee', 8, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_products');
    }
};
