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
        $this->addCompositeKeys();
        $this->addTenantForeignKeys();
        $this->addGeographyForeignKeys();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->dropGeographyForeignKeys();
        $this->dropTenantForeignKeys();
        $this->dropCompositeKeys();
    }

    private function addCompositeKeys(): void
    {
        foreach ([
            'stores',
            'categories',
            'products',
            'customers',
            'orders',
            'payment_methods',
            'shipping_companies',
            'failed_delivery_reasons',
            'shipments',
        ] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                $table->unique(['tenant_id', 'id'], "{$tableName}_tenant_id_id_unique");
            });
        }

        Schema::table('communes', function (Blueprint $table): void {
            $table->unique(['wilaya_id', 'id'], 'communes_wilaya_id_id_unique');
        });
    }

    private function dropCompositeKeys(): void
    {
        Schema::table('communes', function (Blueprint $table): void {
            $table->dropUnique('communes_wilaya_id_id_unique');
        });

        foreach ([
            'shipments',
            'failed_delivery_reasons',
            'shipping_companies',
            'payment_methods',
            'orders',
            'customers',
            'products',
            'categories',
            'stores',
        ] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                $table->dropUnique("{$tableName}_tenant_id_id_unique");
            });
        }
    }

    private function addTenantForeignKeys(): void
    {
        Schema::table('categories', function (Blueprint $table): void {
            $table->foreign(['tenant_id', 'parent_id'], 'categories_parent_same_tenant_fk')
                ->references(['tenant_id', 'id'])
                ->on('categories');
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->foreign(['tenant_id', 'category_id'], 'products_category_same_tenant_fk')
                ->references(['tenant_id', 'id'])
                ->on('categories');
        });

        Schema::table('product_images', function (Blueprint $table): void {
            $table->foreign(['tenant_id', 'product_id'], 'product_images_product_same_tenant_fk')
                ->references(['tenant_id', 'id'])
                ->on('products');
        });

        Schema::table('inventory_items', function (Blueprint $table): void {
            $table->foreign(['tenant_id', 'product_id'], 'inventory_items_product_same_tenant_fk')
                ->references(['tenant_id', 'id'])
                ->on('products');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->foreign(['tenant_id', 'store_id'], 'orders_store_same_tenant_fk')
                ->references(['tenant_id', 'id'])
                ->on('stores');

            $table->foreign(['tenant_id', 'customer_id'], 'orders_customer_same_tenant_fk')
                ->references(['tenant_id', 'id'])
                ->on('customers');
        });

        Schema::table('order_items', function (Blueprint $table): void {
            $table->foreign(['tenant_id', 'order_id'], 'order_items_order_same_tenant_fk')
                ->references(['tenant_id', 'id'])
                ->on('orders');

            $table->foreign(['tenant_id', 'product_id'], 'order_items_product_same_tenant_fk')
                ->references(['tenant_id', 'id'])
                ->on('products');
        });

        Schema::table('order_status_histories', function (Blueprint $table): void {
            $table->foreign(['tenant_id', 'order_id'], 'order_status_order_same_tenant_fk')
                ->references(['tenant_id', 'id'])
                ->on('orders');
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->foreign(['tenant_id', 'order_id'], 'payments_order_same_tenant_fk')
                ->references(['tenant_id', 'id'])
                ->on('orders');

            $table->foreign(['tenant_id', 'payment_method_id'], 'payments_method_same_tenant_fk')
                ->references(['tenant_id', 'id'])
                ->on('payment_methods');
        });

        Schema::table('shipments', function (Blueprint $table): void {
            $table->foreign(['tenant_id', 'order_id'], 'shipments_order_same_tenant_fk')
                ->references(['tenant_id', 'id'])
                ->on('orders');

            $table->foreign(['tenant_id', 'shipping_company_id'], 'shipments_company_same_tenant_fk')
                ->references(['tenant_id', 'id'])
                ->on('shipping_companies');

            $table->foreign(['tenant_id', 'failed_delivery_reason_id'], 'shipments_failed_reason_same_tenant_fk')
                ->references(['tenant_id', 'id'])
                ->on('failed_delivery_reasons');
        });

        Schema::table('shipment_status_histories', function (Blueprint $table): void {
            $table->foreign(['tenant_id', 'shipment_id'], 'shipment_status_shipment_same_tenant_fk')
                ->references(['tenant_id', 'id'])
                ->on('shipments');
        });

        Schema::table('order_returns', function (Blueprint $table): void {
            $table->foreign(['tenant_id', 'order_id'], 'order_returns_order_same_tenant_fk')
                ->references(['tenant_id', 'id'])
                ->on('orders');

            $table->foreign(['tenant_id', 'customer_id'], 'order_returns_customer_same_tenant_fk')
                ->references(['tenant_id', 'id'])
                ->on('customers');
        });
    }

    private function dropTenantForeignKeys(): void
    {
        foreach ([
            'order_returns' => [
                'order_returns_customer_same_tenant_fk',
                'order_returns_order_same_tenant_fk',
            ],
            'shipment_status_histories' => [
                'shipment_status_shipment_same_tenant_fk',
            ],
            'shipments' => [
                'shipments_failed_reason_same_tenant_fk',
                'shipments_company_same_tenant_fk',
                'shipments_order_same_tenant_fk',
            ],
            'payments' => [
                'payments_method_same_tenant_fk',
                'payments_order_same_tenant_fk',
            ],
            'order_status_histories' => [
                'order_status_order_same_tenant_fk',
            ],
            'order_items' => [
                'order_items_product_same_tenant_fk',
                'order_items_order_same_tenant_fk',
            ],
            'orders' => [
                'orders_customer_same_tenant_fk',
                'orders_store_same_tenant_fk',
            ],
            'inventory_items' => [
                'inventory_items_product_same_tenant_fk',
            ],
            'product_images' => [
                'product_images_product_same_tenant_fk',
            ],
            'products' => [
                'products_category_same_tenant_fk',
            ],
            'categories' => [
                'categories_parent_same_tenant_fk',
            ],
        ] as $tableName => $foreignKeys) {
            Schema::table($tableName, function (Blueprint $table) use ($foreignKeys): void {
                foreach ($foreignKeys as $foreignKey) {
                    $table->dropForeign($foreignKey);
                }
            });
        }
    }

    private function addGeographyForeignKeys(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->foreign(['wilaya_id', 'commune_id'], 'customers_commune_same_wilaya_fk')
                ->references(['wilaya_id', 'id'])
                ->on('communes');
        });

        Schema::table('shipping_rates', function (Blueprint $table): void {
            $table->foreign(['wilaya_id', 'commune_id'], 'shipping_rates_commune_same_wilaya_fk')
                ->references(['wilaya_id', 'id'])
                ->on('communes');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->foreign(['wilaya_id', 'commune_id'], 'orders_commune_same_wilaya_fk')
                ->references(['wilaya_id', 'id'])
                ->on('communes');
        });

        Schema::table('shipments', function (Blueprint $table): void {
            $table->foreign(['wilaya_id', 'commune_id'], 'shipments_commune_same_wilaya_fk')
                ->references(['wilaya_id', 'id'])
                ->on('communes');
        });
    }

    private function dropGeographyForeignKeys(): void
    {
        foreach ([
            'shipments' => ['shipments_commune_same_wilaya_fk'],
            'orders' => ['orders_commune_same_wilaya_fk'],
            'shipping_rates' => ['shipping_rates_commune_same_wilaya_fk'],
            'customers' => ['customers_commune_same_wilaya_fk'],
        ] as $tableName => $foreignKeys) {
            Schema::table($tableName, function (Blueprint $table) use ($foreignKeys): void {
                foreach ($foreignKeys as $foreignKey) {
                    $table->dropForeign($foreignKey);
                }
            });
        }
    }
};
