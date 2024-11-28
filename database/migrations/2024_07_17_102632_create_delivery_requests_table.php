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
        Schema::create('delivery_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('driver_id');
            $table->enum('status', ['pending', 'accepted', 'cancelled','rejected','completed'])->default('pending')->nullable();
            $table->primary(['order_id', 'driver_id']);
            $table->foreign('order_id')->references('_id')->on('orders');
            $table->foreign('driver_id')->references('_id')->on('users');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_requests');
    }
};
