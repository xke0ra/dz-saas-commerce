<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\CurrentTenant;
use App\Support\Tenancy\TenantResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenantFromRequest
{
    public function __construct(
        private readonly CurrentTenant $currentTenant,
        private readonly TenantResolver $tenantResolver,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $this->currentTenant->set($this->tenantResolver->resolveFromRequest($request));

        try {
            return $next($request);
        } finally {
            $this->currentTenant->forget();
        }
    }
}
