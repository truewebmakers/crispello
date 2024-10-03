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
        Schema::create('delivery_drivers', function (Blueprint $table) {
            $table->id('_id');
            $table->mediumText('name')->nullable();
            $table->tinyText('phoneno')->nullable();
            $table->tinyText('email')->nullable();
            $table->mediumText('profile_image')->nullable();
            $table->tinyText('latitude')->nullable();
            $table->tinyText('longitude')->nullable();
            $table->boolean('online')->default(1)->comment('0:offline 1:online')->nullable();
            $table->boolean('available')->default(1)->comment('0:not available 1:available')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_drivers');
    }
};
