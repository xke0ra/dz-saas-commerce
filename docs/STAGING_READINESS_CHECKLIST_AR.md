# Staging Readiness Checklist

آخر تحديث: 2026-05-27

Checklist تنفيذية قصيرة قبل تشغيل real staging smoke وبعد كل نشر. لا تستبدل هذه الوثيقة runbooks الموجودة؛ استخدمها كبوابة قبل `deploy/staging/staging-smoke.sh`، وراجع `docs/STAGING_DEPLOYMENT_RUNBOOK_AR.md` للخطوات التفصيلية. الحالة الحالية مثبتة جزئياً في `docs/STAGING_SMOKE_PROOF_2026-05-26_AR.md`.

مراجع أساسية:

- `deploy/staging/README.md`
- `deploy/staging/GITHUB_ENVIRONMENT.md`
- `docs/STAGING_DEPLOYMENT_RUNBOOK_AR.md`
- `docs/STAGING_SMOKE_PROOF_TEMPLATE_AR.md`
- `docs/STAGING_SMOKE_PROOF_2026-05-26_AR.md`
- `docs/REVERSE_PROXY_RUNBOOK.md`
- `docs/QUEUE_SCHEDULER_RUNBOOK.md`
- `docs/PRODUCTION_READINESS.md`

## 1. Infrastructure

- [x] VPS/provider محدد: DigitalOcean droplet `mayfair-vps` في FRA1.
- [x] VPS created and reachable through the public domains.
- [x] Docker installed and running.
- [x] Docker Compose installed and running.
- [x] Caddy installed as the public reverse proxy.
- [x] staging storefront domain محدد: `mayfairs.app`.
- [x] backend/admin hosts محددة: `api.mayfairs.app` و`admin.mayfairs.app`.
- [x] TLS/reverse proxy جاهز عبر Caddy أمام Nginx edge الداخلي.
- [x] backend runtime يعمل كـ PHP-FPM service داخل Docker.
- [x] storefront runtime يعمل كـ Next.js service داخل Docker.
- [x] `STAGING_BACKEND_HOST` و`STAGING_STOREFRONT_HOST` يمكن مطابقتهما مع host routing في Caddy/Nginx.
- [x] queue worker service منفصل عن web.
- [x] scheduler service منفصل، وبنسخة واحدة فقط للبيئة.
- [x] PostgreSQL staging يعمل داخل overlay الحالي، ومنفصل عن production.
- [x] Redis staging يعمل داخل overlay الحالي.
- [x] Meilisearch staging يعمل داخل overlay الحالي.
- [x] object storage staging يعمل عبر MinIO داخل overlay الحالي.
- [x] Mailpit sandbox يعمل داخل overlay الحالي ولا يرسل لعملاء حقيقيين.
- [x] `EDGE_PORT=127.0.0.1:8080` حتى لا يكون Nginx الداخلي عاماً.
- [x] Cloudflare DNS only حالياً، وليس Proxied.

## 1.1 Current Staging Verification

- [x] Deployed commit recorded: `045c264` (`Fix mandatory Filament 2FA setup flow (#36)`).
- [x] Caddy routes `mayfairs.app`, `www.mayfairs.app`, `api.mayfairs.app`, و`admin.mayfairs.app` إلى `127.0.0.1:8080`.
- [x] `curl -I https://mayfairs.app` يرد `HTTP/2 200`.
- [x] `curl -I https://api.mayfairs.app` يرد `HTTP/2 200`.
- [x] `curl -I https://admin.mayfairs.app` يرد `HTTP/2 200`.
- [x] Filament CSS/JS يولدان HTTPS.
- [x] Livewire script و`data-module-url` و`data-update-uri` يولدون HTTPS.
- [x] لا يوجد mixed content مرصود في smoke الحالي.
- [x] backend, queue, scheduler, PostgreSQL, Redis, Mailpit health reported healthy.
- [x] edge, storefront, Meilisearch, وMinIO reported up.
- [x] mandatory 2FA setup smoke passed without redirect loop.
- [x] 2FA challenge بعد logout/login passed.
- [x] invalid TOTP was rejected.
- [x] reset command for a target without enabled 2FA returned a safe no-op.
- [x] staging demo tenant/store exists.
- [x] demo storefront reachable at `https://mayfairs.app`.
- [x] demo store is linked to `mayfairs.app`.
- [x] COD payment method, shipping rates, products, and inventory exist for the demo store.
- [x] storefront resolve works for the demo store.
- [ ] Backup automation deployed and monitored.
- [x] Restore drill executed and recorded (2026-05-28; see `BACKUP_RESTORE_DRILL_PROOF_2026-05-28.md`).
- [ ] Monitoring/alerting/log aggregation/error tracking implemented.
- [ ] Cloudflare Proxied mode enabled after headers/assets/session/2FA smoke.
- [ ] Release rollback proof recorded.

## 2. Required Env Checklist

لا تكتب قيماً حقيقية هنا. هذه أسماء مفاتيح مطلوبة أو اختيارية فقط.

Backend required env:

- [ ] `APP_NAME`
- [ ] `APP_ENV`
- [ ] `APP_KEY`
- [ ] `APP_DEBUG`
- [ ] `APP_URL`
- [ ] `ASSET_URL`
- [ ] `TRUSTED_PROXIES`
- [ ] `LOG_CHANNEL`
- [ ] `LOG_STACK`
- [ ] `LOG_LEVEL`
- [ ] `DB_CONNECTION`
- [ ] `DB_HOST`
- [ ] `DB_PORT`
- [ ] `DB_DATABASE`
- [ ] `DB_USERNAME`
- [ ] `DB_PASSWORD`
- [ ] `DB_SSLMODE`
- [ ] `CACHE_STORE`
- [ ] `QUEUE_CONNECTION`
- [ ] `SESSION_DRIVER`
- [ ] `SESSION_ENCRYPT`
- [ ] `SESSION_DOMAIN`
- [ ] `SESSION_SECURE_COOKIE`
- [ ] `SESSION_SAME_SITE`
- [ ] `REDIS_CLIENT`
- [ ] `REDIS_HOST`
- [ ] `REDIS_PASSWORD`
- [ ] `REDIS_PORT`
- [ ] `FILESYSTEM_DISK`
- [ ] `PRODUCT_IMAGES_DISK`
- [ ] `AWS_ACCESS_KEY_ID`
- [ ] `AWS_SECRET_ACCESS_KEY`
- [ ] `AWS_DEFAULT_REGION`
- [ ] `AWS_BUCKET`
- [ ] `AWS_USE_PATH_STYLE_ENDPOINT`
- [ ] `SCOUT_DRIVER`
- [ ] `SCOUT_QUEUE`
- [ ] `SCOUT_PREFIX`
- [ ] `MEILISEARCH_HOST`
- [ ] `MEILISEARCH_KEY`
- [ ] `MAIL_MAILER`
- [ ] `MAIL_SCHEME`
- [ ] `MAIL_HOST`
- [ ] `MAIL_PORT`
- [ ] `MAIL_USERNAME`
- [ ] `MAIL_PASSWORD`
- [ ] `MAIL_FROM_ADDRESS`
- [ ] `MAIL_FROM_NAME`

Storefront required env:

- [ ] `NODE_ENV`
- [ ] `NEXT_PUBLIC_API_BASE_URL`
- [ ] `NEXT_PUBLIC_ASSET_BASE_URL`
- [ ] `NEXT_PUBLIC_STOREFRONT_BASE_URL`
- [ ] `STOREFRONT_BASE_URL`

Non-secret mayfairs staging values recorded in current proof:

- [x] `APP_ENV=staging`
- [x] `APP_DEBUG=false`
- [x] `APP_URL=https://api.mayfairs.app`
- [x] `ASSET_URL=https://api.mayfairs.app`
- [x] `TRUSTED_PROXIES=*` with the edge bound to `127.0.0.1:8080`
- [x] `SESSION_DOMAIN=.mayfairs.app`
- [x] `SESSION_SECURE_COOKIE=true`
- [x] `NEXT_PUBLIC_API_BASE_URL=https://api.mayfairs.app`
- [x] `NEXT_PUBLIC_ASSET_BASE_URL=https://api.mayfairs.app`
- [x] `NEXT_PUBLIC_STOREFRONT_BASE_URL=https://mayfairs.app`
- [x] `STOREFRONT_BASE_URL=https://mayfairs.app`

Optional smoke env:

- [ ] `NEXT_PUBLIC_DEFAULT_STORE`
- [ ] `DEFAULT_STORE_IDENTIFIER`
- [ ] `STAGING_EDGE_URL`
- [ ] `STAGING_EDGE_PORT`

Secrets يجب أن تكون في secret manager أو env file غير مرفوع:

- [ ] `APP_KEY`
- [ ] `DB_PASSWORD`
- [ ] `REDIS_PASSWORD`
- [ ] `MEILISEARCH_KEY`
- [ ] `MAIL_PASSWORD`
- [ ] `AWS_ACCESS_KEY_ID`
- [ ] `AWS_SECRET_ACCESS_KEY`

## 3. GitHub Staging Environment

راجع `deploy/staging/GITHUB_ENVIRONMENT.md` و`.github/workflows/staging-smoke.yml`.

Secrets المطلوبة بدون كتابة أي قيم:

- [ ] `STAGING_APP_KEY`
- [ ] `STAGING_DB_PASSWORD`
- [ ] `STAGING_MEILISEARCH_KEY`
- [ ] `STAGING_REDIS_PASSWORD`
- [ ] `STAGING_MAIL_PASSWORD`
- [ ] `STAGING_AWS_ACCESS_KEY_ID`
- [ ] `STAGING_AWS_SECRET_ACCESS_KEY`

Variables المطلوبة:

- [ ] `STAGING_APP_URL`
- [ ] `STAGING_DB_HOST`
- [ ] `STAGING_DB_DATABASE`
- [ ] `STAGING_DB_USERNAME`
- [ ] `STAGING_MEILISEARCH_HOST`
- [ ] `STAGING_REDIS_HOST`
- [ ] `STAGING_MAIL_HOST`
- [ ] `STAGING_MAIL_USERNAME`
- [ ] `STAGING_MAIL_FROM_ADDRESS`
- [ ] `STAGING_AWS_DEFAULT_REGION`
- [ ] `STAGING_AWS_BUCKET`
- [ ] `STAGING_NEXT_PUBLIC_API_BASE_URL`
- [ ] `STAGING_NEXT_PUBLIC_ASSET_BASE_URL`
- [ ] `STAGING_NEXT_PUBLIC_STOREFRONT_BASE_URL`
- [ ] `STAGING_STOREFRONT_BASE_URL`
- [ ] `STAGING_BACKEND_HOST`
- [ ] `STAGING_STOREFRONT_HOST`

Optional variables موثقة في `deploy/staging/GITHUB_ENVIRONMENT.md`. لا تضف قيماً حقيقية داخل repository.
لـ smoke ضد URL خارجي حقيقي، عيّن `STAGING_EDGE_URL` صراحة إلى URL الـ edge أو load balancer الذي يستطيع runner الوصول إليه؛ تركه فارغاً يجعل الفحص يستخدم bind المحلي من `EDGE_PORT`، سواء كان `8080` أو `127.0.0.1:8080`.

## 4. Images

- [ ] `BACKEND_IMAGE` يساوي immutable tag أو digest.
- [ ] `STOREFRONT_IMAGE` يساوي immutable tag أو digest.
- [ ] مسموح: `@sha256:<digest>`, `:sha-<commit>`, `:staging-YYYYMMDD-<id>`, أو version tag واضح.
- [ ] ممنوع كمرجع smoke نهائي: `:latest`, `:staging`, `:production`.
- [ ] image publish/scanning معروف من workflow أو سجل release.

## 5. Env Files / Rendering

- [ ] `deploy/staging/images.env` يتم توليده أو إنشاؤه من `images.env.example`.
- [ ] `deploy/staging/backend.env` لا يحتوي placeholders.
- [ ] `deploy/staging/storefront.env` لا يحتوي placeholders.
- [ ] `BACKEND_ENV_FILE` و`STOREFRONT_ENV_FILE` يشيران إلى الملفات الصحيحة.
- [ ] placeholder detection في `staging-smoke.sh validate` يمر.
- [ ] لا توجد أسرار في git.
- [ ] لا تطبع env files كاملة في logs أو artifacts.

## 6. Database And Migrations

- [ ] migrations لا تعمل تلقائياً من container entrypoint.
- [ ] شغل migrations فقط ضمن نافذة تشغيل معلنة وبعد backup مناسب.
- [ ] استخدم seed آمن ومحدود فقط إذا كان مطلوباً للـ smoke.
- [ ] ممنوع seed production data في staging.
- [ ] rollback caution موثق: migrations destructive تحتاج خطة استرجاع وbackup حديث.
- [ ] قبل `mode=all`, تأكد أن runner يستطيع الوصول إلى PostgreSQL staging.

## 7. Health Checks

- [ ] backend live: `/api/system/health/live`.
- [ ] backend ready: `/api/system/health/ready`.
- [ ] storefront home يرد عبر edge/proxy.
- [ ] `php artisan queue:failed` لا يعرض failed jobs.
- [ ] scheduler process موجود، و`php artisan schedule:list` يعرض الأوامر المهمة.
- [ ] readiness يشمل database/cache/queue/storage/redis/search حسب الإعداد.
- [ ] Meilisearch readiness يمر عندما `SCOUT_DRIVER=meilisearch`.
- [ ] object storage readiness يمر عندما `FILESYSTEM_DISK=s3`.

## 8. Smoke Sequence

الأوامر الموجودة في `deploy/staging/staging-smoke.sh`:

```bash
deploy/staging/staging-smoke.sh validate
deploy/staging/staging-smoke.sh pull
deploy/staging/staging-smoke.sh up
deploy/staging/staging-smoke.sh verify
deploy/staging/staging-smoke.sh all
deploy/staging/staging-smoke.sh down
```

التسلسل المقترح:

1. شغل `validate` أولاً.
2. شغل `pull` بعد تثبيت image refs.
3. شغل `up` بعد التأكد من env والbacking services.
4. شغل migrations/seed الآمن من runbook أو نافذة تشغيل مخصصة، وليس من entrypoint، ولا تستخدم production seed data.
5. شغل `verify`.
6. استخدم `all` فقط عندما تكون كل الخدمات متاحة للrunner.
7. استخدم `down` عند الحاجة لإيقاف stack.
8. سجل النتيجة في proof مؤرخ مثل `docs/STAGING_SMOKE_PROOF_2026-05-26_AR.md` أو في سجل عمليات خارجي.

## 9. Acceptance Criteria

البنود التي أثبتها proof الحالي محددة بعلامة `[x]`. البنود المتبقية لا تزال مطلوبة قبل اعتبار staging بوابة beta/production أوسع:

- [ ] backend live يرد 200 عبر staging edge/proxy.
- [ ] backend ready يرد 200 ويغطي PostgreSQL, cache/Redis, queue, storage, وMeilisearch عند تفعيلها.
- [x] storefront home يرد عبر host staging الصحيح.
- [x] `curl -I https://mayfairs.app` يرد `HTTP/2 200`.
- [x] `curl -I https://api.mayfairs.app` يرد `HTTP/2 200`.
- [x] `curl -I https://admin.mayfairs.app` يرد `HTTP/2 200`.
- [x] Filament login يحمّل CSS/JS/Livewire عبر HTTPS ولا توجد mixed content في smoke الحالي.
- [x] super admin بدون 2FA يجبر على setup.
- [x] setup TOTP صحيح يحفظ 2FA ويؤكد session ويدخل dashboard بلا loop.
- [x] logout/login جديد يعرض challenge، TOTP الصحيح يدخل dashboard، وTOTP الخاطئ يعطي validation error.
- [x] reset command لحالة target بدون 2FA مفعل يرجع safe no-op.
- [x] demo store storefront resolve يعمل على `mayfairs.app`.
- [x] demo store يحتوي COD وshipping rates وproducts/inventory كبيانات staging smoke.
- [ ] checkout smoke محدود ينجح على منتج simple، وعلى variant product إذا كان seed staging يحتوي variant آمن للاختبار.
- [x] queue worker service reported healthy.
- [x] scheduler service reported healthy.
- [ ] object storage readiness ينجح ضد bucket staging.
- [ ] Meilisearch readiness ينجح ضد خدمة staging.
- [ ] smoke يستخدم immutable images موثقة.
- [ ] لا توجد أسرار في repo أو logs.
- [x] proof خارجي محدث محفوظ في `docs/STAGING_SMOKE_PROOF_2026-05-26_AR.md`، بدون ادعاء production readiness.
