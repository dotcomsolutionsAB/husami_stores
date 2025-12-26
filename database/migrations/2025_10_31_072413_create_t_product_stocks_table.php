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
        Schema::create('t_product_stocks', function (Blueprint $table) {
            $table->id();
            // extend later: product_id, godown_id, quantity, etc.
            $table->string('sku');   // t_products.sku
            $table->unsignedInteger('godown_id');   // t_godown.id
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('ctn');
            $table->unsignedInteger('sent')->default(0);
            $table->string('batch_no');   
            $table->string('rack_no');   
            $table->string('invoice_no');
            $table->date('invoice_date');
            $table->string('tc_no')->nullable();
            $table->date('tc_date')->nullable();
            $table->string('tc_attachment')->nullable();
            $table->longText('remarks')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('t_product_stocks');
    }
};
