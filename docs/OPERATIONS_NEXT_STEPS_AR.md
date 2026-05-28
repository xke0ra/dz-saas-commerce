# خطة التشغيل التالية

هذه الخطة لا تستبدل runbooks الموجودة. استخدمها كمسار تنفيذ مختصر يربط `PRODUCTION_READINESS.md`, `BACKUP_RESTORE_RUNBOOK.md`, `MONITORING_ALERTING_RUNBOOK.md`, `QUEUE_SCHEDULER_RUNBOOK.md`, و`REVERSE_PROXY_RUNBOOK.md`.

## الحالة المثبتة حالياً

تم إنجاز جزء أساسي من real staging على mayfairs.app بعد نشر commit `045c264`:

- DigitalOcean droplet `mayfair-vps` يعمل كـ staging host.
- Caddy يملك HTTPS public على 80/443 أمام nginx edge داخلي على `127.0.0.1:8080`.
- Cloudflare ما زال DNS only.
- `mayfairs.app`, `api.mayfairs.app`, و`admin.mayfairs.app` ترد HTTP/2 200.
- Filament وLivewire assets تولد HTTPS بدون mixed content مرصود.
- mandatory 2FA setup/challenge تم اختباره يدوياً بدون redirect loop.
- demo tenant/store يعمل على `https://mayfairs.app` مع COD وshipping rates وproducts/inventory.
- proof محفوظ في `docs/STAGING_SMOKE_PROOF_2026-05-26_AR.md`.

هذه الحالة لا تعني production readiness. ما زالت backup/restore وmonitoring والrollback وCloudflare Proxied غير مثبتة.

## Phase A: Backup + Restore Drill

الهدف: إثبات أن النسخ الاحتياطي قابل للاستعادة، لا أنه موجود فقط.

**المُنجز من PR #43:**

- `deploy/backup/bin/staging-restore-drill.sh.example` يفرض restore آمن إلى database مؤقت فقط لا يلمس live staging.
- Database مؤقت يجب أن يبدأ بـ `dz_saas_restore_drill_`.
- `deploy/backup/backup.env.example` يوثّق جميع متغيرات الـ restore drill بما فيها:
  - `STAGING_ADMIN_DATABASE_URL` (اتصال إداري فقط للـ CREATE/DROP)
  - `RESTORE_DRILL_DATABASE` (اسم database مؤقت)
  - `RESTORE_DRILL_DATABASE_URL` (URL اتصال الـ drill)
  - `CLEANUP_RESTORE_DRILL_DATABASE` و`CONFIRM_DROP_RESTORE_DRILL_DATABASE` (تنظيف يتطلب تأكيد مزدوج)
- `docs/BACKUP_RESTORE_RUNBOOK.md` لا توثّق بعد الآن direct restore إلى live staging وتركز على الـ drill الآمن.

**ما زال معلّق (لم يتم إنجازه):**

- جدولة backup مؤتمتة لقاعدة PostgreSQL.
- جدولة backup للـ object storage أو توثيق replication/snapshot strategy.
- تنفيذ فعلي لـ restore drill وتسجيل الدليل.
- وثق RPO وRTO بالأرقام بعد أول drill فعلي.
- سجل الدليل في `BACKUP_RESTORE_DRILL_EVIDENCE_TEMPLATE.md` أو نسخة منه داخل issue/operations log.
- وثق owner وتكرار drill الشهري أو قبل تغييرات كبيرة.

معيار القبول:

- يوجد restore log بتاريخ ووقت.
- health/readiness يمران على البيئة المستعادة.
- RPO/RTO مسجلان في runbook.
- أي فشل في restore ينتج issue أو task واضح.

## Phase B: Monitoring/Alerting

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
- استخدم `MONITORING_BASELINE_MATRIX_AR.md` لتحديد source/status/threshold/action لكل alert قبل اختيار provider.

معيار القبول:

- alerts تصل إلى قناة يراقبها الإنسان.
- كل alert له runbook مختصر.
- logs لا تحتوي phone/IP raw إلا إذا كانت masked أو hashed.
- يتم اختبار alert واحد على الأقل بشكل مقصود قبل اعتبار المرحلة مغلقة.

## Phase C: Release And Rollback

الهدف: جعل النشر قابلاً للتكرار والاسترجاع بدون قرارات مرتجلة.

- وثق image tag أو commit SHA لكل نشر staging.
- سجل آخر known-good release.
- نفذ rollback smoke على staging بدون production data.
- اربط rollback بخطة backup قبل migrations.
- لا تستخدم `migrate:rollback` تلقائياً للتغييرات destructive.

معيار القبول:

- يوجد rollback reference موثق.
- يوجد أمر استرجاع واضح للimages أو commit.
- smoke بعد rollback موثق.
- لا يوجد فقدان بيانات غير مقصود أثناء التمرين.

## Phase D: Cloudflare Proxied Smoke

الهدف: اتخاذ قرار Proxied بناءً على smoke، لا افتراض.

- فعّل Proxied مؤقتاً في نافذة اختبار.
- تحقق من HTTPS وHSTS و`X-Forwarded-*`.
- تحقق من `SESSION_DOMAIN`, secure cookies، و2FA setup/challenge.
- تحقق من Filament/Livewire assets و`data-update-uri`.
- تحقق من storefront demo resolution.
- ارجع إلى DNS only فوراً إذا ظهرت session أو mixed content أو redirect issues.

معيار القبول:

- proof مستقل يثبت headers/assets/session/2FA خلف Cloudflare.
- قرار واضح: إبقاء DNS only أو اعتماد Proxied مع إعدادات موثقة.

## Phase E: Custom Domains/TLS Design

الهدف: تصميم مسار domain/TLS للتجار قبل تعميمه.

- حدد هل Caddy يدير شهادات custom domains أم سيستخدم provider/CDN.
- وثق domain verification flow وDNS requirements.
- حدد حدود tenant isolation في host routing.
- أضف smoke لدومين تاجر تجريبي قبل أي customer-facing rollout.
