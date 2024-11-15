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
        Schema::create('cart_products', function (Blueprint $table) {
            $table->id('_id');
            $table->unsignedBigInteger('cart_id')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('combo_id')->nullable();
            $table->integer('quantity')->default(1)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->mediumText('customization')->nullable();
            $table->boolean('is_update')->default(0)->comment('1:update cart 0:not update cart')->nullable();
            $table->foreign('cart_id')->references('_id')->on('carts');
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
        Schema::dropIfExists('cart_products');
    }
};
