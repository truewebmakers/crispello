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
        Schema::create('delivery_fcm_tokens', function (Blueprint $table) {
            $table->string('device_id', 190);
            $table->mediumText('token')->nullable();
            $table->unsignedBigInteger('driver_id')->nullable();
            $table->primary('device_id');
            $table->foreign('driver_id')->references('_id')->on('delivery_drivers');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_fcm_tokens');
    }
};
