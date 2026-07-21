<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('negotiations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_account_id')->constrained()->onDelete('cascade');
            $table->string('type')->nullable();
            $table->text('note');
            $table->json('terms')->nullable();
            $table->decimal('accepted_amount', 12, 2)->nullable();
            $table->timestamp('expires_at');
            $table->boolean('is_active')->default(true);
            $table->timestamp('expired_at')->nullable();
            $table->timestamps();

            $table->index(['loan_account_id', 'is_active']);
            $table->index('expires_at');
        });

        Schema::table('loan_accounts', function (Blueprint $table) {
            $table->json('negotiation_data')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('negotiations');
        Schema::table('loan_accounts', function (Blueprint $table) {
            $table->dropColumn('negotiation_data');
        });
    }
};