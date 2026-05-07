<?php

namespace Database\Seeders;

use App\Enums\PlatformRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Seed a platform super admin when credentials are explicitly configured.
     */
    public function run(): void
    {
        $email = env('SUPER_ADMIN_EMAIL') ?: env('ADMIN_EMAIL');
        $password = env('SUPER_ADMIN_PASSWORD') ?: env('ADMIN_PASSWORD');

        if (! $email || ! $password) {
            $this->command?->warn('Skipping super admin seeding: set SUPER_ADMIN_EMAIL and SUPER_ADMIN_PASSWORD to create one.');

            return;
        }

        User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => env('SUPER_ADMIN_NAME', 'Platform Super Admin'),
                'password' => Hash::make($password),
                'platform_role' => PlatformRole::SuperAdmin,
            ],
        );
    }
}
