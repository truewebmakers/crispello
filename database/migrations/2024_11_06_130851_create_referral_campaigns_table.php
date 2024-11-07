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
        Schema::create('referral_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->integer('loyalty_points');
            $table->enum('currency', ['SAR', 'USD','INR','AUD','AED'])->default('SAR');
            $table->decimal('points_equal_to', 8, 2);
            $table->enum('condition_install_app', ['yes', 'no']);
            $table->enum('condition_make_purchase', ['yes', 'no']);
            $table->integer('minimum_purchase')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->unsignedBigInteger('added_by')->nullable();
            $table->foreign('added_by')->references('_id')->on('users');
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('referral_campaigns');
    }
};
