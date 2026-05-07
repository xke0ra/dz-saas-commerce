<?php

use App\Enums\PlatformRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::table('users')->count() !== 1) {
            return;
        }

        if (DB::table('users')->where('platform_role', PlatformRole::SuperAdmin->value)->exists()) {
            return;
        }

        DB::table('users')
            ->whereNull('platform_role')
            ->update(['platform_role' => PlatformRole::SuperAdmin->value]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
