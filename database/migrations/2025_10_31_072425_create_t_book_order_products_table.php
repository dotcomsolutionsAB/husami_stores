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
        Schema::create('t_book_order_products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('book_order');
            $table->unsignedBigInteger('product');
            $table->unsignedInteger('qty')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('t_book_order_products');
    }
};
