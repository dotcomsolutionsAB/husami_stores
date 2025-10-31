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
        Schema::create('t_stock_transfer', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('from_godown')->nullable(); // t_godown.id
            $table->unsignedBigInteger('to_godown')->nullable();   // t_godown.id
            $table->date('transfer_date')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('t_stock_transfer');
    }
};
