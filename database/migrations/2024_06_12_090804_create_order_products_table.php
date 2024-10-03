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
        Schema::create('order_products', function (Blueprint $table) {
            $table->id('_id');
            $table->unsignedBigInteger('order_id')->nullable();
            $table->mediumText('name')->nullable();
            $table->mediumText('size')->nullable();
            $table->tinyText('price')->nullable();
            $table->tinyText('quantity')->nullable();
            $table->boolean('veg')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('combo_id')->nullable();
            $table->unsignedBigInteger('size_id')->nullable();
            $table->foreign('order_id')->references('_id')->on('orders');
            $table->foreign('product_id')->references('_id')->on('products');
            $table->foreign('combo_id')->references('_id')->on('combos');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_products');
    }
};
