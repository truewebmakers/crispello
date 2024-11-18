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
        Schema::create('referral_codes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('referral_campaign_id')->nullable();
            $table->foreign('referral_campaign_id')->references('id')->on('referral_campaigns');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->foreign('user_id')->references('_id')->on('admins');
            $table->string('code',191)->unique(); // Referral code
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('referral_codes');
    }
};
