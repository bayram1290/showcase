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
        Schema::create('users', function (Blueprint $table) {
           $table->id();
           $table->string('login')->unique();
           $table->string('email')->unique();
           $table->string('password');
           $table->string('first_name');
           $table->string('last_name');
           $table->string('phone')->nullable();
           $table->enum('role', ['admin', 'loan_officer', 'moderator'])->default('loan_officer');
           $table->string('department')->nullable();
           $table->string('employee_id')->unique()->nullable();
           $table->date('date_of_joining')->nullable();
           $table->boolean('is_active')->default(true);
           $table->boolean('is_locked')->default(false);
           $table->integer('failed_login_attempts')->default(0);
           $table->timestamp('last_login')->nullable();
           $table->timestamp('password_changed')->nullable();
           $table->string('device_name')->nullable();
           $table->rememberToken();
           $table->timestamps();
           $table->softDeletes();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
