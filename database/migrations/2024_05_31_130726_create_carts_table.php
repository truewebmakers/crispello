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
        Schema::create('carts', function (Blueprint $table) {
            $table->id('_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('address_id')->nullable();
            $table->unsignedBigInteger('coupon_id')->nullable();
            $table->tinyText('table_no')->nullable();
            $table->enum('order_type', ['Dine In', 'Pickup', 'Delivery'])->default('Delivery')->nullable();
            $table->boolean('payment_method')->default(0)->nullable()->comment('0:cod or pay at hotel,1:payment gateway');
            $table->foreign('user_id')->references('_id')->on('users');
            $table->foreign('address_id')->references('_id')->on('addresses');
            $table->foreign('coupon_id')->references('_id')->on('coupons');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('carts');
    }
};
