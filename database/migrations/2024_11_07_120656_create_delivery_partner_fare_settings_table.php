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
        Schema::create('delivery_partner_fare_settings', function (Blueprint $table) {
            $table->id();
            $table->decimal('fare_per_km', 8, 2);
            $table->enum('currency', ['SAR', 'USD','INR','AUD','AED'])->default('SAR');

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
        Schema::dropIfExists('delivery_partner_fare_settings');
    }
};
