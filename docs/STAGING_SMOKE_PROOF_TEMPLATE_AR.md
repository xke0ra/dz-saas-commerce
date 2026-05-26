# قالب إثبات Smoke للـ Staging

آخر تحديث: 2026-05-26

هذه الوثيقة قالب يملؤه المشغل بعد كل staging smoke خارجي. لا تضع أسراراً هنا.

## بيانات التشغيل

| الحقل | القيمة |
| --- | --- |
| Date |  |
| Commit SHA |  |
| Environment URL |  |
| Backend URL |  |
| Storefront URL |  |
| Image tags/digests |  |
| Edge bind (`EDGE_PORT`) |  |
| Public proxy |  |
| Cloudflare mode |  |
| Operator |  |

## Backend Proof

| الفحص | النتيجة |
| --- | --- |
| `php artisan migrate --force` |  |
| Migration status |  |
| `php artisan route:list` route count |  |
| `php artisan system:health --scope=live --format=json` |  |
| `php artisan system:health --scope=ready --format=json` |  |
| `GET /api/system/health/live` |  |
| `GET /api/system/health/ready` |  |
| `GET /api/storefront/resolve?host=<staging-storefront-host>` |  |
| `curl -I https://mayfairs.app` |  |
| `curl -I https://api.mayfairs.app` |  |
| `curl -I https://admin.mayfairs.app` |  |
| Filament CSS URL check |  |
| Filament JS URL check |  |
| Livewire asset URL HTTPS check |  |
| Mixed content grep result |  |
| Failed jobs result |  |
| Scheduler check |  |

## Filament 2FA Smoke

نفذ هذا على حساب staging إداري مصرح به فقط، ولا تنسخ TOTP أو recovery codes إلى هذه الوثيقة.

| الفحص | النتيجة |
| --- | --- |
| Admin login page loads with CSS |  |
| Required super admin without 2FA redirects to setup |  |
| Setup QR/TOTP accepted |  |
| `two_factor_confirmed_at` and `two_factor_enabled_at` set |  |
| Session contains `auth.2fa.user_id` and `auth.2fa.confirmed_at` |  |
| Redirect after setup reaches dashboard, not setup/challenge |  |
| Logout/login shows 2FA challenge |  |
| Correct TOTP challenge reaches dashboard |  |
| Incorrect TOTP challenge shows validation error |  |
| Recovery code smoke, if safe to consume one |  |
| Reset command dry run output |  |
| Reset command actual run, if performed |  |
| Login after reset returns to setup |  |

## Storefront Proof

| الفحص | النتيجة |
| --- | --- |
| Storefront build result |  |
| Storefront service start result |  |
| Homepage smoke |  |
| Product listing smoke |  |
| Product detail smoke |  |
| Backend API connection from storefront |  |

## Checkout Smoke

نفذ هذا الفحص فقط على test store/payment وبيانات staging آمنة، وليس على production data.

| الحقل | القيمة |
| --- | --- |
| Store identifier |  |
| Product slug/id |  |
| Payment method |  |
| Shipping/commune test data |  |
| Checkout smoke result |  |
| Variant checkout smoke result, if seeded |  |
| Created order reference |  |
| Cleanup needed |  |

## Known Issues

-

## Attachments / Evidence Links

- Workflow run:
- Logs location:
- Screenshot or browser proof:
- Rollback reference:
