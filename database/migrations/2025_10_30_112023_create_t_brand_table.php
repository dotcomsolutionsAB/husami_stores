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
        Schema::create('t_brand', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('order_by')->default(0);
            $table->string('hex_code', 9)->nullable();       // e.g., #RRGGBB or #RRGGBBAA
            $table->unsignedBigInteger('logo')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('t_brand');
    }
};
