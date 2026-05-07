<?php

use App\Enums\CouponType;
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
        Schema::create('coupons', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('name')->nullable();
            $table->string('type')->default(CouponType::FixedAmount->value);
            $table->unsignedInteger('value');
            $table->unsignedInteger('max_discount_minor')->nullable();
            $table->unsignedInteger('minimum_subtotal_minor')->default(0);
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('used_count')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'is_active']);
            $table->index(['tenant_id', 'starts_at', 'ends_at']);
        });

        DB::statement("ALTER TABLE coupons ADD CONSTRAINT coupons_type_check CHECK (type IN ('fixed_amount', 'percentage'))");
        DB::statement("ALTER TABLE coupons ADD CONSTRAINT coupons_percentage_value_check CHECK (type != 'percentage' OR value BETWEEN 1 AND 100)");
        DB::statement('ALTER TABLE coupons ADD CONSTRAINT coupons_fixed_amount_value_check CHECK (type != \'fixed_amount\' OR value > 0)');
        DB::statement('ALTER TABLE coupons ADD CONSTRAINT coupons_minimum_subtotal_nonnegative CHECK (minimum_subtotal_minor >= 0)');
        DB::statement('ALTER TABLE coupons ADD CONSTRAINT coupons_used_count_within_usage_limit CHECK (usage_limit IS NULL OR used_count <= usage_limit)');

        Schema::table('orders', function (Blueprint $table): void {
            $table->foreignUlid('coupon_id')->nullable()->after('customer_id');
            $table->index(['tenant_id', 'coupon_id']);
            $table->foreign(['tenant_id', 'coupon_id'], 'orders_coupon_same_tenant_fk')
                ->references(['tenant_id', 'id'])
                ->on('coupons')
                ->restrictOnDelete();
        });

        Schema::create('coupon_redemptions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('coupon_id');
            $table->foreignUlid('order_id');
            $table->foreignUlid('customer_id')->nullable();
            $table->string('code');
            $table->unsignedInteger('discount_minor');
            $table->char('currency', 3)->default('DZD');
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->unique('order_id');
            $table->index(['tenant_id', 'coupon_id']);
            $table->index(['tenant_id', 'customer_id']);
            $table->foreign(['tenant_id', 'coupon_id'], 'coupon_redemptions_coupon_same_tenant_fk')
                ->references(['tenant_id', 'id'])
                ->on('coupons')
                ->restrictOnDelete();
            $table->foreign(['tenant_id', 'order_id'], 'coupon_redemptions_order_same_tenant_fk')
                ->references(['tenant_id', 'id'])
                ->on('orders')
                ->cascadeOnDelete();
            $table->foreign(['tenant_id', 'customer_id'], 'coupon_redemptions_customer_same_tenant_fk')
                ->references(['tenant_id', 'id'])
                ->on('customers')
                ->restrictOnDelete();
        });

        DB::statement('ALTER TABLE coupon_redemptions ADD CONSTRAINT coupon_redemptions_discount_nonnegative CHECK (discount_minor >= 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coupon_redemptions');

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropForeign('orders_coupon_same_tenant_fk');
            $table->dropIndex(['tenant_id', 'coupon_id']);
            $table->dropColumn('coupon_id');
        });

        Schema::dropIfExists('coupons');
    }
};
