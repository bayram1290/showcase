<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('installments', function (Blueprint $table) {
            $table->uuid('installment_uuid')->unique()->index()->nullable()->after('id');
        });

        DB::table('installments')
            ->whereNull('installment_uuid')
            ->orderBy('id')
            ->each(function ($installment) {
                DB::table('installments')
                    ->where('id', $installment->id)
                    ->update(['installment_uuid' => (string) Str::uuid()]);
            });

        // Make the column NOT NULL after population
        Schema::table('installments', function (Blueprint $table) {
            $table->uuid('installment_uuid')->nullable(false)->change();
        });
    }

    public function down()
    {
        Schema::table('installments', function (Blueprint $table) {
            $table->dropColumn('installment_uuid');
        });
    }
};
