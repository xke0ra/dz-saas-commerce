<?php

use App\Enums\DeliveryType;
use App\Enums\ShipmentStatus;
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
        Schema::create('shipments', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('order_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('shipping_company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('failed_delivery_reason_id')->nullable()->constrained()->nullOnDelete();
            $table->string('tracking_number')->nullable();
            $table->string('status')->default(ShipmentStatus::Pending->value)->index();
            $table->string('delivery_type')->default(DeliveryType::Home->value);
            $table->unsignedTinyInteger('wilaya_id');
            $table->foreignId('commune_id')->constrained()->restrictOnDelete();
            $table->text('destination_address');
            $table->unsignedInteger('shipping_fee_minor')->default(0);
            $table->char('currency', 3)->default('DZD');
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->text('failure_note')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->foreign('wilaya_id')->references('id')->on('wilayas')->restrictOnDelete();
            $table->unique(['tenant_id', 'tracking_number']);
            $table->index(['tenant_id', 'order_id']);
            $table->index(['tenant_id', 'status', 'created_at']);
            $table->index(['tenant_id', 'shipping_company_id']);
        });

        DB::statement('ALTER TABLE shipments ADD CONSTRAINT shipments_shipping_fee_minor_nonnegative CHECK (shipping_fee_minor >= 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
