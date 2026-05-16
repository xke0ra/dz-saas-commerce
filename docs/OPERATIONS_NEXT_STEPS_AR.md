# خطة التشغيل التالية

هذه الخطة لا تستبدل runbooks الموجودة. استخدمها كمسار تنفيذ مختصر يربط `PRODUCTION_READINESS.md`, `BACKUP_RESTORE_RUNBOOK.md`, `MONITORING_ALERTING_RUNBOOK.md`, `QUEUE_SCHEDULER_RUNBOOK.md`, و`REVERSE_PROXY_RUNBOOK.md`.

## Phase A: Real Staging

الهدف: إثبات staging حقيقي يشبه production بدون إطلاق عام.

- أنشئ GitHub environment باسم `staging` مع variables/secrets الحقيقية المطلوبة.
- استخدم PostgreSQL, Redis, Meilisearch, object storage, وSMTP منفصلة عن local/dev.
- شغل backend, queue worker, scheduler, storefront, وreverse proxy بنفس topology الموثق.
- فعّل TLS وhost routing للدومينات التجريبية.
- شغل staging smoke ضد URL حقيقي، وليس فقط compose محلي.
- ثبّت image tag أو digest المستخدم في تقرير smoke.
- وثق rollback: آخر image صالح، أمر الإرجاع، وكيفية إيقاف traffic مؤقتاً.
- لا تستخدم أسرار production في staging.

معيار القبول:

- `system/health/live` و`system/health/ready` يمران عبر URL staging.
- storefront يفتح ضد backend staging.
- checkout smoke محدود ينجح ببيانات seed آمنة.
- queue/scheduler يعملان ويمكن رصد failed jobs.

## Phase B: Backup + Restore Drill

الهدف: إثبات أن النسخ الاحتياطي قابل للاستعادة، لا أنه موجود فقط.

- جدولة backup لقاعدة PostgreSQL.
- جدولة backup للـ object storage أو توثيق replication/snapshot strategy.
- استرجع backup إلى database منفصلة لا تلمس staging الأصلي.
- شغل migrations/status checks المناسبة بعد restore.
- شغل health checks ضد البيئة المستعادة.
- شغل smoke checkout محدود بعد restore إن كانت البيانات تسمح.
- وثق RPO وRTO بالأرقام بعد أول drill.
- وثق owner وتكرار drill الشهري أو قبل تغييرات كبيرة.

معيار القبول:

- يوجد restore log بتاريخ ووقت.
- health/readiness يمران على البيئة المستعادة.
- RPO/RTO مسجلان في runbook.
- أي فشل في restore ينتج issue أو task واضح.

## Phase C: Monitoring/Alerting

الهدف: معرفة الفشل مبكراً قبل أن يتحول إلى خسارة أو incident.

- Uptime للـ backend live وready endpoints.
- Uptime للـ storefront.
- failed jobs count وتنبيه عند الزيادة.
- queue latency وتنبيه عند تجاوز threshold.
- scheduler last run وتنبيه عند الانقطاع.
- 5xx rate للbackend والedge.
- checkout failure rate.
- storage readiness وsearch readiness.
- TLS expiry.
- backup age.
- error tracking مع PII redaction.
- dashboard مختصر يعرض الحالة التشغيلية بدون كشف أسرار.

معيار القبول:

- alerts تصل إلى قناة يراقبها الإنسان.
- كل alert له runbook مختصر.
- logs لا تحتوي phone/IP raw إلا إذا كانت masked أو hashed.
- يتم اختبار alert واحد على الأقل بشكل مقصود قبل اعتبار المرحلة مغلقة.
