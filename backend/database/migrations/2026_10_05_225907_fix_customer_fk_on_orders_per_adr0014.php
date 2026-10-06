<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Per ADR-0014: Customer FK on orders must be SET NULL to preserve financial history
        // when customer is archived. Customer snapshot on order captures details.
        // Change from cascadeOnDelete to nullOnDelete (SET NULL).
        // First make customer_id nullable, then re-add FK with nullOnDelete (SET NULL).
        // Also drop the composite FK orders_customer_same_tenant_fk which conflicts with SET NULL.

        // Make customer_id nullable first
        DB::statement('ALTER TABLE orders ALTER COLUMN customer_id DROP NOT NULL');

        // Drop the composite FK that conflicts with SET NULL
        DB::statement('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_customer_same_tenant_fk');

        // Drop the existing FK constraint first, then re-add with nullOnDelete (SET NULL)
        DB::statement('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_customer_id_foreign');
        Schema::table('orders', function (Blueprint $table): void {
            $table->foreign('customer_id', 'orders_customer_id_foreign')
                ->references('id')
                ->on('customers')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Restore original cascadeOnDelete for rollback
        DB::statement('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_customer_id_foreign');
        Schema::table('orders', function (Blueprint $table): void {
            $table->foreign('customer_id', 'orders_customer_id_foreign')
                ->references('id')
                ->on('customers')
                ->cascadeOnDelete();
        });

        // Restore NOT NULL constraint on rollback
        DB::statement('ALTER TABLE orders ALTER COLUMN customer_id SET NOT NULL');

        // Restore composite FK on rollback
        Schema::table('orders', function (Blueprint $table): void {
            $table->foreign(['tenant_id', 'customer_id'], 'orders_customer_same_tenant_fk')
                ->references(['tenant_id', 'id'])
                ->on('customers');
        });
    }
};