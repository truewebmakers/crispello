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
        Schema::create('admins', function (Blueprint $table) {
            $table->id('_id');
            $table->tinyText('username')->nullable();
            $table->tinyText('password')->nullable();
            $table->mediumText('name')->nullable();
            $table->tinyText('phoneno')->nullable();
            $table->tinyText('email')->nullable();
            $table->mediumText('location')->nullable();
            $table->tinyText('latitude')->nullable();
            $table->tinyText('longitude')->nullable();
            $table->tinyText('delivery_coverage_km')->nullable();
            $table->tinyText('delivery_charge')->nullable();
            $table->tinyText('free_upto_km')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admins');
    }
};
