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
        Schema::create('theme_settings', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('store_id')->constrained()->cascadeOnDelete();
            $table->string('theme_name')->default('default');
            $table->string('primary_color', 7)->default('#107062');
            $table->string('accent_color', 7)->default('#b54836');
            $table->string('background_color', 7)->default('#f7f9fa');
            $table->string('foreground_color', 7)->default('#161c24');
            $table->string('heading_font')->nullable();
            $table->string('body_font')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('favicon_path')->nullable();
            $table->string('hero_image_path')->nullable();
            $table->string('hero_title')->nullable();
            $table->text('hero_subtitle')->nullable();
            $table->string('product_card_style')->default('standard');
            $table->jsonb('layout_settings')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('store_id');
            $table->foreign(['tenant_id', 'store_id'])
                ->references(['tenant_id', 'id'])
                ->on('stores')
                ->cascadeOnDelete();
            $table->index(['tenant_id', 'store_id', 'is_active']);
        });

        DB::statement("ALTER TABLE theme_settings ADD CONSTRAINT theme_settings_primary_color_hex CHECK (primary_color ~ '^#[0-9A-Fa-f]{6}$')");
        DB::statement("ALTER TABLE theme_settings ADD CONSTRAINT theme_settings_accent_color_hex CHECK (accent_color ~ '^#[0-9A-Fa-f]{6}$')");
        DB::statement("ALTER TABLE theme_settings ADD CONSTRAINT theme_settings_background_color_hex CHECK (background_color ~ '^#[0-9A-Fa-f]{6}$')");
        DB::statement("ALTER TABLE theme_settings ADD CONSTRAINT theme_settings_foreground_color_hex CHECK (foreground_color ~ '^#[0-9A-Fa-f]{6}$')");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('theme_settings');
    }
};
