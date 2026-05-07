<?php

use App\Enums\DeliveryType;
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
        Schema::create('shipping_rates', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('wilaya_id');
            $table->foreignId('commune_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('delivery_type')->default(DeliveryType::Home->value);
            $table->unsignedInteger('price_minor');
            $table->char('currency', 3)->default('DZD');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->foreign('wilaya_id')->references('id')->on('wilayas')->cascadeOnDelete();
            $table->unique(['tenant_id', 'wilaya_id', 'commune_id', 'delivery_type']);
            $table->index(['tenant_id', 'wilaya_id', 'delivery_type', 'is_active']);
        });

        DB::statement('ALTER TABLE shipping_rates ADD CONSTRAINT shipping_rates_price_minor_nonnegative CHECK (price_minor >= 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipping_rates');
    }
};
