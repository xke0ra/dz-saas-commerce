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
        DB::statement(
            'CREATE UNIQUE INDEX shipping_rates_tenant_wilaya_delivery_null_commune_unique
            ON shipping_rates (tenant_id, wilaya_id, delivery_type)
            WHERE commune_id IS NULL',
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS shipping_rates_tenant_wilaya_delivery_null_commune_unique');
    }
};
