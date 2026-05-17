<?php

use App\Enums\ProductStatus;
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
        $this->createProductOptionsTable();
        $this->createProductOptionValuesTable();
        $this->createProductVariantsTable();
        $this->createProductVariantOptionValuesTable();
        $this->addVariantColumnsToExistingTables();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->dropVariantColumnsFromExistingTables();
        $this->dropProductVariantOptionValuesTable();
        $this->dropProductVariantsTable();
        $this->dropProductOptionValuesTable();
        $this->dropProductOptionsTable();
    }

    private function createProductOptionsTable(): void
    {
        Schema::create('product_options', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id');
            $table->ulid('product_id');
            $table->string('name');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->foreign('tenant_id', 'product_options_tenant_fk')
                ->references('id')
                ->on('tenants')
                ->cascadeOnDelete();

            $table->foreign('product_id', 'product_options_product_fk')
                ->references('id')
                ->on('products');

            $table->foreign(['tenant_id', 'product_id'], 'product_options_product_tenant_fk')
                ->references(['tenant_id', 'id'])
                ->on('products');

            $table->unique(['tenant_id', 'id'], 'product_options_tenant_id_id_unique');
            $table->unique(['tenant_id', 'product_id', 'name'], 'product_options_name_unique');
            $table->index(['tenant_id', 'product_id', 'position'], 'product_options_position_idx');
        });

        DB::statement("ALTER TABLE product_options ADD CONSTRAINT product_options_name_not_blank CHECK (btrim(name) <> '')");
    }

    private function createProductOptionValuesTable(): void
    {
        Schema::create('product_option_values', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id');
            $table->ulid('product_option_id');
            $table->string('value');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->foreign('tenant_id', 'product_option_values_tenant_fk')
                ->references('id')
                ->on('tenants')
                ->cascadeOnDelete();

            $table->foreign('product_option_id', 'product_option_values_option_fk')
                ->references('id')
                ->on('product_options');

            $table->foreign(['tenant_id', 'product_option_id'], 'product_option_values_option_tenant_fk')
                ->references(['tenant_id', 'id'])
                ->on('product_options');

            $table->unique(['tenant_id', 'id'], 'product_option_values_tenant_id_id_unique');
            $table->unique(['tenant_id', 'product_option_id', 'value'], 'product_option_values_value_unique');
            $table->index(['tenant_id', 'product_option_id', 'position'], 'product_option_values_position_idx');
        });

        DB::statement("ALTER TABLE product_option_values ADD CONSTRAINT product_option_values_value_not_blank CHECK (btrim(value) <> '')");
    }

    private function createProductVariantsTable(): void
    {
        Schema::create('product_variants', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id');
            $table->ulid('product_id');
            $table->string('sku')->nullable();
            $table->string('option_signature');
            $table->string('title')->nullable();
            $table->unsignedInteger('price_minor')->nullable();
            $table->unsignedInteger('compare_at_price_minor')->nullable();
            $table->unsignedInteger('cost_price_minor')->nullable();
            $table->string('status')->default(ProductStatus::Active->value);
            $table->unsignedInteger('sort_order')->default(0);
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id', 'product_variants_tenant_fk')
                ->references('id')
                ->on('tenants')
                ->cascadeOnDelete();

            $table->foreign('product_id', 'product_variants_product_fk')
                ->references('id')
                ->on('products');

            $table->foreign(['tenant_id', 'product_id'], 'product_variants_product_tenant_fk')
                ->references(['tenant_id', 'id'])
                ->on('products');

            $table->unique(['tenant_id', 'id'], 'product_variants_tenant_id_id_unique');
            $table->unique(['tenant_id', 'product_id', 'option_signature'], 'product_variants_signature_unique');
            $table->index(['tenant_id', 'product_id', 'sort_order'], 'product_variants_sort_idx');
            $table->index(['tenant_id', 'status'], 'product_variants_status_idx');
            $table->index(['tenant_id', 'sku'], 'product_variants_sku_idx');
        });

        DB::statement('CREATE UNIQUE INDEX product_variants_tenant_sku_unique ON product_variants (tenant_id, sku) WHERE sku IS NOT NULL');
        DB::statement("ALTER TABLE product_variants ADD CONSTRAINT product_variants_signature_not_blank CHECK (btrim(option_signature) <> '')");
        DB::statement("ALTER TABLE product_variants ADD CONSTRAINT product_variants_sku_not_blank CHECK (sku IS NULL OR btrim(sku) <> '')");
        DB::statement('ALTER TABLE product_variants ADD CONSTRAINT product_variants_price_minor_nonnegative CHECK (price_minor IS NULL OR price_minor >= 0)');
        DB::statement('ALTER TABLE product_variants ADD CONSTRAINT product_variants_compare_price_nonnegative CHECK (compare_at_price_minor IS NULL OR compare_at_price_minor >= 0)');
        DB::statement('ALTER TABLE product_variants ADD CONSTRAINT product_variants_cost_price_nonnegative CHECK (cost_price_minor IS NULL OR cost_price_minor >= 0)');
    }

    private function createProductVariantOptionValuesTable(): void
    {
        Schema::create('product_variant_option_values', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id');
            $table->ulid('product_variant_id');
            $table->ulid('product_option_value_id');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('tenant_id', 'pvov_tenant_fk')
                ->references('id')
                ->on('tenants')
                ->cascadeOnDelete();

            $table->foreign('product_variant_id', 'pvov_variant_fk')
                ->references('id')
                ->on('product_variants');

            $table->foreign('product_option_value_id', 'pvov_value_fk')
                ->references('id')
                ->on('product_option_values');

            $table->foreign(['tenant_id', 'product_variant_id'], 'pvov_variant_tenant_fk')
                ->references(['tenant_id', 'id'])
                ->on('product_variants');

            $table->foreign(['tenant_id', 'product_option_value_id'], 'pvov_value_tenant_fk')
                ->references(['tenant_id', 'id'])
                ->on('product_option_values');

            $table->unique(['tenant_id', 'product_variant_id', 'product_option_value_id'], 'pvov_unique');
            $table->index(['tenant_id', 'product_variant_id'], 'pvov_variant_idx');
            $table->index(['tenant_id', 'product_option_value_id'], 'pvov_value_idx');
        });
    }

    private function addVariantColumnsToExistingTables(): void
    {
        Schema::table('inventory_items', function (Blueprint $table): void {
            $table->ulid('product_variant_id')->nullable()->after('product_id');

            $table->foreign('product_variant_id', 'inventory_items_variant_fk')
                ->references('id')
                ->on('product_variants');

            $table->foreign(['tenant_id', 'product_variant_id'], 'inventory_items_variant_tenant_fk')
                ->references(['tenant_id', 'id'])
                ->on('product_variants');

            $table->index(['tenant_id', 'product_variant_id'], 'inventory_items_variant_idx');
        });

        Schema::table('order_items', function (Blueprint $table): void {
            $table->ulid('product_variant_id')->nullable()->after('product_id');
            $table->string('variant_title')->nullable()->after('product_sku');
            $table->string('variant_sku')->nullable()->after('variant_title');
            $table->jsonb('selected_options')->nullable()->after('variant_sku');

            $table->foreign('product_variant_id', 'order_items_variant_fk')
                ->references('id')
                ->on('product_variants');

            $table->foreign(['tenant_id', 'product_variant_id'], 'order_items_variant_tenant_fk')
                ->references(['tenant_id', 'id'])
                ->on('product_variants');

            $table->index(['tenant_id', 'product_variant_id'], 'order_items_variant_idx');
        });

        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->ulid('product_variant_id')->nullable()->after('product_id');

            $table->foreign('product_variant_id', 'stock_movements_variant_fk')
                ->references('id')
                ->on('product_variants');

            $table->foreign(['tenant_id', 'product_variant_id'], 'stock_movements_variant_tenant_fk')
                ->references(['tenant_id', 'id'])
                ->on('product_variants');

            $table->index(['tenant_id', 'product_variant_id'], 'stock_movements_variant_idx');
        });
    }

    private function dropVariantColumnsFromExistingTables(): void
    {
        if (Schema::hasColumn('stock_movements', 'product_variant_id')) {
            Schema::table('stock_movements', function (Blueprint $table): void {
                $table->dropForeign('stock_movements_variant_tenant_fk');
                $table->dropForeign('stock_movements_variant_fk');
                $table->dropIndex('stock_movements_variant_idx');
                $table->dropColumn('product_variant_id');
            });
        }

        if (Schema::hasColumn('order_items', 'product_variant_id')) {
            Schema::table('order_items', function (Blueprint $table): void {
                $table->dropForeign('order_items_variant_tenant_fk');
                $table->dropForeign('order_items_variant_fk');
                $table->dropIndex('order_items_variant_idx');
                $table->dropColumn([
                    'product_variant_id',
                    'variant_title',
                    'variant_sku',
                    'selected_options',
                ]);
            });
        }

        if (Schema::hasColumn('inventory_items', 'product_variant_id')) {
            Schema::table('inventory_items', function (Blueprint $table): void {
                $table->dropForeign('inventory_items_variant_tenant_fk');
                $table->dropForeign('inventory_items_variant_fk');
                $table->dropIndex('inventory_items_variant_idx');
                $table->dropColumn('product_variant_id');
            });
        }
    }

    private function dropProductVariantOptionValuesTable(): void
    {
        $this->dropConstraints('product_variant_option_values', [
            'pvov_value_tenant_fk',
            'pvov_variant_tenant_fk',
            'pvov_value_fk',
            'pvov_variant_fk',
            'pvov_tenant_fk',
            'pvov_unique',
        ]);

        Schema::dropIfExists('product_variant_option_values');
    }

    private function dropProductVariantsTable(): void
    {
        if (Schema::hasTable('product_variants')) {
            DB::statement('DROP INDEX IF EXISTS product_variants_tenant_sku_unique');
        }

        $this->dropConstraints('product_variants', [
            'product_variants_cost_price_nonnegative',
            'product_variants_compare_price_nonnegative',
            'product_variants_price_minor_nonnegative',
            'product_variants_sku_not_blank',
            'product_variants_signature_not_blank',
            'product_variants_signature_unique',
            'product_variants_tenant_id_id_unique',
            'product_variants_product_tenant_fk',
            'product_variants_product_fk',
            'product_variants_tenant_fk',
        ]);

        Schema::dropIfExists('product_variants');
    }

    private function dropProductOptionValuesTable(): void
    {
        $this->dropConstraints('product_option_values', [
            'product_option_values_value_not_blank',
            'product_option_values_value_unique',
            'product_option_values_tenant_id_id_unique',
            'product_option_values_option_tenant_fk',
            'product_option_values_option_fk',
            'product_option_values_tenant_fk',
        ]);

        Schema::dropIfExists('product_option_values');
    }

    private function dropProductOptionsTable(): void
    {
        $this->dropConstraints('product_options', [
            'product_options_name_not_blank',
            'product_options_name_unique',
            'product_options_tenant_id_id_unique',
            'product_options_product_tenant_fk',
            'product_options_product_fk',
            'product_options_tenant_fk',
        ]);

        Schema::dropIfExists('product_options');
    }

    /**
     * @param  array<int, string>  $constraints
     */
    private function dropConstraints(string $tableName, array $constraints): void
    {
        if (! Schema::hasTable($tableName)) {
            return;
        }

        foreach ($constraints as $constraint) {
            DB::statement("ALTER TABLE {$tableName} DROP CONSTRAINT IF EXISTS {$constraint}");
        }
    }
};
