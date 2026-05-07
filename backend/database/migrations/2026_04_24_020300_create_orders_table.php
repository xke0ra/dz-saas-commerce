<?php

use App\Enums\DeliveryType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
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
        Schema::create('orders', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('store_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('customer_id')->constrained()->cascadeOnDelete();
            $table->string('order_number');
            $table->string('status')->default(OrderStatus::Pending->value)->index();
            $table->string('payment_status')->default(PaymentStatus::Unpaid->value)->index();
            $table->string('delivery_type')->default(DeliveryType::Home->value);
            $table->unsignedTinyInteger('wilaya_id');
            $table->foreignId('commune_id')->constrained()->restrictOnDelete();
            $table->text('shipping_address');
            $table->text('customer_note')->nullable();
            $table->unsignedInteger('subtotal_minor');
            $table->unsignedInteger('shipping_fee_minor');
            $table->unsignedInteger('discount_minor')->default(0);
            $table->unsignedInteger('total_minor');
            $table->char('currency', 3)->default('DZD');
            $table->timestamp('confirmed_at')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->foreign('wilaya_id')->references('id')->on('wilayas')->restrictOnDelete();
            $table->unique(['tenant_id', 'order_number']);
            $table->index(['tenant_id', 'status', 'created_at']);
            $table->index(['tenant_id', 'payment_status']);
            $table->index(['tenant_id', 'customer_id']);
        });

        DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_subtotal_minor_nonnegative CHECK (subtotal_minor >= 0)');
        DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_shipping_fee_minor_nonnegative CHECK (shipping_fee_minor >= 0)');
        DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_discount_minor_nonnegative CHECK (discount_minor >= 0)');
        DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_total_minor_nonnegative CHECK (total_minor >= 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
