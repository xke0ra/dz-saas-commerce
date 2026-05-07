<?php

use App\Enums\SupportTicketCategory;
use App\Enums\SupportTicketPriority;
use App\Enums\SupportTicketStatus;
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
        Schema::create('support_tickets', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('store_id')->nullable();
            $table->foreignId('requester_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_to_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ticket_number')->unique();
            $table->string('subject');
            $table->text('description');
            $table->string('category')->default(SupportTicketCategory::General->value)->index();
            $table->string('priority')->default(SupportTicketPriority::Normal->value)->index();
            $table->string('status')->default(SupportTicketStatus::Open->value)->index();
            $table->text('resolution')->nullable();
            $table->text('internal_notes')->nullable();
            $table->timestamp('last_response_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'id'], 'support_tickets_tenant_id_id_unique');
            $table->index(['tenant_id', 'status', 'updated_at']);
            $table->index(['tenant_id', 'priority', 'status']);
            $table->index(['assigned_to_id', 'status']);
            $table->index(['store_id', 'status']);
        });

        DB::statement('ALTER TABLE support_tickets ADD CONSTRAINT support_tickets_store_same_tenant_fk FOREIGN KEY (tenant_id, store_id) REFERENCES stores (tenant_id, id) ON DELETE SET NULL (store_id)');
        DB::statement("ALTER TABLE support_tickets ADD CONSTRAINT support_tickets_category_check CHECK (category IN ('general', 'billing', 'technical', 'storefront', 'orders', 'shipping', 'domains'))");
        DB::statement("ALTER TABLE support_tickets ADD CONSTRAINT support_tickets_priority_check CHECK (priority IN ('low', 'normal', 'high', 'urgent'))");
        DB::statement("ALTER TABLE support_tickets ADD CONSTRAINT support_tickets_status_check CHECK (status IN ('open', 'pending', 'waiting_for_merchant', 'resolved', 'closed'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('support_tickets');
    }
};
