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
        Schema::create('loan_statements', function (Blueprint $table) {
            $table->id();
            $table->string('statement_period'); // e.g., "January 2024"
            $table->string('file_path');
            $table->timestamp('generated_at');
            $table->timestamps();

            $table->foreignId('loan_account_id')->constrained()->onDelete('cascade');
            $table->unique(['loan_account_id', 'statement_period']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_statements');
    }
};
