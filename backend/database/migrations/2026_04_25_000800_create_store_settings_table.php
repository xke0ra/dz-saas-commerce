<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('store_settings', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('store_id')->constrained()->cascadeOnDelete();
            $table->string('seller_name')->nullable();
            $table->string('seller_address')->nullable();
            $table->string('commercial_registration_number')->nullable();
            $table->string('tax_identification_number')->nullable();
            $table->string('public_email')->nullable();
            $table->string('public_phone', 32)->nullable();
            $table->string('support_phone', 32)->nullable();
            $table->string('whatsapp_phone', 32)->nullable();
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->string('announcement_text')->nullable();
            $table->longText('terms_content')->nullable();
            $table->longText('privacy_content')->nullable();
            $table->longText('return_policy_content')->nullable();
            $table->longText('shipping_policy_content')->nullable();
            $table->jsonb('social_links')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->unique('store_id');
            $table->foreign(['tenant_id', 'store_id'])
                ->references(['tenant_id', 'id'])
                ->on('stores')
                ->cascadeOnDelete();
            $table->index(['tenant_id', 'store_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('store_settings');
    }
};
