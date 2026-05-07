<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\PlatformRole;
use App\Enums\TenantPermission;
use App\Enums\TenantRole;
use App\Support\Tenancy\CurrentTenant;
use App\Support\Tenancy\TenantResolver;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'platform_role'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'platform_role' => PlatformRole::class,
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return match ($panel->getId()) {
            'admin' => $this->isSuperAdmin(),
            'vendor' => $this->canAccessVendorPanel(),
            'support' => $this->isSuperAdmin() || $this->isPlatformSupport(),
            default => false,
        };
    }

    public function isSuperAdmin(): bool
    {
        return $this->platform_role === PlatformRole::SuperAdmin;
    }

    public function isPlatformSupport(): bool
    {
        return $this->platform_role === PlatformRole::PlatformSupport;
    }

    private function canAccessVendorPanel(): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        $tenantId = request()->header('X-Tenant-ID') ?: request()->query('tenant_id');
        $tenant = app(TenantResolver::class)->resolveForUser($this, is_string($tenantId) ? $tenantId : null);

        return $this->hasTenantPermission($tenant, TenantPermission::StoresView);
    }

    /**
     * @return HasMany<Tenant, $this>
     */
    public function ownedTenants(): HasMany
    {
        return $this->hasMany(Tenant::class, 'owner_id');
    }

    /**
     * @return HasMany<SupportTicket, $this>
     */
    public function requestedSupportTickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class, 'requester_id');
    }

    /**
     * @return HasMany<SupportTicket, $this>
     */
    public function assignedSupportTickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class, 'assigned_to_id');
    }

    /**
     * @return BelongsToMany<Tenant, $this>
     */
    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class)
            ->withPivot(['role', 'permissions'])
            ->withTimestamps();
    }

    public function belongsToTenant(Tenant|string $tenant): bool
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->getKey() : $tenant;

        return $this->tenants()->whereKey($tenantId)->exists();
    }

    public function hasCurrentTenantPermission(TenantPermission|string $permission): bool
    {
        return $this->hasTenantPermission(app(CurrentTenant::class)->get(), $permission);
    }

    public function hasTenantPermission(Tenant|string|null $tenant, TenantPermission|string $permission): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        if ($tenant === null) {
            return false;
        }

        $permission = $permission instanceof TenantPermission
            ? $permission
            : TenantPermission::tryFrom($permission);

        if ($permission === null) {
            return false;
        }

        $membership = $this->tenantMembership($tenant);

        if ($membership === null) {
            return false;
        }

        $role = TenantRole::tryFrom((string) $membership->pivot->role);

        if ($role === null) {
            return false;
        }

        $overrides = $this->tenantPermissionOverrides($membership->pivot->permissions);

        if (array_key_exists($permission->value, $overrides)) {
            return $overrides[$permission->value];
        }

        return TenantPermission::defaultAllows($role, $permission);
    }

    public function tenantRole(Tenant|string $tenant): ?TenantRole
    {
        $membership = $this->tenantMembership($tenant);

        return $membership?->pivot?->role
            ? TenantRole::tryFrom($membership->pivot->role)
            : null;
    }

    private function tenantMembership(Tenant|string $tenant): ?Tenant
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->getKey() : $tenant;

        return $this->tenants()
            ->whereKey($tenantId)
            ->first();
    }

    /**
     * @return array<string, bool>
     */
    private function tenantPermissionOverrides(mixed $permissions): array
    {
        if ($permissions === null || $permissions === '') {
            return [];
        }

        if (is_string($permissions)) {
            $decoded = json_decode($permissions, true);
            $permissions = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($permissions)) {
            return [];
        }

        $overrides = [];

        foreach ($permissions as $key => $value) {
            if (is_int($key) && is_string($value)) {
                $overrides[$value] = true;

                continue;
            }

            if (is_string($key)) {
                $overrides[$key] = (bool) $value;
            }
        }

        return $overrides;
    }
}
