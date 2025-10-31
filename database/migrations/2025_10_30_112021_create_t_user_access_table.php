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
        Schema::create('t_user_access', function (Blueprint $table) {
            $table->id();
            $table->string('module_name');                   // e.g., 'products', 'clients', etc.
            // Comma-separated IDs as requested; text to avoid length limits
            $table->string('can_create');          // "1,3,7"
            $table->string('can_view');
            $table->string('can_edit');
            $table->string('can_delete');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('t_user_access');
    }
};
