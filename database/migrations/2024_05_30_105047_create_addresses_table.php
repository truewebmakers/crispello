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
        Schema::create('addresses', function (Blueprint $table) {
            $table->id('_id');
            $table->mediumText('location')->nullable();
            $table->tinyText('latitude')->nullable();
            $table->tinyText('longitude')->nullable();
            $table->mediumText('house_no')->nullable();
            $table->mediumText('area')->nullable();
            $table->mediumText('options_to_reach')->nullable();
            $table->tinyText('save_as')->nullable();
            $table->boolean('is_default')->default(0)->comment('0:not default 1:default')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->foreign('user_id')->references('_id')->on('users');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
