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
        Schema::create('t_quotation_products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('quotation');
            $table->string('sku');
            $table->unsignedInteger('qty')->default(0);
            $table->unsignedBigInteger('unit')->nullable();
            $table->decimal('price', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->string('hsn', 32)->nullable();
            $table->decimal('tax', 5, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('t_quotation_products');
    }
};
