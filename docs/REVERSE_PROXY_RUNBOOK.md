# Reverse Proxy Runbook

Last updated: 2026-05-07

This runbook defines the first reverse proxy strategy for `dz-saas-commerce`. It matches the current runtime shape: Laravel backend runs as PHP-FPM on port `9000`, while the Next.js storefront runs as an HTTP server on port `3000`.

## Current Status

Implemented:

- Example Nginx edge config: `deploy/reverse-proxy/nginx-edge.conf.example`.
- Laravel trusted proxy config: `backend/config/trustedproxy.php`.
- Production env placeholder: `TRUSTED_PROXIES=`.
- Backend test proving forwarded HTTPS is trusted only from configured proxies.
- Nginx example syntax was checked with Docker using temporary host aliases for the upstream names.

Not yet proven:

- The Nginx config has not been deployed in staging.
- TLS certificate automation is not configured in this repository.
- Custom domain routing has not been exercised end to end in staging.
- Browser/e2e validation behind the proxy is still required.

## Recommended Topology

Use a managed load balancer or CDN as the public TLS termination layer, then route private HTTP traffic to the Nginx edge proxy:

```text
Internet
  -> TLS load balancer / CDN
  -> Nginx edge proxy on private network
  -> Laravel PHP-FPM backend:9000 for API/Admin/Filament
  -> Next.js storefront:3000 for public stores/custom domains
```

This keeps TLS certificates and public IP management outside the application containers. If Nginx terminates TLS directly, adapt the example config to listen on `443 ssl http2` and keep the same upstream and header rules.

## Host Routing

Backend/Admin/API hosts:

- `api.example.com`
- `admin.example.com`
- `support.example.com`
- `api.staging.example.com`
- `admin.staging.example.com`
- `support.staging.example.com`

These route to Laravel through FastCGI. The Nginx container must have Laravel public files mounted at `/var/www/html/public`, while PHP executes in the backend FPM container.
For a real staging domain that does not match the example names, update the deployed `server_name` entries before running staging smoke with `STAGING_BACKEND_HOST`.

Storefront hosts:

- `example.com`
- `*.example.com`
- `staging.example.com`
- `*.staging.example.com`
- verified custom domains

These route to the Next.js storefront and preserve the original `Host` header.

## Forwarded Headers

The edge must pass:

- `Host`
- `X-Real-IP`
- `X-Forwarded-For`
- `X-Forwarded-Host`
- `X-Forwarded-Proto`
- `X-Forwarded-Port`

Laravel must trust only the private proxy/load-balancer IPs or CIDRs via `TRUSTED_PROXIES`.

Do not set `TRUSTED_PROXIES=*` unless the backend is impossible to reach except through a trusted private proxy layer. Prefer explicit CIDRs.

## Security Headers

Laravel and Next.js already emit baseline security headers. The reverse proxy should preserve them. Add edge-only headers only after checking they do not create duplicate or conflicting CSP/HSTS behavior.

HSTS depends on Laravel seeing the request as secure. Behind a proxy, this requires:

- TLS terminated before the application.
- `X-Forwarded-Proto: https`.
- `TRUSTED_PROXIES` configured correctly.

## Body Size And Uploads

The example Nginx config sets:

```nginx
client_max_body_size 20m;
```

Review this before enabling payment proofs, support attachments, or product image uploads at larger sizes.

## Static Assets

Backend assets:

- `/build/` cached as immutable.
- `/storage/` cached for seven days in the example.

Storefront assets:

- `/_next/static/` cached as immutable.

Do not cache dynamic checkout, order tracking, admin, vendor, or support responses at the proxy layer.

## Validation

Syntax check outside the deployment network, if Docker is available:

```bash
docker run --rm \
  --add-host backend:127.0.0.1 \
  --add-host storefront:127.0.0.1 \
  -v "$PWD/deploy/reverse-proxy/nginx-edge.conf.example:/etc/nginx/nginx.conf:ro" \
  nginx:1.27-alpine nginx -t
```

Inside the deployment network, the `backend` and `storefront` service names should resolve through the container network DNS.

Laravel trusted proxy smoke:

```bash
cd backend
php artisan test tests/Feature/Security/TrustedProxyTest.php
```

Runtime smoke after staging deployment:

```bash
curl -I https://api.example.com/api/system/health/live
curl -I https://api.example.com/api/system/health/ready
curl -I https://example.com
curl -I https://demo.example.com
```

Expected:

- health endpoints return `200`.
- `Strict-Transport-Security` appears on HTTPS responses.
- the storefront sees the original host.
- admin/vendor/support routes remain on backend hosts.

## Definition Of Done

This reverse proxy phase is complete only when:

- Staging uses a documented proxy topology.
- TLS termination is configured.
- `TRUSTED_PROXIES` is set to explicit private proxy IPs/CIDRs.
- API/Admin/Filament routes reach Laravel through FastCGI.
- Storefront and custom domains reach Next.js with the original `Host`.
- Security headers are preserved and verified.
- Browser/e2e smoke passes behind the proxy.
