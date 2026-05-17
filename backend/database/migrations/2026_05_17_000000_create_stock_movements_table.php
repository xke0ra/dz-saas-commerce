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
        $this->addCompositeKeys();

        Schema::create('stock_movements', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id');
            $table->ulid('product_id');
            $table->ulid('inventory_item_id');
            $table->ulid('order_id')->nullable();
            $table->ulid('order_item_id')->nullable();
            $table->ulid('order_return_id')->nullable();
            $table->foreignId('actor_id')->nullable();
            $table->string('type');
            $table->integer('quantity_delta')->default(0);
            $table->integer('reserved_delta')->default(0);
            $table->integer('balance_quantity_after')->nullable();
            $table->integer('balance_reserved_after')->nullable();
            $table->string('reason')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamp('occurred_at')->useCurrent();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('tenant_id', 'stock_movements_tenant_fk')
                ->references('id')
                ->on('tenants')
                ->cascadeOnDelete();

            $table->foreign('product_id', 'stock_movements_product_fk')
                ->references('id')
                ->on('products');

            $table->foreign('inventory_item_id', 'stock_movements_inventory_item_fk')
                ->references('id')
                ->on('inventory_items');

            $table->foreign('order_id', 'stock_movements_order_fk')
                ->references('id')
                ->on('orders');

            $table->foreign('order_item_id', 'stock_movements_order_item_fk')
                ->references('id')
                ->on('order_items');

            $table->foreign('order_return_id', 'stock_movements_order_return_fk')
                ->references('id')
                ->on('order_returns');

            $table->foreign('actor_id', 'stock_movements_actor_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->foreign(['tenant_id', 'product_id'], 'stock_movements_product_same_tenant_fk')
                ->references(['tenant_id', 'id'])
                ->on('products');

            $table->foreign(['tenant_id', 'inventory_item_id'], 'stock_movements_inventory_item_same_tenant_fk')
                ->references(['tenant_id', 'id'])
                ->on('inventory_items');

            $table->foreign(['tenant_id', 'order_id'], 'stock_movements_order_same_tenant_fk')
                ->references(['tenant_id', 'id'])
                ->on('orders');

            $table->foreign(['tenant_id', 'order_item_id'], 'stock_movements_order_item_same_tenant_fk')
                ->references(['tenant_id', 'id'])
                ->on('order_items');

            $table->foreign(['tenant_id', 'order_return_id'], 'stock_movements_order_return_same_tenant_fk')
                ->references(['tenant_id', 'id'])
                ->on('order_returns');

            $table->index(['tenant_id', 'product_id', 'occurred_at'], 'stock_movements_tenant_product_occurred_idx');
            $table->index(['tenant_id', 'inventory_item_id', 'occurred_at'], 'stock_movements_tenant_inventory_occurred_idx');
            $table->index(['tenant_id', 'type', 'occurred_at'], 'stock_movements_tenant_type_occurred_idx');
            $table->index(['tenant_id', 'order_id'], 'stock_movements_tenant_order_idx');
            $table->index(['tenant_id', 'order_item_id'], 'stock_movements_tenant_order_item_idx');
            $table->index(['tenant_id', 'order_return_id'], 'stock_movements_tenant_order_return_idx');
            $table->index('occurred_at', 'stock_movements_occurred_at_idx');
        });

        DB::statement('ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_non_zero_delta CHECK (quantity_delta <> 0 OR reserved_delta <> 0)');
        DB::statement("ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_type_not_blank CHECK (btrim(type) <> '')");
        DB::statement('ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_balance_quantity_after_nonnegative CHECK (balance_quantity_after IS NULL OR balance_quantity_after >= 0)');
        DB::statement('ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_balance_reserved_after_nonnegative CHECK (balance_reserved_after IS NULL OR balance_reserved_after >= 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('stock_movements')) {
            Schema::table('stock_movements', function (Blueprint $table): void {
                foreach ([
                    'stock_movements_order_return_same_tenant_fk',
                    'stock_movements_order_item_same_tenant_fk',
                    'stock_movements_order_same_tenant_fk',
                    'stock_movements_inventory_item_same_tenant_fk',
                    'stock_movements_product_same_tenant_fk',
                    'stock_movements_actor_fk',
                    'stock_movements_order_return_fk',
                    'stock_movements_order_item_fk',
                    'stock_movements_order_fk',
                    'stock_movements_inventory_item_fk',
                    'stock_movements_product_fk',
                    'stock_movements_tenant_fk',
                ] as $foreignKey) {
                    $table->dropForeign($foreignKey);
                }
            });

            foreach ([
                'stock_movements_balance_reserved_after_nonnegative',
                'stock_movements_balance_quantity_after_nonnegative',
                'stock_movements_type_not_blank',
                'stock_movements_non_zero_delta',
            ] as $constraint) {
                DB::statement("ALTER TABLE stock_movements DROP CONSTRAINT IF EXISTS {$constraint}");
            }
        }

        Schema::dropIfExists('stock_movements');

        $this->dropCompositeKeys();
    }

    private function addCompositeKeys(): void
    {
        foreach ([
            'inventory_items',
            'order_items',
            'order_returns',
        ] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                $table->unique(['tenant_id', 'id'], "{$tableName}_tenant_id_id_unique");
            });
        }
    }

    private function dropCompositeKeys(): void
    {
        foreach ([
            'order_returns',
            'order_items',
            'inventory_items',
        ] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                $table->dropUnique("{$tableName}_tenant_id_id_unique");
            });
        }
    }
};
