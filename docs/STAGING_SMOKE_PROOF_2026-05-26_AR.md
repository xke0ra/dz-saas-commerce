# إثبات Smoke للـ Staging - 2026-05-26

هذه الوثيقة تسجل نتيجة smoke خارجي يدوي على staging بعد نشر إصلاح 2FA. لا تحتوي أسراراً، ولا تثبت جاهزية production.

## بيانات التشغيل

| الحقل | القيمة |
| --- | --- |
| Date | 2026-05-26 |
| Deployed commit | `045c264` - `Fix mandatory Filament 2FA setup flow (#36)` |
| Provider | DigitalOcean |
| Droplet | `mayfair-vps` |
| Region | Frankfurt FRA1 |
| OS | Ubuntu 24.04 LTS |
| Public IPv4 | `46.101.178.27` |
| Runtime user | `deploy` |
| Docker | Installed and running |
| Docker Compose | Installed and running |
| Firewall | `ufw` enabled: OpenSSH, 80, 443 |
| Storefront URL | `https://mayfairs.app` |
| Backend/API URL | `https://api.mayfairs.app` |
| Admin URL | `https://admin.mayfairs.app` |
| Cloudflare mode | DNS only, not Proxied |

## Topology

```text
Internet
  -> Caddy :80/:443
  -> 127.0.0.1:8080
  -> nginx edge container
  -> backend/storefront
```

Caddy receives `mayfairs.app`, `www.mayfairs.app`, `api.mayfairs.app`, and `admin.mayfairs.app`, then proxies them to `127.0.0.1:8080`.

`EDGE_PORT` is intentionally set to `127.0.0.1:8080`, not `0.0.0.0:8080`, so the Docker Nginx edge is not directly public.

## Non-secret Runtime Configuration Recorded

Backend:

```dotenv
APP_ENV=staging
APP_DEBUG=false
APP_URL=https://api.mayfairs.app
ASSET_URL=https://api.mayfairs.app
TRUSTED_PROXIES=*
SESSION_DOMAIN=.mayfairs.app
SESSION_SECURE_COOKIE=true
```

Storefront:

```dotenv
NEXT_PUBLIC_API_BASE_URL=https://api.mayfairs.app
NEXT_PUBLIC_ASSET_BASE_URL=https://api.mayfairs.app
NEXT_PUBLIC_STOREFRONT_BASE_URL=https://mayfairs.app
STOREFRONT_BASE_URL=https://mayfairs.app
```

## Docker Services

| Service | Status |
| --- | --- |
| `staging-backend-1` | healthy |
| `staging-backend-queue-1` | healthy |
| `staging-backend-scheduler-1` | healthy |
| `staging-edge-1` | up |
| `staging-postgres-1` | healthy |
| `staging-redis-1` | healthy |
| `staging-storefront-1` | up |
| `staging-meilisearch-1` | up |
| `staging-minio-1` | up |
| `staging-mailpit-1` | healthy |

## HTTPS Results

| Check | Result |
| --- | --- |
| `curl -I https://mayfairs.app` | HTTP/2 200 |
| `curl -I https://api.mayfairs.app` | HTTP/2 200 |
| `curl -I https://admin.mayfairs.app` | HTTP/2 200 |

## Filament And Livewire Assets

| Check | Result |
| --- | --- |
| Filament CSS URL | HTTPS generated |
| Filament JS URL | HTTPS generated |
| Livewire script URL | HTTPS generated |
| Livewire `data-module-url` | HTTPS generated |
| Livewire `data-update-uri` | HTTPS generated |
| Mixed content | No mixed content observed in this smoke |

Examples observed:

- `https://api.mayfairs.app/css/filament/filament/app.css`
- `https://api.mayfairs.app/js/filament/filament/app.js`
- `https://api.mayfairs.app/livewire-9718984b/livewire.min.js`
- `https://api.mayfairs.app/livewire-9718984b/update`

## Filament 2FA Smoke

The smoke used an authorized staging super admin account. No password, TOTP secret, or recovery code is recorded here.

| Check | Result |
| --- | --- |
| Staging user promoted to `super_admin` | Passed |
| `super_admin` without 2FA forced to setup | Passed |
| Valid TOTP setup | Passed |
| Redirect after setup reaches dashboard without setup/challenge loop | Passed |
| Logout/login shows 2FA challenge | Passed |
| Valid TOTP challenge reaches dashboard | Passed |
| Invalid TOTP challenge is rejected | Passed |
| Reset command against user without enabled 2FA | Safe no-op |

Safe no-op output recorded:

```text
No reset performed: target user does not have two-factor authentication enabled.
```

## Demo Storefront Smoke

| Check | Result |
| --- | --- |
| Demo tenant/store exists | Passed |
| Storefront reachable at `https://mayfairs.app` | Passed |
| Store linked to domain `mayfairs.app` | Passed |
| Storefront resolve works | Passed |
| COD payment method available | Passed |
| Shipping rates available | Passed |
| Products and inventory available | Passed |
| Suitable as staging demo smoke | Passed |

This is a staging demo store only. It is not a production store and not a real customer store.

## Still Pending

- Backup automation is not deployed/proven.
- Restore drill evidence is not recorded.
- Monitoring, alerting, centralized logging, and error tracking are not implemented.
- Cloudflare Proxied mode is not enabled and requires a separate smoke for headers, assets, sessions, and 2FA before activation.
- Release rollback proof is still pending.
- Custom domains/TLS automation beyond the primary mayfairs.app domains is still pending.
