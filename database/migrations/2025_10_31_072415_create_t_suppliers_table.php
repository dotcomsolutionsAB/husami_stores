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
        Schema::create('t_suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('city')->nullable();
            $table->unsignedInteger('pincode')->nullable();
            $table->string('gstin', 20)->nullable();
            $table->unsignedBigInteger('state')->nullable(); // t_state.id
            $table->string('country', 64)->nullable();
            $table->string('mobile', 32)->nullable();
            $table->string('email')->nullable();
            $table->timestamps();

            $table->index(['name','mobile','gstin']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('t_suppliers');
    }
};
