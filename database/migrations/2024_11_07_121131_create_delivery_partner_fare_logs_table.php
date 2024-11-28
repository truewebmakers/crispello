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
        Schema::create('delivery_partner_fare_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('delivery_partner_id')->nullable();
            $table->foreign('delivery_partner_id')->references('_id')->on('users');
            $table->unsignedBigInteger('order_id')->nullable();
            $table->foreign('order_id')->references('_id')->on('orders');
            $table->string('pickup_lat',191)->nullable();
            $table->string('pickup_long',191)->nullable();
            $table->string('destination_lat',191)->nullable();
            $table->string('destination_long',191)->nullable();
            $table->string('total_km',191)->nullable();
            $table->string('total_fare',191)->nullable();
            $table->enum('currency', ['SAR', 'USD','INR','AUD','AED'])->default('SAR');
            $table->enum('status',['pending','credit','in-progress','withdraw'])->nullable();
            // $table->enum('status',['delivered','in-progress','out-of-delivery'])->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_partner_fare_logs');
    }
};
