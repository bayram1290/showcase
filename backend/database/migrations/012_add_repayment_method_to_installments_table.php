<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('installments', function (Blueprint $table) {
            $table->foreignId('repayment_method_id')
                  ->nullable()
                  ->after('paid_amount')
                  ->constrained('installment_repayment_methods')
                  ->nullOnDelete();
        });
    }

    public function down()
    {
        Schema::table('installments', function (Blueprint $table) {
            $table->dropForeign(['repayment_method_id']);
            $table->dropColumn('repayment_method_id');
        });
    }
};