<?php

use App\Enums\InvoiceStatus;
use App\Enums\SubscriptionPaymentStatus;
use App\Enums\SubscriptionStatus;
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
        Schema::create('subscriptions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->string('status')->default(SubscriptionStatus::Trialing->value)->index();
            $table->boolean('is_current')->default(true);
            $table->timestamp('starts_at');
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_starts_at');
            $table->timestamp('current_period_ends_at');
            $table->timestamp('grace_ends_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'is_current']);
            $table->index(['tenant_id', 'current_period_ends_at']);
        });

        DB::statement('CREATE UNIQUE INDEX subscriptions_one_current_per_tenant ON subscriptions (tenant_id) WHERE is_current = true');

        Schema::create('invoices', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('subscription_id')->nullable();
            $table->string('invoice_number');
            $table->string('status')->default(InvoiceStatus::Issued->value)->index();
            $table->unsignedInteger('subtotal_minor');
            $table->unsignedInteger('tax_minor')->default(0);
            $table->unsignedInteger('total_minor');
            $table->unsignedInteger('paid_amount_minor')->default(0);
            $table->unsignedInteger('balance_minor');
            $table->char('currency', 3)->default('DZD');
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'invoice_number']);
            $table->index(['tenant_id', 'status', 'created_at']);
            $table->index(['tenant_id', 'due_at']);
            $table->foreign(['tenant_id', 'subscription_id'])
                ->references(['tenant_id', 'id'])
                ->on('subscriptions')
                ->restrictOnDelete();
        });

        DB::statement('ALTER TABLE invoices ADD CONSTRAINT invoices_subtotal_minor_nonnegative CHECK (subtotal_minor >= 0)');
        DB::statement('ALTER TABLE invoices ADD CONSTRAINT invoices_tax_minor_nonnegative CHECK (tax_minor >= 0)');
        DB::statement('ALTER TABLE invoices ADD CONSTRAINT invoices_total_minor_nonnegative CHECK (total_minor >= 0)');
        DB::statement('ALTER TABLE invoices ADD CONSTRAINT invoices_paid_amount_minor_nonnegative CHECK (paid_amount_minor >= 0)');
        DB::statement('ALTER TABLE invoices ADD CONSTRAINT invoices_balance_minor_nonnegative CHECK (balance_minor >= 0)');
        DB::statement('ALTER TABLE invoices ADD CONSTRAINT invoices_total_matches_parts CHECK (total_minor = subtotal_minor + tax_minor)');
        DB::statement('ALTER TABLE invoices ADD CONSTRAINT invoices_paid_not_above_total CHECK (paid_amount_minor <= total_minor)');

        Schema::create('subscription_payments', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('subscription_id')->nullable();
            $table->foreignUlid('invoice_id')->nullable();
            $table->string('status')->default(SubscriptionPaymentStatus::Pending->value)->index();
            $table->string('method');
            $table->unsignedInteger('amount_minor');
            $table->char('currency', 3)->default('DZD');
            $table->string('reference')->nullable();
            $table->string('proof_path')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'invoice_id']);
            $table->index(['tenant_id', 'subscription_id']);
            $table->foreign(['tenant_id', 'subscription_id'])
                ->references(['tenant_id', 'id'])
                ->on('subscriptions')
                ->restrictOnDelete();
            $table->foreign(['tenant_id', 'invoice_id'])
                ->references(['tenant_id', 'id'])
                ->on('invoices')
                ->restrictOnDelete();
        });

        DB::statement('ALTER TABLE subscription_payments ADD CONSTRAINT subscription_payments_amount_minor_positive CHECK (amount_minor > 0)');

        Schema::create('usage_counters', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->date('period_start');
            $table->date('period_end');
            $table->unsignedInteger('used')->default(0);
            $table->unsignedInteger('limit_value')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'key', 'period_start', 'period_end']);
            $table->index(['tenant_id', 'key']);
        });

        Schema::create('feature_flags', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_enabled')->default(false)->index();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('feature_flags');
        Schema::dropIfExists('usage_counters');
        Schema::dropIfExists('subscription_payments');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('subscriptions');
    }
};
