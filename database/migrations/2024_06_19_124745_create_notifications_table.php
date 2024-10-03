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
        Schema::create('notifications', function (Blueprint $table) {
            $table->id('_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->mediumText('title')->nullable();
            $table->mediumText('body')->nullable();
            $table->mediumText('image')->nullable();
            $table->enum('type', ['order'])->nullable();
            $table->boolean('read')->default(0)->nullable();
            $table->foreign('user_id')->references('_id')->on('users');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
