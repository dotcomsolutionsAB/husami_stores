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
        Schema::create('t_grn_products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('grn');
            $table->unsignedBigInteger('product');
            $table->unsignedInteger('ctn')->default(0);
            $table->unsignedInteger('qty_per_ctn')->default(0);
            $table->string('rack_no')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('t_grn_products');
    }
};
