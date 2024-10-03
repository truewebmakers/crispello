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
        Schema::create('feedback', function (Blueprint $table) {
            $table->id('_id');
            $table->mediumText('feedback')->nullable();
            $table->string('rating', 1)->nullable();
            $table->mediumText('reply')->nullable();
            $table->timestamp('reply_time')->useCurrent()->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('combo_id')->nullable();
            $table->foreign('user_id')->references('_id')->on('users');
            $table->foreign('combo_id')->references('_id')->on('combos');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('feedback');
    }
};
