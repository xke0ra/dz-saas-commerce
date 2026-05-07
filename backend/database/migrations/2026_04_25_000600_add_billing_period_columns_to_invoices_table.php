<?php

use App\Enums\InvoiceType;
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
        Schema::table('invoices', function (Blueprint $table): void {
            $table->string('type')->default(InvoiceType::SubscriptionInitial->value);
            $table->timestamp('billing_period_starts_at')->nullable();
            $table->timestamp('billing_period_ends_at')->nullable();

            $table->index(['tenant_id', 'type', 'status'], 'invoices_tenant_type_status_index');
            $table->index(['tenant_id', 'subscription_id', 'type'], 'invoices_subscription_type_index');
            $table->unique(
                ['tenant_id', 'subscription_id', 'type', 'billing_period_starts_at'],
                'invoices_subscription_period_unique'
            );
        });

        DB::statement('
            UPDATE invoices
            SET
                billing_period_starts_at = subscriptions.current_period_starts_at,
                billing_period_ends_at = subscriptions.current_period_ends_at
            FROM subscriptions
            WHERE invoices.subscription_id = subscriptions.id
              AND invoices.billing_period_starts_at IS NULL
              AND invoices.type = ?
        ', [InvoiceType::SubscriptionInitial->value]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropUnique('invoices_subscription_period_unique');
            $table->dropIndex('invoices_subscription_type_index');
            $table->dropIndex('invoices_tenant_type_status_index');
            $table->dropColumn(['type', 'billing_period_starts_at', 'billing_period_ends_at']);
        });
    }
};
