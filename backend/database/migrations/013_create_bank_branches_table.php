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
        Schema::create('bank_branches', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('code', 30)->unique();
            $table->string('address')->nullable();
            $table->string('district', 50)->nullable();
            $table->string('phones', 120)->nullable(); // Each bank has three contact numbers, which will be registered here
            $table->string('fax', 20)->nullable(); //Only one fax numbere to be registered
            $table->boolean('is_headquarters')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_branches');
    }
};
