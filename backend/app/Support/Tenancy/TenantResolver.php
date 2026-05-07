<?php

namespace App\Support\Tenancy;

use App\Enums\DomainStatus;
use App\Models\Domain;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TenantResolver
{
    public function resolveFromRequest(Request $request): ?Tenant
    {
        $tenant = $this->resolveFromHost($request->getHost());

        if ($tenant !== null) {
            return $tenant;
        }

        $user = $request->user();

        if (! $user instanceof User) {
            return null;
        }

        $tenantId = $this->tenantIdFromRequest($request);

        return $this->resolveForUser($user, $tenantId);
    }

    public function resolveFromHost(?string $host): ?Tenant
    {
        return $this->resolveStoreFromHost($host)?->tenant;
    }

    public function resolveStoreFromHost(?string $host): ?Store
    {
        $host = $this->normalizeHost($host);

        if ($host === null) {
            return null;
        }

        $domain = Domain::query()
            ->withoutGlobalScope('current_tenant')
            ->with(['store.tenant', 'store.storeSetting', 'store.themeSetting'])
            ->where('hostname', $host)
            ->where('status', DomainStatus::Active->value)
            ->whereNotNull('verified_at')
            ->first();

        if ($domain?->store !== null) {
            return $domain->store;
        }

        $store = Store::query()
            ->with(['tenant', 'storeSetting', 'themeSetting'])
            ->where('domain', $host)
            ->first();

        if ($store !== null) {
            return $store;
        }

        $subdomain = $this->extractSubdomain($host);

        if ($subdomain === null) {
            return null;
        }

        return Store::query()
            ->with(['tenant', 'storeSetting', 'themeSetting'])
            ->where('subdomain', $subdomain)
            ->first();
    }

    public function resolveForUser(User $user, ?string $tenantId = null): ?Tenant
    {
        if ($tenantId !== null) {
            $query = Tenant::query()->whereKey($tenantId);

            if (! $user->isSuperAdmin()) {
                $query->whereHas('users', fn ($users) => $users->whereKey($user->getKey()));
            }

            return $query->first();
        }

        return $user->tenants()->oldest('tenants.created_at')->first();
    }

    private function normalizeHost(?string $host): ?string
    {
        if ($host === null || $host === '') {
            return null;
        }

        return Str::of($host)
            ->lower()
            ->before(':')
            ->trim()
            ->toString();
    }

    private function extractSubdomain(string $host): ?string
    {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false || $host === 'localhost') {
            return null;
        }

        $labels = explode('.', $host);

        if (count($labels) < 3) {
            return null;
        }

        $subdomain = $labels[0];

        return $subdomain === 'www' ? null : $subdomain;
    }

    private function tenantIdFromRequest(Request $request): ?string
    {
        $tenantId = $request->header('X-Tenant-ID') ?: $request->query('tenant_id');

        if (is_string($tenantId) && $tenantId !== '') {
            return $tenantId;
        }

        if (! $request->hasSession()) {
            return null;
        }

        $tenantId = $request->session()->get(TenantSwitcher::SESSION_KEY);

        return is_string($tenantId) && $tenantId !== '' ? $tenantId : null;
    }
}
