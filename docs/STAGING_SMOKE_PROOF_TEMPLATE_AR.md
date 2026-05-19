# قالب إثبات Smoke للـ Staging

هذه الوثيقة قالب يملؤه المشغل لاحقاً بعد توفر real staging environment. لا تحتوي نتائج حالية، ولا تعني أن staging تم نشره.

## بيانات التشغيل

| الحقل | القيمة |
| --- | --- |
| Date |  |
| Commit SHA |  |
| Environment URL |  |
| Backend URL |  |
| Storefront URL |  |
| Image tags/digests |  |
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
| Failed jobs result |  |
| Scheduler check |  |

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
| Created order reference |  |
| Cleanup needed |  |

## Known Issues

-

## Attachments / Evidence Links

- Workflow run:
- Logs location:
- Screenshot or browser proof:
- Rollback reference:
