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
        Schema::create('products', function (Blueprint $table) {
            $table->id('_id');
            $table->mediumText('name')->nullable();
            $table->mediumText('description')->nullable();
            $table->boolean('veg')->default(1)->comment('1:Veg 0:Non Veg')->nullable();
            $table->tinyText('delivery_actual_price')->nullable();
            $table->tinyText('delivery_selling_price')->nullable();
            $table->tinyText('pickup_actual_price')->nullable();
            $table->tinyText('pickup_selling_price')->nullable();
            $table->tinyText('dinein_actual_price')->nullable();
            $table->tinyText('dinein_selling_price')->nullable();
            $table->mediumText('image')->nullable();
            $table->mediumText('customization')->nullable();
            $table->boolean('best_seller')->default(0)->comment('0:not best seller,1:best seller')->nullable();
            $table->boolean('recommended')->default(0)->comment('0:not recommended,1:recommended')->nullable();
            $table->boolean('only_combo')->default(0)->comment('0:regular ,1:only for combo')->nullable();
            $table->boolean('is_available')->default(0)->comment('0:not available,1:available')->nullable();
            $table->boolean('disable')->default(0)->comment('0:not best seller ,1:best seller')->nullable();
            $table->unsignedBigInteger('product_category_id')->nullable();
            $table->foreign('product_category_id')->references('_id')->on('product_categories');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
