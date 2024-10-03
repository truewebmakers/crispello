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
        Schema::create('combo_details', function (Blueprint $table) {
            $table->unsignedBigInteger('combo_id');
            $table->unsignedBigInteger('product_id');
            $table->integer('quantity')->default(1);
            $table->unsignedBigInteger('size')->nullable();
            $table->primary(['combo_id', 'product_id']);
            $table->foreign('combo_id')->references('_id')->on('combos');
            $table->foreign('product_id')->references('_id')->on('products');
            $table->foreign('size')->references('_id')->on('product_sizes');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('combo_details');
    }
};
