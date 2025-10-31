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
        Schema::create('t_grn', function (Blueprint $table) {
            $table->id();
            $table->string('purchase_order')->nullable(); // string ref as per spec
            $table->string('grn')->unique();
            $table->date('date')->nullable();
            $table->unsignedBigInteger('godown')->nullable(); // t_godown.id
            $table->unsignedBigInteger('file')->nullable();   // t_uploads.id
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('t_grn');
    }
};
