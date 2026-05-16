# Staging Readiness Checklist

Checklist تنفيذية قصيرة قبل تشغيل real staging smoke. لا تستبدل هذه الوثيقة runbooks الموجودة؛ استخدمها كبوابة قبل `deploy/staging/staging-smoke.sh`.

مراجع أساسية:

- `deploy/staging/README.md`
- `deploy/staging/GITHUB_ENVIRONMENT.md`
- `docs/REVERSE_PROXY_RUNBOOK.md`
- `docs/QUEUE_SCHEDULER_RUNBOOK.md`
- `docs/PRODUCTION_READINESS.md`

## 1. Infrastructure

- [ ] staging domain أو subdomain محدد للـ storefront.
- [ ] backend host محدد مثل API/admin host غير إنتاجي.
- [ ] TLS/reverse proxy جاهز حسب `docs/REVERSE_PROXY_RUNBOOK.md`.
- [ ] backend runtime يعمل كـ PHP-FPM service.
- [ ] storefront runtime يعمل كـ Next.js service.
- [ ] `STAGING_BACKEND_HOST` و`STAGING_STOREFRONT_HOST` يطابقان host routing في reverse proxy المنشور.
- [ ] queue worker service منفصل عن web.
- [ ] scheduler service منفصل، وبنسخة واحدة فقط للبيئة.
- [ ] PostgreSQL staging منفصل عن production.
- [ ] Redis staging منفصل أو namespace واضح.
- [ ] Meilisearch staging منفصل أو index prefix واضح.
- [ ] object storage staging bucket منفصل.
- [ ] SMTP/mail sandbox لا يرسل لعملاء حقيقيين.

## 2. GitHub Staging Environment

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
لـ smoke ضد URL خارجي حقيقي، عيّن `STAGING_EDGE_URL` صراحة إلى URL الـ edge أو load balancer الذي يستطيع runner الوصول إليه؛ تركه فارغاً يجعل الفحص يستخدم `http://127.0.0.1:${EDGE_PORT}` داخل runner.

## 3. Images

- [ ] `BACKEND_IMAGE` يساوي immutable tag أو digest.
- [ ] `STOREFRONT_IMAGE` يساوي immutable tag أو digest.
- [ ] مسموح: `@sha256:<digest>`, `:sha-<commit>`, `:staging-YYYYMMDD-<id>`, أو version tag واضح.
- [ ] ممنوع كمرجع smoke نهائي: `:latest`, `:staging`, `:production`.
- [ ] image publish/scanning معروف من workflow أو سجل release.

## 4. Env Files / Rendering

- [ ] `deploy/staging/images.env` يتم توليده أو إنشاؤه من `images.env.example`.
- [ ] `deploy/staging/backend.env` لا يحتوي placeholders.
- [ ] `deploy/staging/storefront.env` لا يحتوي placeholders.
- [ ] `BACKEND_ENV_FILE` و`STOREFRONT_ENV_FILE` يشيران إلى الملفات الصحيحة.
- [ ] placeholder detection في `staging-smoke.sh validate` يمر.
- [ ] لا توجد أسرار في git.
- [ ] لا تطبع env files كاملة في logs أو artifacts.

## 5. Database And Migrations

- [ ] migrations لا تعمل تلقائياً من container entrypoint.
- [ ] شغل migrations فقط ضمن نافذة تشغيل معلنة وبعد backup مناسب.
- [ ] استخدم seed آمن ومحدود فقط إذا كان مطلوباً للـ smoke.
- [ ] ممنوع seed production data في staging.
- [ ] rollback caution موثق: migrations destructive تحتاج خطة استرجاع وbackup حديث.
- [ ] قبل `mode=all`, تأكد أن runner يستطيع الوصول إلى PostgreSQL staging.

## 6. Health Checks

- [ ] backend live: `/api/system/health/live`.
- [ ] backend ready: `/api/system/health/ready`.
- [ ] storefront home يرد عبر edge/proxy.
- [ ] `php artisan queue:failed` لا يعرض failed jobs.
- [ ] scheduler process موجود، و`php artisan schedule:list` يعرض الأوامر المهمة.
- [ ] readiness يشمل database/cache/queue/storage/redis/search حسب الإعداد.
- [ ] Meilisearch readiness يمر عندما `SCOUT_DRIVER=meilisearch`.
- [ ] object storage readiness يمر عندما `FILESYSTEM_DISK=s3`.

## 7. Smoke Sequence

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

## 8. Acceptance Criteria

نقول إن real staging جاهز فقط عندما:

- [ ] backend live يرد 200 عبر staging edge/proxy.
- [ ] backend ready يرد 200 ويغطي PostgreSQL, cache/Redis, queue, storage, وMeilisearch عند تفعيلها.
- [ ] storefront home يرد عبر host staging الصحيح.
- [ ] queue worker يعمل ولا توجد failed jobs.
- [ ] scheduler يعمل كعملية مستقلة وبنسخة واحدة.
- [ ] object storage readiness ينجح ضد bucket staging.
- [ ] Meilisearch readiness ينجح ضد خدمة staging.
- [ ] smoke يستخدم immutable images موثقة.
- [ ] لا توجد أسرار في repo أو logs.
- [ ] نتيجة smoke محفوظة كرابط workflow run أو سجل تشغيل، بدون ادعاء production readiness.
