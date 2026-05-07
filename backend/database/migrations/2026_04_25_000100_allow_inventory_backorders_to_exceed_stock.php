<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE inventory_items DROP CONSTRAINT inventory_items_reserved_not_above_quantity');
        DB::statement('ALTER TABLE inventory_items ADD CONSTRAINT inventory_items_reserved_not_above_quantity CHECK (allow_backorders OR reserved_quantity <= quantity)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE inventory_items DROP CONSTRAINT inventory_items_reserved_not_above_quantity');
        DB::statement('ALTER TABLE inventory_items ADD CONSTRAINT inventory_items_reserved_not_above_quantity CHECK (reserved_quantity <= quantity)');
    }
};
