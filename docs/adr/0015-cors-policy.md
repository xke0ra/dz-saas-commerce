# ADR 0015: CORS Policy

Date: 2026-05-31

Status: Proposed

## Context

The platform currently has no explicit CORS configuration found in the codebase or documentation. The storefront (Next.js) calls the Laravel API, and Filament panels make Livewire requests. Without explicit CORS rules, cross-origin requests rely on Laravel's default CORS behavior.

Current state:

- No `config/cors.php` customization found beyond framework default.
- `docs/SECURITY_BASELINE.md` does not mention CORS.
- `docs/ARCHITECTURE.md` does not define allowed origins.
- Filament panels use same-origin requests (no CORS needed for panel resources).
- Storefront BFF routes (`storefront/src/app/api/`) proxy to Laravel — so the browser talks to Next.js, not Laravel directly. This reduces CORS exposure.

Key questions to decide:

1. Which origins are allowed to call the Laravel API directly?
2. Are there any endpoints that should be accessible from third-party origins?
3. How should pre-flight OPTIONS requests be handled for the storefront API?

## Decision

[TO BE DECIDED — define allowed origins and configure middleware]

Proposed minimal policy:

```php
// config/cors.php
'paths' => ['api/*'],
'allowed_methods' => ['GET', 'POST'],
'allowed_origins' => [
    env('STOREFRONT_BASE_URL'),    // e.g. https://mayfairs.app
    env('ADMIN_BASE_URL'),         // e.g. https://admin.mayfairs.app
],
'allowed_origins_patterns' => [
    // Custom store domains when implemented
    // e.g. /^https:\/\/[a-z0-9-]+\.mayfairs\.app$/
],
'allowed_headers' => ['Content-Type', 'Accept', 'Idempotency-Key'],
'exposed_headers' => [],
'max_age' => 0,
'supports_credentials' => false,
```

## Consequences

- Wildcard `allowed_origins: ['*']` is **not permitted** for authenticated endpoints.
- CORS must be re-evaluated when custom domain stores go live (third-party domains calling the API).
- Pre-flight OPTIONS requests must respond quickly (< 50ms).
- `supports_credentials: false` is correct because storefront uses stateless API tokens, not cookies.
- Filament panels use same-origin requests — they don't need CORS rules.

This ADR moves to `Accepted` when:

1. `config/cors.php` is explicitly configured with named allowed origins.
2. The storefront can successfully call the Laravel API in staging without CORS errors.
3. A test verifies that requests from an unlisted origin are rejected.
4. The allowed origins list is documented in this ADR.
