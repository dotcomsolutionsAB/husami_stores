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
        Schema::create('t_logs', function (Blueprint $table) {
            $table->id();
            $table->string('item')->nullable();
            $table->string('grade_no')->nullable();
            $table->string('size')->nullable();
            $table->unsignedBigInteger('brand')->nullable();   // t_brand.id
            $table->unsignedBigInteger('godown')->nullable();  // t_godown.id
            $table->integer('quantity')->default(0);
            $table->string('action', 50);
            $table->unsignedBigInteger('user');
            $table->date('date')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('t_logs');
    }
};
