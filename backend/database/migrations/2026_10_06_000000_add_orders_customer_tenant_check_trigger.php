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
        // Create a trigger function to enforce same-tenant customer_id on orders
        DB::statement(<<<'SQL'
CREATE OR REPLACE FUNCTION enforce_orders_customer_tenant_match()
RETURNS trigger AS $$
BEGIN
    -- Only check on INSERT or UPDATE where customer_id is being set/changed
    IF (TG_OP = 'INSERT' OR TG_OP = 'UPDATE') AND NEW.customer_id IS NOT NULL THEN
        IF NEW.tenant_id IS NULL THEN
            RAISE EXCEPTION 'Order tenant_id cannot be null when customer_id is set';
        END IF;

        IF EXISTS (
            SELECT 1 FROM customers
            WHERE id = NEW.customer_id
            AND tenant_id != NEW.tenant_id
        ) THEN
            RAISE EXCEPTION 'Order customer_id references a customer from a different tenant';
        END IF;
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql SECURITY DEFINER;
SQL
        );

        // Attach the trigger to the orders table (separate statements)
        DB::statement('DROP TRIGGER IF EXISTS orders_customer_tenant_check ON orders;');
        DB::statement(<<<'SQL'
CREATE TRIGGER orders_customer_tenant_check
    BEFORE INSERT OR UPDATE ON orders
    FOR EACH ROW
    EXECUTE FUNCTION enforce_orders_customer_tenant_match();
SQL
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS orders_customer_tenant_check ON orders;');
        DB::statement('DROP FUNCTION IF EXISTS enforce_orders_customer_tenant_match();');
    }
};