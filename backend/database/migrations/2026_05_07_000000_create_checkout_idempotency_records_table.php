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
        Schema::create('checkout_idempotency_records', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('store_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('idempotency_key')->nullable();
            $table->char('request_hash', 64);
            $table->string('customer_phone', 32);
            $table->unsignedSmallInteger('response_status')->default(201);
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at');
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'store_id', 'idempotency_key'], 'checkout_idempotency_records_key_unique');
            $table->index(['tenant_id', 'store_id', 'customer_phone', 'request_hash', 'created_at'], 'checkout_idempotency_records_duplicate_window_idx');
            $table->index(['tenant_id', 'order_id']);
            $table->index('expires_at');
        });

        DB::statement('ALTER TABLE checkout_idempotency_records ADD CONSTRAINT checkout_idempotency_records_key_not_blank CHECK (idempotency_key IS NULL OR length(btrim(idempotency_key)) > 0)');
        DB::statement('ALTER TABLE checkout_idempotency_records ADD CONSTRAINT checkout_idempotency_records_request_hash_length CHECK (length(request_hash) = 64)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('checkout_idempotency_records');
    }
};
