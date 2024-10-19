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
        Schema::create('product_sizes', function (Blueprint $table) {
            $table->id('_id');
            $table->mediumText('size')->nullable();
            // $table->tinyText('actual_price')->nullable();
            // $table->tinyText('selling_price')->nullable();
            $table->tinyText('delivery_actual_price')->nullable();
            $table->tinyText('delivery_selling_price')->nullable();
            $table->tinyText('pickup_actual_price')->nullable();
            $table->tinyText('pickup_selling_price')->nullable();
            $table->tinyText('dinein_actual_price')->nullable();
            $table->tinyText('dinein_selling_price')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->foreign('product_id')->references('_id')->on('products');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_sizes');
    }
};
