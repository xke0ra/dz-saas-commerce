<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Tenancy\TenantSwitcher;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SwitchTenantController extends Controller
{
    public function __invoke(Request $request, TenantSwitcher $tenantSwitcher): RedirectResponse
    {
        $validated = $request->validate([
            'tenant_id' => ['required', 'string', 'exists:tenants,id'],
        ]);

        $user = $request->user();

        if (! $user instanceof User) {
            throw new AuthorizationException;
        }

        $tenant = $tenantSwitcher->findAvailableTenantFor($user, $validated['tenant_id']);

        if ($tenant === null) {
            throw new AuthorizationException('You cannot switch to this tenant.');
        }

        $request->session()->put(TenantSwitcher::SESSION_KEY, $tenant->id);

        return back()->with('status', "Tenant switched to {$tenant->name}.");
    }
}
