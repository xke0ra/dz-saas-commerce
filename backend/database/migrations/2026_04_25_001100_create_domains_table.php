<?php

use App\Enums\DomainStatus;
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
        Schema::create('domains', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('store_id');
            $table->string('hostname');
            $table->string('status')->default(DomainStatus::PendingVerification->value)->index();
            $table->string('verification_token', 96)->unique();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->boolean('redirect_to_primary')->default(true);
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->unique('hostname');
            $table->index(['tenant_id', 'store_id']);
            $table->index(['hostname', 'status']);
            $table->foreign(['tenant_id', 'store_id'], 'domains_store_same_tenant_fk')
                ->references(['tenant_id', 'id'])
                ->on('stores')
                ->cascadeOnDelete();
        });

        DB::statement("ALTER TABLE domains ADD CONSTRAINT domains_status_check CHECK (status IN ('pending_verification', 'active', 'failed', 'disabled'))");
        DB::statement("ALTER TABLE domains ADD CONSTRAINT domains_hostname_format_check CHECK (hostname ~ '^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?(\\.[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)+$')");
        DB::statement('CREATE UNIQUE INDEX domains_primary_per_store_unique ON domains (tenant_id, store_id) WHERE is_primary = true');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS domains_primary_per_store_unique');
        Schema::dropIfExists('domains');
    }
};
