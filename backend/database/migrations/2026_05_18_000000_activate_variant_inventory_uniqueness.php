<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE inventory_items DROP CONSTRAINT IF EXISTS inventory_items_tenant_id_product_id_unique');
        DB::statement('DROP INDEX IF EXISTS inventory_items_tenant_id_product_id_unique');

        DB::statement('CREATE UNIQUE INDEX inventory_items_simple_unique ON inventory_items (tenant_id, product_id) WHERE product_variant_id IS NULL');
        DB::statement('CREATE UNIQUE INDEX inventory_items_variant_unique ON inventory_items (tenant_id, product_variant_id) WHERE product_variant_id IS NOT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS inventory_items_variant_unique');
        DB::statement('DROP INDEX IF EXISTS inventory_items_simple_unique');

        // Rollback can fail if a tenant/product already has multiple variant inventory rows.
        DB::statement('ALTER TABLE inventory_items ADD CONSTRAINT inventory_items_tenant_id_product_id_unique UNIQUE (tenant_id, product_id)');
    }
};
