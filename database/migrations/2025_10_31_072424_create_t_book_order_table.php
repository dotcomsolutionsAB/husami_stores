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
        Schema::create('t_book_order', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('supplier');
            $table->string('booking_no')->unique();
            $table->date('booking_date')->nullable();
            $table->unsignedBigInteger('file')->nullable(); // t_uploads.id
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('t_book_order');
    }
};
