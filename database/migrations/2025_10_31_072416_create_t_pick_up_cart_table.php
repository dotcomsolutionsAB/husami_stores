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
            $table->unsignedBigInteger('user_id')->nullable();  // users.id
            $table->unsignedBigInteger('godown')->nullable();  // t_godown.id
            $table->unsignedInteger('ctn')->default(0);
            $table->string('sku');
            $table->unsignedInteger('product_stock_id');
            $table->unsignedInteger('total_quantity')->default(0);
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
