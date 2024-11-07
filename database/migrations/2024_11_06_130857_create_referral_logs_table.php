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
        Schema::create('referral_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('referral_code_id')->nullable();
            $table->foreign('referral_code_id')->references('id')->on('referral_codes');

            $table->unsignedBigInteger('referrer_user_id')->nullable();
            $table->foreign('referrer_user_id')->references('_id')->on('users');

            $table->unsignedBigInteger('referred_user_id')->nullable();
            $table->foreign('referred_user_id')->references('_id')->on('users');

            $table->integer('points')->nullable();

            $table->enum('status', ['credit', 'spent'])->default('credit');


            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('referral_logs');
    }
};
