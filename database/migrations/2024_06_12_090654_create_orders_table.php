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
        Schema::create('orders', function (Blueprint $table) {
            $table->id('_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('driver_id')->nullable();
            $table->tinyText('total')->nullable();
            $table->tinyText('delivery_charge')->nullable();
            $table->enum('order_status', ['pending', 'accepted', 'preparing', 'dispatched', 'delivered', 'cancelled'])->default('pending')->nullable();
            $table->timestamp('order_date')->useCurrent()->nullable();
            $table->boolean('paid')->default(0)->nullable()->comment('0:not paid,1: paid')->nullable();
            $table->enum('order_type', ['Dine In', 'Pickup', 'Delivery'])->nullable();
            $table->boolean('payment_method')->nullable()->comment('0:cod or pay at hotel,1:payment gateway')->nullable();
            $table->tinyText('payment_id')->nullable();
            $table->tinyText('table_no')->nullable();
            $table->mediumText('location')->nullable();
            $table->tinyText('longitude')->nullable();
            $table->tinyText('latitude')->nullable();
            $table->mediumText('house_no')->nullable();
            $table->mediumText('area')->nullable();
            $table->mediumText('options_to_reach')->nullable();
            $table->unsignedBigInteger('coupon_id')->nullable();
            $table->foreign('user_id')->references('_id')->on('users');
            $table->foreign('driver_id')->references('_id')->on('delivery_drivers');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
