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
        Schema::create('t_proforma', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client');
            $table->string('proforma_no')->unique();
            $table->date('proforma_date')->nullable();
            $table->unsignedBigInteger('quotation');
            $table->string('sales_order_no')->nullable();
            $table->decimal('gross_total', 12, 2)->default(0);
            $table->decimal('packing_and_forwarding', 12, 2)->default(0);
            $table->decimal('freight_val', 12, 2)->default(0);
            $table->decimal('total_tax', 12, 2)->default(0);
            $table->decimal('round_off', 12, 2)->default(0);
            $table->decimal('grand_total', 12, 2)->default(0);
            $table->string('prices')->nullable();
            $table->string('p_and_f')->nullable();
            $table->string('freight')->nullable();
            $table->string('delivery')->nullable();
            $table->string('payment')->nullable();
            $table->string('validity')->nullable();
            $table->longText('remarks')->nullable();
            $table->unsignedBigInteger('file')->nullable(); // t_uploads.id
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('t_proforma');
    }
};
