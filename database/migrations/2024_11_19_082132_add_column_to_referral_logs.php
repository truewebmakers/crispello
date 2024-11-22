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
        Schema::table('referral_logs', function (Blueprint $table) {

            $table->unsignedBigInteger('point_credit_user_id')->nullable();
            $table->foreign('point_credit_user_id')->references('_id')->on('users');
            $table->float('amount')->default(0);
            $table->float('currency')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('referral_logs', function (Blueprint $table) {
            //
            $table->dropColumn('point_credit_user_id');
            $table->dropColumn('amount');
            $table->dropColumn('currency');
        });
    }
};
