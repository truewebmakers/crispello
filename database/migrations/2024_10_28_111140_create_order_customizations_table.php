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
        Schema::create('order_customizations', function (Blueprint $table) {
            $table->id('_id');
            $table->tinyText('name')->nullable();
            $table->tinyText('price')->nullable();
            $table->boolean('veg')->default(1)->comment('1:Veg 0:Non Veg')->nullable();
            $table->enum('type', ['Toppings'])->nullable();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->foreign('order_id')->references('_id')->on('orders');
            $table->unsignedBigInteger('order_product_id')->nullable();
            $table->foreign('order_product_id')->references('_id')->on('order_products');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_customizations');
    }
};
