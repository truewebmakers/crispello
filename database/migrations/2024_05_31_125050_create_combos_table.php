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
        Schema::create('combos', function (Blueprint $table) {
            $table->id('_id');
            $table->mediumText('name')->nullable();
            $table->mediumText('image')->nullable();
            $table->tinyText('actual_price',15)->nullable();
            $table->tinyText('selling_price',15)->nullable();
            $table->boolean('veg')->default(1)->comment('1:Veg 0:Non Veg')->nullable();
            $table->boolean('best_seller')->default(0)->comment('0:not best seller,1:best seller')->nullable();
            $table->boolean('recommended')->default(0)->comment('0:not recommended,1:recommended')->nullable();
            $table->boolean('is_available')->default(0)->comment('0:not available,1:available')->nullable();
            $table->boolean('disable')->default(0)->comment('0:not disable ,1:disable')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('combos');
    }
};
