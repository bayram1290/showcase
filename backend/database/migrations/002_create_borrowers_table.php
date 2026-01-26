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
        Schema::create('borrowers', function (Blueprint $table) {
            $table->id();
            $table->string('login')->unique();
            $table->string('password');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->unique();
            $table->string('phone')->unique();
            $table->string('address');
            $table->date('date_of_birth');
            $table->char('gender', 1)->check("gender IN ('M', 'F')")->comment('M - Male, F - Female');
            $table->string('city')->nullable();
            $table->string('region')->nullable();
            $table->string('citizenship')->default('Turkmenistan');
            $table->string('postal_code')->nullable();
            $table->decimal('monthly_income', 12, 2);
            $table->enum('employment_status', ['employed', 'self-employed', 'unemployed', 'retired', 'student']);
            $table->string('employer_name')->nullable();
            $table->integer('employment_duration')->nullable()->comment('in months');
            $table->string('occupation')->nullable();
            $table->string('ssn')->unique();
            $table->string('government_id_number')->unique()->nullable();
            $table->enum('government_id_type', ['passport', 'nic', 'drivers_license', 'eid'])->comment('nic_National_Identity_Card_and_eid_Electronic_ID');
            $table->decimal('total_debt', 12, 2)->default(0);
            $table->decimal('monthly_expenses', 12, 2)->default(0);
            $table->boolean('is_verified')->default(false);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_blocked')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->integer('failed_login_attempts')->default(0);
            $table->timestamp('last_login')->nullable();
            $table->string('preferred_contact_method')->default('email');
            $table->string('marital_status')->nullable();
            $table->integer('dependents')->default(0);
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['city', 'region']);
            $table->index('employment_status');
            $table->index('monthly_income');
            $table->index('is_verified');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('borrowers');
    }
};
