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
        Schema::create('t_template', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // requires t_uploads table to exist, else swap to unsignedBigInteger
            $table->unsignedBigInteger('image');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('t_template');
    }
};
