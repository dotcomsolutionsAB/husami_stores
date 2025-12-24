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
        Schema::create('t_pick_up_slip_products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pick_up_slip_id');
            $table->unsignedBigInteger('product_stock_id');
            $table->string('sku');
            $table->unsignedBigInteger('godown')->nullable(); // t_godown.id
            $table->unsignedInteger('ctn');
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('approved')->default(0);
            $table->longText('remarks')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('t_pick_up_slip_products');
    }
};
