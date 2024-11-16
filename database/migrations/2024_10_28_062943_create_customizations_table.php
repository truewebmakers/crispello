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
        Schema::create('customizations', function (Blueprint $table) {
            $table->id('_id');
            $table->tinyText('name')->nullable();
            $table->tinyText('price')->nullable();
            $table->boolean('veg')->default(1)->comment('1:Veg 0:Non Veg')->nullable();
            $table->boolean('is_available')->default(0)->comment('0:not available,1:available')->nullable();
            $table->enum('type', ['Toppings'])->nullable();
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->foreign('admin_id')->references('_id')->on('admins');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customizations');
    }
};
