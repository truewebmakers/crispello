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
        Schema::create('coupons', function (Blueprint $table) {
            $table->id('_id');
            $table->tinyText('code')->nullable();
            $table->enum('coupon_type', ['discount', 'net discount'])->nullable();
            $table->integer('discount')->nullable();
            $table->tinyText('threshold_amount')->nullable()->comment('minimum purchase amount');
            $table->mediumText('title')->nullable();
            $table->mediumText('description')->nullable();
            $table->mediumText('more_details', 2000)->nullable();
            $table->date('valid_until')->nullable();
            $table->date('valid_from')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};
