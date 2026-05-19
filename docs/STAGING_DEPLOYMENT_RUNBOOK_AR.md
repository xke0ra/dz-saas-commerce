# Runbook نشر Staging

آخر تحديث: 2026-05-19

هذه وثيقة تحضير لنشر staging حقيقي لاحقاً. لا تحتوي أسراراً، ولا تثبت أن staging منشور الآن. لا تستخدم هذه الوثيقة كدليل production readiness؛ هدفها أن تكون خطوات staging جاهزة فور توفر VPS أو منصة استضافة وdomain أو temporary hostname.

## 1. الهدف والنطاق

النطاق:

- تجهيز خطوات تشغيل staging غير إنتاجي لـ `dz-saas-commerce`.
- توثيق متطلبات البيئة والخدمات.
- توثيق env placeholders بدون قيم حقيقية.
- توثيق smoke checks يمكن تنفيذها لاحقاً.
- توثيق rollback notes وDefinition of Done.

خارج النطاق:

- لا نشر حقيقي في هذه الجولة.
- لا أسرار داخل repository أو docs.
- لا تغييرات في checkout أو storefront أو Filament أو business behavior.
- لا migrations أو dependencies جديدة.
- لا ادعاء أن staging الخارجي يعمل حتى يتم تنفيذ smoke proof حقيقي.

## 2. الوضع الحالي في المستودع

المستودع يحتوي مسار staging جاهز للتنفيذ لاحقاً:

- `deploy/staging/docker-compose.staging.example.yml`: backend PHP-FPM، queue worker، scheduler، storefront، وNginx edge.
- `deploy/staging/docker-compose.staging.ephemeral.yml`: PostgreSQL وRedis وMeilisearch وMinIO وMailpit disposable للـ smoke المؤقت.
- `deploy/staging/staging-smoke.sh`: validate/pull/up/verify/all/down.
- `deploy/staging/staging-ephemeral-smoke.sh`: proof محلي/runner-local دون خدمات staging حقيقية.
- `.github/workflows/staging-smoke.yml`: workflow يدعم `target=environment` و`target=ephemeral`.
- `deploy/staging/GITHUB_ENVIRONMENT.md`: عقد secrets/variables لبيئة GitHub `staging`.

هذا يعني أن المشروع لديه deployment path موثق وسكربت smoke، لكنه لا يملك real staging environment الآن. real staging يبقى pending إلى أن تتوفر VPS/provider أو منصة استضافة، domain أو temporary hostname، وخدمات staging وأسرار غير إنتاجية.

## 3. المتطلبات

Infrastructure:

- VPS أو منصة استضافة قادرة على تشغيل Docker Compose أو خدمات منفصلة.
- domain أو temporary hostname للـ storefront.
- backend hostname منفصل أو route واضح للـ API/admin/support.
- TLS/SSL عبر reverse proxy أو load balancer قبل أي smoke خارجي.
- Git access أو image registry access حسب طريقة النشر.

Backend runtime:

- PHP 8.3.
- Composer 2.
- امتدادات PHP المطلوبة من Dockerfile/CI: `bcmath`, `intl`, `pdo_pgsql`, `redis`, `zip`, `pcntl`, `opcache`.
- Laravel queue worker منفصل عن web process.
- Laravel scheduler منفصل وبنسخة واحدة فقط للبيئة.
- Storage writable، ويفضل S3-compatible object storage في staging.

Storefront runtime:

- Node.js 24.
- pnpm 11.1.2 حسب `storefront/package.json` وDockerfile.
- Next.js production build/start.

Backing services:

- PostgreSQL منفصل عن production.
- Redis للcache/session/queue.
- Meilisearch إذا كان `SCOUT_DRIVER=meilisearch`.
- S3-compatible object storage أو bucket staging منفصل.
- SMTP sandbox أو mail provider غير إنتاجي لا يرسل لعملاء حقيقيين.
- Log collection أو على الأقل وصول واضح إلى backend/storefront/edge logs.

Security prerequisites:

- Secret manager أو ملفات `.env` غير مرفوعة إلى git وبصلاحيات محدودة.
- least-privilege DB user.
- `APP_ENV=staging`.
- `APP_DEBUG=false`.
- HTTPS فقط للزيارات الخارجية.
- وصول admin/support/vendor مقيد مؤقتاً إن أمكن عبر IP allowlist أو حماية provider.

## 4. Environment Variables

هذه القيم placeholders فقط. لا ترفع ملفات env الحقيقية إلى git.

### 4.1 Backend Required Env

```dotenv
APP_NAME="DZ SaaS Commerce"
APP_ENV=staging
APP_KEY=base64:GENERATE_ON_SERVER
APP_DEBUG=false
APP_URL=<staging-backend-url>
TRUSTED_PROXIES=<proxy-cidr-or-provider-value>

LOG_CHANNEL=stack
LOG_STACK=stderr
LOG_LEVEL=info

DB_CONNECTION=pgsql
DB_HOST=<staging-postgres-host>
DB_PORT=5432
DB_DATABASE=<staging-database-name>
DB_USERNAME=<staging-db-user>
DB_PASSWORD=CHANGE_ME_IN_SECRET_STORE
DB_SSLMODE=require

CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax

REDIS_CLIENT=phpredis
REDIS_HOST=<staging-redis-host>
REDIS_PASSWORD=CHANGE_ME_IN_SECRET_STORE
REDIS_PORT=6379

FILESYSTEM_DISK=s3
PRODUCT_IMAGES_DISK=s3
AWS_ACCESS_KEY_ID=SET_IN_SECRET_STORE_ONLY
AWS_SECRET_ACCESS_KEY=SET_IN_SECRET_STORE_ONLY
AWS_DEFAULT_REGION=<staging-object-storage-region>
AWS_BUCKET=<staging-object-storage-bucket>
AWS_USE_PATH_STYLE_ENDPOINT=false

SCOUT_DRIVER=meilisearch
SCOUT_QUEUE=true
SCOUT_PREFIX=staging_
MEILISEARCH_HOST=<staging-meilisearch-url>
MEILISEARCH_KEY=SET_IN_SECRET_STORE_ONLY

MAIL_MAILER=smtp
MAIL_SCHEME=tls
MAIL_HOST=<staging-smtp-host>
MAIL_PORT=587
MAIL_USERNAME=<staging-smtp-user>
MAIL_PASSWORD=SET_IN_SECRET_STORE_ONLY
MAIL_FROM_ADDRESS=<staging-from-address>
MAIL_FROM_NAME="${APP_NAME}"
```

### 4.2 Storefront Required Env

```dotenv
NODE_ENV=production
NEXT_PUBLIC_API_BASE_URL=<staging-backend-url>
NEXT_PUBLIC_ASSET_BASE_URL=<staging-asset-base-url>
NEXT_PUBLIC_STOREFRONT_BASE_URL=<staging-storefront-url>
STOREFRONT_BASE_URL=<staging-storefront-url>
```

### 4.3 Optional Env

Backend optional:

- `AWS_URL`
- `AWS_ENDPOINT`
- `AWS_USE_PATH_STYLE_ENDPOINT=true` عند استخدام MinIO أو provider يحتاج path-style.
- `SCOUT_PREFIX` إذا كانت خدمة Meilisearch مشتركة مع index prefix واضح.
- `SESSION_DOMAIN` عند الحاجة لربط cookies على domain محدد.
- `LOG_STDERR_FORMATTER` عند اعتماد formatter مركزي.
- `DEFAULT_STORE_IDENTIFIER` للـ smoke فقط إذا كان مطلوباً.

Storefront optional:

- `NEXT_PUBLIC_DEFAULT_STORE`
- `DEFAULT_STORE_IDENTIFIER`

GitHub staging workflow optional:

- `STAGING_EDGE_PORT`
- `STAGING_EDGE_URL`
- `STAGING_TRUSTED_PROXIES`
- `STAGING_DB_PORT`
- `STAGING_DB_SSLMODE`
- `STAGING_REDIS_PORT`
- `STAGING_MAIL_SCHEME`
- `STAGING_MAIL_PORT`
- `STAGING_AWS_URL`
- `STAGING_AWS_ENDPOINT`
- `STAGING_AWS_USE_PATH_STYLE_ENDPOINT`
- `STAGING_SCOUT_PREFIX`
- `STAGING_DEFAULT_STORE_IDENTIFIER`

### 4.4 Secrets

ضع هذه القيم في secret manager أو `.env` غير مرفوع:

- `APP_KEY`
- `DB_PASSWORD`
- `REDIS_PASSWORD`
- `MEILISEARCH_KEY`
- `MAIL_PASSWORD`
- `AWS_ACCESS_KEY_ID`
- `AWS_SECRET_ACCESS_KEY`
- أي مفاتيح دفع أو شحن مستقبلية
- `STAGING_AWS_SECRET_ACCESS_KEY=SET_IN_SECRET_STORE_ONLY`

لا توثق القيم الفعلية في issues أو PRs أو logs. عند استخدام GitHub Actions، ضعها كـ environment secrets في `staging` كما هو موثق في `deploy/staging/GITHUB_ENVIRONMENT.md`.

## 5. مسار النشر الموصى به: Docker Compose Staging

هذا المسار يطابق البنية الموجودة في `deploy/staging`.

1. جهز VPS/provider مع Docker وDocker Compose.
2. افتح الوصول إلى GHCR أو registry المستخدم.
3. جهز PostgreSQL وRedis وMeilisearch وobject storage وSMTP staging.
4. جهز DNS أو temporary hostname وTLS/reverse proxy.
5. انسخ repository أو فقط deploy files إلى السيرفر:

```bash
git clone <repo-url> dz-saas-commerce
cd dz-saas-commerce
git checkout main
```

6. انسخ ملفات env غير المتتبعة:

```bash
cp deploy/staging/images.env.example deploy/staging/images.env
cp deploy/staging/backend.env.example deploy/staging/backend.env
cp deploy/staging/storefront.env.example deploy/staging/storefront.env
```

7. استبدل كل placeholders بقيم staging غير إنتاجية. استخدم immutable image tags أو digests فقط:

```dotenv
BACKEND_IMAGE=<immutable-backend-image-tag-or-digest>
STOREFRONT_IMAGE=<immutable-storefront-image-tag-or-digest>
BACKEND_ENV_FILE=./backend.env
STOREFRONT_ENV_FILE=./storefront.env
EDGE_PORT=<internal-edge-port>
```

8. تحقق من contract قبل التشغيل:

```bash
deploy/staging/staging-smoke.sh validate
```

9. اسحب الصور وشغل stack:

```bash
deploy/staging/staging-smoke.sh pull
deploy/staging/staging-smoke.sh up
```

10. نفذ migrations بعد backup مناسب وقبل `verify`:

```bash
docker compose \
  --project-name dz-saas-staging \
  --env-file deploy/staging/images.env \
  -f deploy/staging/docker-compose.staging.example.yml \
  exec -T backend php artisan migrate --force
```

11. شغل seed آمن فقط إذا كان معتمداً لبيئة staging ولا يستخدم production data:

```bash
docker compose \
  --project-name dz-saas-staging \
  --env-file deploy/staging/images.env \
  -f deploy/staging/docker-compose.staging.example.yml \
  exec -T backend php artisan db:seed --class=StorefrontDemoSeeder --force
```

12. شغل smoke verification:

```bash
deploy/staging/staging-smoke.sh verify
```

لا تستخدم `deploy/staging/staging-smoke.sh all` ضد real staging إلا بعد التأكد من أن قاعدة البيانات جاهزة وأن migrations/seed preconditions محسومة.

## 6. مسار بديل: Source Deployment على VPS

استخدم هذا فقط إذا لم تكن تستخدم images/Compose.

### 6.1 Backend Deployment Steps

```bash
git clone <repo-url> dz-saas-commerce
cd dz-saas-commerce
git checkout main
cd backend
composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
cp .env.production.example .env
```

ثم ضع قيم staging في `.env` غير مرفوع. إذا لم يكن `APP_KEY` موجوداً:

```bash
php artisan key:generate --force
```

قبل migrations:

```bash
php artisan optimize:clear
php artisan migrate:status
```

بعد backup ونافذة تشغيل معلنة:

```bash
php artisan migrate --force
```

Storage:

```bash
php artisan storage:link
```

شغل هذا فقط إذا كان staging يستخدم public/local storage أو يحتاج symlink للملفات العامة. عند استخدام S3 كـ `FILESYSTEM_DISK=s3` لا تعتمد على symlink كدليل جاهزية storage؛ اعتمد على readiness check.

Cache:

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Queue/scheduler:

```bash
php artisan queue:restart
php artisan queue:work redis --tries=3 --timeout=90 --sleep=3 --max-time=3600
php artisan schedule:work
```

في التشغيل الحقيقي، لا تشغل worker/scheduler كجلسة shell دائمة. استخدم systemd/Supervisor/container services كما في `docs/QUEUE_SCHEDULER_RUNBOOK.md`.

Health:

```bash
php artisan system:health --scope=live --format=json
php artisan system:health --scope=ready --format=json
php artisan route:list
php artisan queue:failed
```

### 6.2 Storefront Deployment Steps

```bash
cd storefront
cp .env.production.example .env.production
pnpm install --frozen-lockfile
pnpm build
pnpm start
```

في service manager:

```bash
pnpm start
```

أو استخدم Dockerfile الحالي إذا كان النشر image-based.

تحقق من اتصال storefront بالـ backend:

```bash
curl -fsS --connect-timeout 5 --max-time 20 \
  "<staging-backend-url>/api/storefront/resolve?host=<staging-storefront-host>"
```

لـ product detail smoke عند وجود store وproduct آمنين:

```bash
curl -fsS --connect-timeout 5 --max-time 20 \
  "<staging-backend-url>/api/storefront/<store-identifier>/products/<product-slug>"
```

## 7. Smoke Checks

### 7.1 Backend CLI

```bash
php artisan migrate --force
php artisan migrate:status
php artisan route:list
php artisan system:health --scope=live --format=json
php artisan system:health --scope=ready --format=json
php artisan queue:failed
php artisan schedule:list
```

يمكن تشغيل subset من tests فقط إذا كان ذلك مناسباً staging ولا يلمس بيانات حقيقية:

```bash
php artisan test --filter=<safe-smoke-filter>
```

لا تشغل اختبارات تكتب بيانات غير معزولة على قاعدة staging حقيقية إلا بعد موافقة صريحة.

### 7.2 Backend HTTP/API

```bash
curl -fsS --connect-timeout 5 --max-time 20 \
  "<staging-backend-url>/api/system/health/live"

curl -fsS --connect-timeout 5 --max-time 20 \
  "<staging-backend-url>/api/system/health/ready"

curl -fsS --connect-timeout 5 --max-time 20 \
  "<staging-backend-url>/api/storefront/resolve?host=<staging-storefront-host>"

curl -fsS --connect-timeout 5 --max-time 20 \
  "<staging-backend-url>/api/storefront/<store-identifier>/products"

curl -fsS --connect-timeout 5 --max-time 20 \
  "<staging-backend-url>/api/storefront/<store-identifier>/products/<product-slug>"
```

### 7.3 Storefront

```bash
curl -fsSIL --connect-timeout 5 --max-time 20 "<staging-storefront-url>/"
curl -fsSIL --connect-timeout 5 --max-time 20 "<staging-storefront-url>/products"
curl -fsSIL --connect-timeout 5 --max-time 20 "<staging-storefront-url>/products/<product-slug>"
```

Manual browser smoke:

- homepage loads.
- product listing loads.
- product detail loads.
- variant picker يعمل إذا كان product variable.
- quick checkout smoke فقط على test store/payment وبيانات staging آمنة.
- لا تستخدم production customer data أو production payment/shipping integrations.

### 7.4 Docker Smoke Runner

```bash
deploy/staging/staging-smoke.sh validate
deploy/staging/staging-smoke.sh pull
deploy/staging/staging-smoke.sh up
deploy/staging/staging-smoke.sh verify
```

`verify` يفحص process state، Laravel readiness، failed jobs، storefront edge response، وHTTP live/ready health عبر edge proxy.

## 8. Rollback Notes

قبل أي migration:

- خذ backup حديث لقاعدة PostgreSQL.
- سجل image tag/digest الحالي.
- سجل commit SHA الحالي.
- تأكد من أن `.env` محفوظ ولا يتم استبداله عند rollback.

Rollback image/commit:

```bash
git checkout <previous-known-good-commit>
```

أو في Compose:

```dotenv
BACKEND_IMAGE=<previous-known-good-backend-image>
STOREFRONT_IMAGE=<previous-known-good-storefront-image>
```

ثم:

```bash
deploy/staging/staging-smoke.sh pull
deploy/staging/staging-smoke.sh up
deploy/staging/staging-smoke.sh verify
```

Laravel:

```bash
php artisan queue:restart
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

لا تستخدم `php artisan migrate:rollback` تلقائياً. rollback للـ migrations يحتاج مراجعة migration نفسها، أثر البيانات، وbackup حديث. في التغييرات destructive، الاسترجاع من backup قد يكون أوضح من rollback جزئي.

## 9. Security Notes

- لا ترفع `.env`, `deploy/staging/backend.env`, `deploy/staging/storefront.env`, أو `deploy/staging/images.env` إلى git.
- لا تضع secrets في docs أو PR descriptions أو logs.
- استخدم HTTPS للزيارات الخارجية.
- استخدم least-privilege DB user.
- اضبط `APP_ENV=staging`.
- اضبط `APP_DEBUG=false`.
- اضبط `SESSION_SECURE_COOKIE=true` عند وجود HTTPS.
- لا تستخدم production secrets في staging.
- اجعل object storage bucket منفصلاً عن production.
- اجعل Meilisearch index prefix منفصلاً أو خدمة منفصلة.
- استخدم SMTP sandbox أو mail provider غير إنتاجي.
- قيّد admin/support/vendor access مؤقتاً إن أمكن.
- افحص logs بعد smoke للتأكد من عدم وجود PII أو secrets.

## 10. Definition Of Done

لا يعتبر real staging جاهزاً إلا عند تحقق كل البنود التالية:

- live health ok عبر URL staging.
- ready health ok عبر URL staging ويغطي PostgreSQL وRedis/cache/queue/storage/search عند تفعيلها.
- backend logs لا تحتوي errors جديدة أثناء smoke.
- queue worker يعمل ولا توجد failed jobs.
- scheduler يعمل وبنسخة واحدة فقط.
- storefront build ok.
- storefront homepage reachable.
- product listing reachable.
- product detail reachable.
- checkout smoke موثق على test store/payment فقط.
- image tag أو commit SHA المستخدم موثق.
- migration status موثق.
- لا أسرار committed.
- نتيجة smoke محفوظة في `docs/STAGING_SMOKE_PROOF_TEMPLATE_AR.md` أو سجل عمليات خارجي.

## 11. مراجع مرتبطة

- `deploy/staging/README.md`
- `deploy/staging/GITHUB_ENVIRONMENT.md`
- `docs/STAGING_READINESS_CHECKLIST_AR.md`
- `docs/STAGING_SMOKE_PROOF_TEMPLATE_AR.md`
- `docs/REVERSE_PROXY_RUNBOOK.md`
- `docs/QUEUE_SCHEDULER_RUNBOOK.md`
- `docs/BACKUP_RESTORE_RUNBOOK.md`
- `docs/MONITORING_ALERTING_RUNBOOK.md`
- `docs/PRODUCTION_READINESS.md`
