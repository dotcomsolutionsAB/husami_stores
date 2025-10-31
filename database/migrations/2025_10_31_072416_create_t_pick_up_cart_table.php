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
        Schema::create('t_pick_up_cart', function (Blueprint $table) {
            $table->id();
            $table->string('grade_no')->nullable();
            $table->string('item')->nullable();
            $table->string('size')->nullable();
            $table->unsignedBigInteger('brand')->nullable();   // t_brand.id
            $table->unsignedBigInteger('godown')->nullable();  // t_godown.id
            $table->unsignedInteger('ctn')->default(0);
            $table->unsignedInteger('total_quantity')->default(0);
            $table->unsignedInteger('cart_no')->nullable();
            $table->string('rack_no')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('t_pick_up_cart');
    }
};
