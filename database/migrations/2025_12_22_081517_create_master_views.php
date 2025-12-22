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
        // Schema::create('master_views', function (Blueprint $table) {
        //     $table->id();
        //     $table->timestamps();
        // });
        DB::statement("
            CREATE OR REPLACE VIEW v_grades AS
            SELECT
              DENSE_RANK() OVER (ORDER BY name) AS id,
              name
            FROM (
              SELECT DISTINCT TRIM(grade_no) AS name
              FROM t_products
              WHERE grade_no IS NOT NULL AND TRIM(grade_no) <> ''
            ) x
        ");

        DB::statement("
            CREATE OR REPLACE VIEW v_items AS
            SELECT
              DENSE_RANK() OVER (ORDER BY name) AS id,
              name
            FROM (
              SELECT DISTINCT TRIM(item_name) AS name
              FROM t_products
              WHERE item_name IS NOT NULL AND TRIM(item_name) <> ''
            ) x
        ");

        DB::statement("
            CREATE OR REPLACE VIEW v_sizes AS
            SELECT
              DENSE_RANK() OVER (ORDER BY name) AS id,
              name
            FROM (
              SELECT DISTINCT TRIM(size) AS name
              FROM t_products
              WHERE size IS NOT NULL AND TRIM(size) <> ''
            ) x
        ");

        DB::statement("
            CREATE OR REPLACE VIEW v_racks AS
            SELECT
              DENSE_RANK() OVER (ORDER BY name) AS id,
              name
            FROM (
              SELECT DISTINCT TRIM(rack_no) AS name
              FROM t_product_stocks
              WHERE rack_no IS NOT NULL AND TRIM(rack_no) <> ''
            ) x
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Schema::dropIfExists('master_views');
        DB::statement("DROP VIEW IF EXISTS v_grades");
        DB::statement("DROP VIEW IF EXISTS v_items");
        DB::statement("DROP VIEW IF EXISTS v_sizes");
        DB::statement("DROP VIEW IF EXISTS v_racks");
    }
};
