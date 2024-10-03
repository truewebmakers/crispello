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
        Schema::create('users', function (Blueprint $table) {
            $table->id('_id');
            $table->mediumText('name')->nullable();
            $table->tinyText('phoneno')->nullable();
            $table->tinyText('email')->nullable();
            $table->tinyText('dob')->nullable()->comment('DD-MM-YYYY');
            $table->tinyText('aniversary_date')->nullable()->comment('DD-MM-YYYY');
            $table->tinyText('gender')->nullable()->comment('Female,Male,Other,Prefer not to disclose,null');
            $table->mediumText('image')->nullable();
            $table->boolean('disable')->default(0)->comment('0:Not Disable ,1:Disable')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
