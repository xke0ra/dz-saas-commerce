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
        Schema::create('products', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('sku')->nullable();
            $table->text('short_description')->nullable();
            $table->longText('description')->nullable();
            $table->string('status')->default(ProductStatus::Draft->value)->index();
            $table->unsignedInteger('price_minor');
            $table->unsignedInteger('compare_at_price_minor')->nullable();
            $table->unsignedInteger('cost_price_minor')->nullable();
            $table->char('currency', 3)->default('DZD');
            $table->boolean('requires_shipping')->default(true);
            $table->boolean('is_featured')->default(false)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamp('published_at')->nullable()->index();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'slug']);
            $table->unique(['tenant_id', 'sku']);
            $table->index(['tenant_id', 'status', 'created_at']);
            $table->index(['tenant_id', 'category_id']);
        });

        DB::statement('ALTER TABLE products ADD CONSTRAINT products_price_minor_nonnegative CHECK (price_minor >= 0)');
        DB::statement('ALTER TABLE products ADD CONSTRAINT products_compare_at_price_minor_nonnegative CHECK (compare_at_price_minor IS NULL OR compare_at_price_minor >= 0)');
        DB::statement('ALTER TABLE products ADD CONSTRAINT products_cost_price_minor_nonnegative CHECK (cost_price_minor IS NULL OR cost_price_minor >= 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
