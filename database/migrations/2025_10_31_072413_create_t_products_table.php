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
        Schema::create('t_products', function (Blueprint $table) {
            $table->id();
            $table->string('grade_no')->nullable();
            $table->string('item_name');
            $table->string('size')->nullable();
            $table->unsignedBigInteger('brand')->nullable();   // t_brand.id
            $table->unsignedBigInteger('units')->nullable();   // future t_units.id?
            $table->decimal('list_price', 12, 2)->default(0);
            $table->string('hsn', 32)->nullable();
            $table->decimal('tax', 5, 2)->default(0);
            $table->unsignedInteger('low_stock_level')->default(0);
            $table->timestamps();

            $table->index(['item_name','grade_no','size']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('t_products');
    }
};
