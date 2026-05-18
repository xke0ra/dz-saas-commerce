<?php

use App\Enums\ProductType;
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
        Schema::table('products', function (Blueprint $table): void {
            $table->string('type')->default(ProductType::Simple->value)->after('status');
            $table->index(['tenant_id', 'type'], 'products_tenant_type_idx');
        });

        DB::statement("ALTER TABLE products ADD CONSTRAINT products_type_check CHECK (type IN ('simple', 'variable'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE products DROP CONSTRAINT IF EXISTS products_type_check');

        Schema::table('products', function (Blueprint $table): void {
            $table->dropIndex('products_tenant_type_idx');
            $table->dropColumn('type');
        });
    }
};
