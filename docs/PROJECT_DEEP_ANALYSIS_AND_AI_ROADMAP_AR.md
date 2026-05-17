# التحليل العميق وخارطة الطريق الاستراتيجية لمنصة dz-saas-commerce

آخر تحديث: 2026-05-12

نوع الوثيقة: مرجع استراتيجي أعلى + تحليل معماري + خارطة طريق تنفيذية طويلة المدى لعمل الإنسان وCodex على المشروع.

هذه الوثيقة هي المرجع الأعلى لتطوير `dz-saas-commerce`. أي وثيقة roadmap تفصيلية أو sprint plan أو prompt موجه إلى Codex يجب ألا يتعارض معها. عند وجود تعارض بين هذه الوثيقة وملف آخر، يتم تحديث الملف الأدنى أو تسجيل الفجوة صراحة قبل تنفيذ الميزة.

مصادر هذه النسخة:

- قراءة بنية المستودع وملفات `backend/`, `storefront/`, `docs/`, `docker-compose.yml`, و`.github/workflows/quality.yml`.
- نتائج فحص عملي محلية بتاريخ 2026-05-12، مع الاحتفاظ بسياق التحقق السابق بتاريخ 2026-05-09 و2026-05-08.
- الوثائق الموجودة فعلياً داخل `docs/`.
- مبدأ المنتج المعلن: بناء منصة SaaS تجارة إلكترونية ضخمة شبيهة بـ Shopify، لكنها مخصصة للسوق الجزائري، بدون استعجال إطلاق غير ناضج.

قاعدة قراءة مهمة: هذه الوثيقة لا تعني أن المشروع جاهز للإنتاج. هي تميز بين ما هو موجود فعلاً، وما هو جيد كأساس، وما يحتاج إثباتاً تشغيلياً قبل beta أو production.

---

## 1. الخلاصة التنفيذية

`dz-saas-commerce` مشروع monorepo متقدم لبناء منصة SaaS متعددة المستأجرين للتجارة الإلكترونية في الجزائر. النواة التجارية موجودة بدرجة جيدة: tenancy، كتالوج، مخزون، طلبات، دفع COD، شحن، اشتراكات، كوبونات، دومينات، إعدادات متجر، ثيمات، تدقيق، دعم، ولوحات Filament متعددة.

الخلفية `backend/` هي أقوى جزء في المشروع حالياً. هي مبنية بـ Laravel وFilament، وتستخدم Actions وسياسات وصلاحيات وقيود قاعدة بيانات واختبارات جيدة. الفحص العملي أثبت أن backend test suite يمر بنجاح: `154 passed (629 assertions)`.

الواجهة `storefront/` جيدة كبداية فعلية: صفحات متجر، منتجات، تصنيفات، بحث، سلة، checkout، تتبع طلب، SEO، robots، sitemap، وJSON-LD. تم توحيد الاعتماد الفعلي على `storefront/pnpm-lock.yaml`، وتم إغلاق بوابة أمان الواجهة في 2026-05-12 بتحديث Next إلى `15.5.18`. التحقق المحلي الحالي يثبت `pnpm audit --audit-level moderate`, `pnpm build`, `pnpm typecheck`, و`pnpm test:e2e` بنجاح.

الجاهزية للإنتاج غير مكتملة. توجد Dockerfiles، health/readiness، runbooks، CI baseline، backup/proxy/monitoring docs، وأساس أمني. تم في 2026-05-12 إثبات Quality Gates داخل GitHub Actions على PR #1 / run `25743248405` وبعد الدمج على `main` / run `25744282999`، وتفعيلها كـ required checks على `main`. وتم أيضاً إثبات GHCR staging image publish بعد Trivy scan عبر run `25751543062`. بعد ذلك أثبت smoke مؤقت محلي كامل أن topology الخاص بالـ backend/storefront/queue/scheduler/edge يعمل مع PostgreSQL وRedis وMeilisearch وS3/MinIO وSMTP/Mailpit، وكشف نقص اعتماد S3 في backend ثم أُغلق بإضافة `league/flysystem-aws-s3-v3`. لا يوجد بعد دليل كاف على staging deployment خارجي حقيقي، TLS/custom domains، restore drill منفذ، monitoring/alerting فعلي، error tracking، أو security hardening كامل.

الهدف بعيد المدى ليس إطلاق متجر واحد، بل بناء منصة SaaS تجارية واسعة شبيهة بـ Shopify ومناسبة للجزائر. لذلك لا ينصح بالإطلاق قبل إغلاق التشغيل، الأمان، العزل بين المستأجرين، موثوقية CI، النسخ الاحتياطي، والمراقبة.

---

## 2. تعريف المنتج النهائي

المنتج النهائي ليس storefront فقط، وليس لوحة Filament فقط. هو `SaaS commerce platform` متعددة الأطراف.

### 2.1 مالك المنصة Platform Owner

مالك المنصة يحتاج إلى:

- إدارة tenants والمتاجر والخطط والاشتراكات والفواتير.
- مراقبة الإيرادات، MRR/ARR، التحصيل، الاشتراكات المتأخرة، والتحويل من trial إلى paid.
- إدارة الدعم، المخاطر، فشل jobs، صحة النظام، وحالات التعطيل.
- التحكم في الخطط، الحدود، add-ons، الدومينات، الثيمات، وصلاحيات الفرق.
- رؤية تشغيلية دقيقة قبل أن تتحول المشكلة إلى خسارة مالية أو تسريب بيانات.

### 2.2 التاجر Merchant

التاجر يحتاج إلى:

- إنشاء متجر احترافي بسرعة، بدون تدخل مطور.
- ضبط البيانات القانونية والتجارية، الشحن، الدفع، الهوية البصرية، والدومين.
- إدارة المنتجات، الصور، variants لاحقاً، المخزون، الطلبات، العملاء، الشحنات، المرتجعات، والكوبونات.
- dashboard واضح: مبيعات، طلبات معلقة، مخزون منخفض، توصيلات فاشلة، فواتير، حدود الخطة.
- تجربة يومية سريعة ومناسبة للعمل التجاري الجزائري، خصوصاً COD والتوصيل حسب الولاية والبلدية.

### 2.3 الزبون Customer

الزبون يحتاج إلى:

- storefront سريع وواضح على الهاتف.
- لغة عربية/RTL ممتازة، مع دعم فرنسي لاحقاً.
- سعر واضح، شحن واضح، checkout مختصر، وتتبع طلب.
- ثقة: معلومات قانونية، اتصال، سياسة شحن/إرجاع، صور جيدة، وأداء جيد.
- عدم الاعتماد على أرقام مزيفة من الواجهة؛ backend يجب أن يؤكد السعر والشحن والمخزون.

### 2.4 فريق الدعم Support

فريق الدعم يحتاج إلى:

- رؤية متعددة المستأجرين بدون خرق العزل.
- إدارة tickets، تصعيد المشاكل، ربطها بالمتاجر والطلبات والفواتير.
- صلاحيات محدودة: الدعم لا يجب أن يصبح super admin مقنعاً.
- audit trail للعمليات الحساسة.

### 2.5 النظام التشغيلي DevOps/Operations

التشغيل يحتاج إلى:

- CI مطلوب قبل الدمج.
- staging يشبه production.
- نسخ احتياطي مجدول ومثبت عبر restore drill.
- مراقبة readiness، failed jobs، queue/scheduler، 5xx، TLS/custom domains، backups، وbilling jobs.
- error tracking مع PII redaction.
- إجراءات incident response وrollback.

---

## 3. المبادئ المعمارية غير القابلة للتفاوض

1. Laravel هو مصدر الحقيقة للأسعار، الشحن، الخصومات، المخزون، الاشتراكات، حالات الطلبات، الصلاحيات، والفواتير.
2. Next.js storefront واجهة عرض وتجربة مستخدم. لا يحسب totals موثوقة، ولا يقرر خصماً أو شحناً أو مخزوناً أو حالة دفع.
3. Tenant isolation حد أمني، وليس تفصيلاً تنظيمياً. أي تسريب بين tenants يعتبر incident.
4. منطق الأعمال الحساس يوضع في Actions/Services/Domain classes، وليس داخل Controllers أو Filament Resources عندما يمكن تفادي ذلك.
5. المال يخزن ويحسب بوحدات صغيرة `minor units`، مثل `price_minor` و`total_minor`.
6. عمليات checkout، الدفع، المخزون، الاشتراكات، وتغيير الحالات يجب أن تستخدم transactions وأقفال مناسبة عند الحاجة.
7. checkout وinventory يجب أن يكونا idempotent أو محميين بوضوح ضد التكرار والطلبات المتزامنة.
8. أي استخدام لـ `withoutGlobalScope('current_tenant')` يحتاج مبرراً واختباراً، أو فلترة `tenant_id` صريحة.
9. Public API يجب ألا يكشف معرفات داخلية غير ضرورية مثل `tenant_id` إلا لسبب مثبت.
10. Policies وdatabase constraints جزء من التصميم، وليسا طبقة لاحقة.
11. كل tenant-owned model جديد يحتاج tenant scoping أو استثناء موثق بوضوح.
12. كل ميزة حساسة يجب أن تترك audit log عند تغيير مالي أو تشغيلي مهم.
13. الوثائق والاختبارات جزء من Definition of Done.
14. لا microservices الآن. modular monolith هو الخيار الصحيح حتى يثبت ضغط تشغيلي حقيقي يستدعي الفصل.
15. لا marketplace كامل الآن. المشروع يجب أن ينضج كمنصة SaaS أولاً.

---

## 4. تحليل البنية الحالية

### 4.1 monorepo

الشكل الحالي:

- `backend/`: Laravel + Filament + API + domain logic.
- `storefront/`: Next.js customer storefront.
- `docs/`: وثائق معمارية وتشغيلية وADRs.
- `deploy/`: أمثلة تشغيل مثل backup وreverse proxy وsupervision.
- `docker-compose.yml`: خدمات محلية: PostgreSQL، Redis، Meilisearch، MinIO، Mailpit.
- `.github/workflows/quality.yml`: CI baseline للbackend والstorefront وDocker checks وe2e إلزامي.

هذا الشكل مناسب الآن لأنه يحافظ على توافق backend وstorefront والوثائق في مستودع واحد، ويجعل Codex يعمل على سياق واحد.

### 4.2 backend

الـ backend مبني حول Laravel/Filament. الدومينات الأساسية موجودة فعلاً:

- tenancy وidentity/RBAC.
- stores وdomains وstore/theme settings.
- catalog وcategories وproducts وimages.
- inventory.
- checkout وorders وpayments.
- shipping وreturns.
- billing/subscriptions/invoices.
- coupons.
- audit logs.
- support tickets.
- health/readiness.

القوة الأساسية أن منطق الأعمال موجود غالباً في `app/Actions`، وهذا يقلل تضخم controllers ويجعل الاختبار أسهل. Filament يستخدم الموارد والصفحات لإدارة العمليات، لكن لا يجب أن يصبح مكان القرارات المالية أو التشغيلية.

### 4.3 storefront

الـ storefront مبني بـ Next.js. الموجود حالياً:

- home.
- products.
- product details.
- categories.
- search.
- cart.
- quick checkout.
- track order.
- legal pages.
- sitemap.
- robots.
- metadata وOpenGraph وJSON-LD.
- API proxy routes لبعض عمليات checkout/geography/track.

الفجوة الرئيسية: الواجهة صالحة كبداية، لكنها ليست بعد storefront تجاري ناضج لمتاجر كبيرة. أهم فجواتها هي catalog pagination UI، sitemap index لاحقاً للمتاجر الضخمة، caching/revalidation، states، accessibility، وصقل checkout mobile.

### 4.4 docs

مجلد `docs/` غني ومفيد. توجد وثائق معمارية وتشغيلية مهمة:

- `ARCHITECTURE.md`
- `DEVELOPMENT_WORKFLOW.md`
- `LOCAL_DEVELOPMENT.md`
- `TESTING_STRATEGY.md`
- `PRODUCTION_READINESS.md`
- `SECURITY_BASELINE.md`
- `TENANCY_RULES.md`
- `BACKUP_RESTORE_RUNBOOK.md`
- `MONITORING_ALERTING_RUNBOOK.md`
- `QUEUE_SCHEDULER_RUNBOOK.md`
- `REVERSE_PROXY_RUNBOOK.md`
- `ALGERIA_GEOGRAPHY.md`
- `STOREFRONT_CART.md`
- `STOREFRONT_SEO.md`
- `STOREFRONT_THEME.md`
- `docs/adr/*.md`

هذه الوثيقة لا تستبدل كل تلك الملفات. هي تجمع الرؤية العليا وترتبط بها. التفاصيل التشغيلية تبقى في runbooks.

### 4.5 Docker والتشغيل المحلي

يوجد `docker-compose.yml` للخدمات المحلية. هذا جيد للتطوير، لكنه ليس دليلاً على production readiness. يوجد أيضاً Dockerfiles للbackend والstorefront، وأمثلة deployment. المطلوب لاحقاً هو إثبات build/push وتشغيل staging فعلي.

### 4.6 CI

يوجد workflow في `.github/workflows/quality.yml` يغطي:

- backend composer/install/audit/Pint/migrate/health/tests/routes.
- storefront pnpm install/audit/typecheck/build.
- docker build plan checks وno-push image build smoke.
- e2e إلزامي مع رفع artifacts عند الفشل.

الفجوة: لا يوجد دليل حالياً أنه مفعل كـ required merge gate في GitHub. قبل تكبير المشروع عبر Codex، يجب أن يصبح CI حاجزاً إلزامياً.

### 4.7 Filament panels

المشروع يستخدم ثلاث لوحات:

- `admin`: مالك المنصة والإدارة العليا.
- `vendor`: التجار وإدارة المتجر.
- `support`: فريق الدعم.

هذا التقسيم صحيح لمنتج SaaS. يجب الحفاظ عليه، وعدم خلط صلاحيات الدعم مع صلاحيات super admin أو التاجر.

### 4.8 database/migrations

قاعدة البيانات متقدمة نسبياً:

- migrations كثيرة تغطي معظم الدومينات.
- `tenant_id` في الجداول التجارية.
- قيود مركبة cross-tenant في علاقات حساسة.
- ULIDs.
- تخزين المال بوحدات صغيرة.
- جدول checkout idempotency.

المرحلة القادمة يجب أن تضيف stock movement ledger وproduct variants بحذر، لأنهما سيؤثران على checkout والمخزون والطلبات.

### 4.9 tenancy

الموجود:

- `CurrentTenant`
- `TenantResolver`
- `ResolveTenantFromRequest`
- `BelongsToTenant`
- policies
- database constraints
- قواعد موثقة في `TENANCY_RULES.md`

ملاحظة تحقق مهمة: أغلب tenant-owned models تستخدم `BelongsToTenant`. لكن `Store` يبقى exception مقصوداً لأنه مطلوب في حلّ الدومين والـ platform flows. تم في 2026-05-09 جعل `Store::forTenant(null)` fail-closed بدلاً من إرجاع كل المتاجر، وإزالة `tenant_id` من `Storefront/StoreResource` العام حتى لا يكشف الـ public API معرف tenant داخلياً بلا حاجة.

### 4.10 checkout

الموجود جيد:

- Laravel يحسب الأسعار والشحن والخصومات والمجموع.
- validation للبيانات الجزائرية مثل الهاتف والولاية والبلدية.
- inventory reservation داخل transaction.
- checkout idempotency عبر `Idempotency-Key`.
- duplicate-window عند غياب المفتاح.
- abuse guard حسب IP/phone/store.
- Next.js يرسل item IDs/quantities ولا يرسل totals موثوقة.

الفجوة: duplicate-window بدون key هو حماية best-effort، وليس ضماناً كاملاً تحت concurrency. في السيناريوهات الحساسة يجب فرض idempotency key من storefront أو إضافة dedupe أقوى.

### 4.11 billing

الموجود:

- plans.
- features/limits.
- subscriptions.
- invoices.
- manual subscription payments.
- lifecycle processing.
- grace/suspension.

الناقص التجاري:

- invoice PDF.
- dunning كامل.
- payment proof upload/review.
- revenue dashboard.
- MRR/ARR.
- reconciliation وledger أوضح.

### 4.12 shipping

الموجود:

- wilayas/communes.
- shipping companies.
- rates.
- shipments.
- shipment status transitions.
- failed delivery reasons.
- returns.

الناقص:

- shipping zones/templates.
- home/desk delivery rules أوسع عند الحاجة.
- COD reconciliation.
- shipment batches.
- provider abstraction قبل أي integration.

### 4.13 theme/storefront presentation

الموجود:

- theme settings.
- hero.
- trust badges.
- contact/legal strip.
- Arabic/French localization foundation.

الناقص:

- theme sections قابلة للتكوين أكثر.
- media/image strategy.
- accessibility audit.
- storefront readiness gates قبل publish.

### 4.14 domains

الموجود:

- domain records.
- verification tokens.
- active/failed/disabled states.
- host-based resolution.
- reverse proxy runbook.

الناقص:

- staging validation حقيقي للدومينات.
- TLS/custom domain routing proof.
- monitoring لانتهاء الشهادات وفشل الدومينات.

### 4.15 support

الموجود:

- support panel.
- support tickets.
- platform support role.

الناقص:

- messages.
- attachments.
- SLA.
- macros.
- customer/vendor conversation flow.

### 4.16 audit logs

الموجود جيد كأساس:

- audit log domain.
- policy تمنع mutation الحساسة.
- events لعمليات متعددة.

القاعدة المستقبلية: أي تغيير في billing, checkout, inventory, shipping, support assignment, staff permissions يجب أن يسجل audit log.

---

## 5. نتائج التحقق العملي بتاريخ 2026-05-12

تم تشغيل الأوامر التالية بنجاح محلياً في `backend/`:

| الأمر | النتيجة |
|---|---|
| `composer validate --strict` | passed |
| `composer audit --no-interaction` | no advisories |
| `php vendor/bin/pint --test` | passed |
| `php artisan test` | `154 passed (629 assertions)` |
| `php artisan system:health --scope=ready --format=json` | ok، ويتضمن اتصال PostgreSQL وMeilisearch في البيئة المحلية |
| `scripts/security/secret-hygiene.sh` | passed |
| `scripts/release/clean-export-check.sh` | passed، archive size: `1.8M` |

تم تشغيل الأوامر التالية في `storefront/`:

| الأمر | النتيجة |
|---|---|
| `pnpm audit --audit-level moderate` | passed، no known vulnerabilities |
| `pnpm build` | passed على `Next.js 15.5.18` |
| `pnpm typecheck` | passed عند تشغيله متسلسلاً بعد البناء |
| `pnpm test:e2e` | `6 passed` |

ملاحظة تحقق مهمة: تشغيل `pnpm typecheck` بالتوازي مع `pnpm build` فشل مرة بسبب إعادة توليد `.next/types` أثناء فحص TypeScript. هذا يؤكد قاعدة موجودة في `TESTING_STRATEGY.md`: لا تشغل typecheck وbuild بالتوازي على نفس checkout. التشغيل المتسلسل بعد build نجح.

حدود هذا التحقق: لم يتم في هذه الجولة تشغيل `migrate:status` أو `checkout-idempotency:prune --dry-run`. تم تشغيل مسار Docker الكامل للواجهة وDockerfile checks وimage build smoke للـ backend/storefront في 2026-05-12، وتم إثبات GitHub required checks عبر PR #1 / run `25743248405` وبعد الدمج على `main` / run `25744282999`. تم إثبات GHCR image publishing بعد Trivy scan عبر `container-images` run `25751543062`. تم أيضاً تشغيل smoke مؤقت كامل محلياً على صورة backend مبنية من الكود الحالي، مع migrations و`StorefrontDemoSeeder` و`queue:failed` وreadiness تشمل S3 storage. لم يتم بعد إثبات staging deployment خارجي حقيقي أو restore drill.

### تحقق الواجهة

تم إغلاق أولوية Phase 0 رقم 1 الخاصة بتوحيد بيئة `storefront` عبر Docker. المسار الموثق الآن:

```bash
./storefront/scripts/verify-docker.sh all
```

نتيجة التحقق المحدثة بتاريخ 2026-05-12:

- Docker image للـ install/typecheck/build: `node:24-bookworm`.
- Docker image للـ e2e: `mcr.microsoft.com/playwright:v1.59.1-noble`.
- Node داخل Docker: `v24.15.0`.
- pnpm داخل Docker: `11.1.2`، موحد مع `storefront/package.json`.
- `pnpm install --frozen-lockfile`: نجح.
- `pnpm typecheck`: نجح.
- `pnpm build`: نجح على `Next.js 15.5.18`.
- `pnpm test:e2e`: نجح، `6 passed (26.7s)`.
- لا يتم خلط Windows `node_modules` مع WSL `node_modules`.
- pnpm store مضبوط داخل الحاوية تحت `/tmp/pnpm-store` حتى لا يولد `.pnpm-store` داخل المشروع.

تحديث 2026-05-12: لا توجد الآن lockfiles غير مناسبة في root أو `storefront/`; الملفات المقفلة الفعلية هي `backend/composer.lock`, `backend/package-lock.json`, و`storefront/pnpm-lock.yaml`. هذا يغلق تحذير Next السابق عن اختيار root بسبب `package-lock.json` في جذر المستودع. تم تحديث `next` إلى `15.5.18` وإعادة تمرير `pnpm audit --audit-level moderate` بنجاح.

ملاحظات التنفيذ من تثبيت مسار Docker:

- تم تعديل script `typecheck` إلى `tsc --noEmit --incremental false` حتى لا يفشل بسبب cache قديم داخل `.next/tsconfig.tsbuildinfo`.
- تم تحديث Playwright لاحقاً ليستخدم مسار production build: `pnpm build` ثم `next start` عبر `pnpm exec`، مع تثبيت `STOREFRONT_BASE_URL` على `http://127.0.0.1:3100` أثناء e2e.
- تم تشغيل e2e داخل official Playwright image لتفادي مشاكل مكتبات Chromium الناقصة في WSL.

---

## 6. نقاط القوة الحالية

### 6.1 tenancy قوي كأساس

المشروع لا يتعامل مع tenancy كفلتر سطحي فقط. توجد طبقات متعددة: resolver، current tenant، middleware، global scope، policies، وdatabase constraints. هذا صحيح لمنصة SaaS.

### 6.2 policies وصلاحيات واضحة

وجود policies وتسجيلها في `AppServiceProvider`، مع تقسيم platform roles وtenant roles، يجعل المشروع قابلاً للنمو بدون خلط صلاحيات خطير.

### 6.3 database constraints مهمة

وجود قيود مركبة لعلاقات tenant-owned يقلل احتمال cross-tenant references حتى لو أخطأ كود application. هذه نقطة قوية جداً في مشروع SaaS.

### 6.4 checkout server-side

الـ checkout لا يثق في totals من الواجهة. Laravel يتحقق من المنتجات والمخزون والشحن والكوبونات ويحسب المجموع. هذا يحمي المال والمخزون.

### 6.5 idempotency

دعم `Idempotency-Key` في checkout، مع conflict عند تغير payload، قرار صحيح مبكراً. كثير من المشاريع تضيفه بعد مشاكل production.

### 6.6 Actions

وضع منطق مثل إنشاء الطلب، تغيير حالة الطلب، تسوية المخزون، تسجيل الدفع، ومعالجة billing lifecycle في Actions يجعل المشروع أكثر قابلية للاختبار والتوسع.

### 6.7 Filament panels

تقسيم admin/vendor/support يعطي بنية مناسبة للمنصة. لا يجب اختزال كل شيء في لوحة واحدة.

### 6.8 الاختبارات

backend test suite قوي نسبياً لحالة pre-production: `154 passed (629 assertions)`. هذا مهم جداً قبل استخدام Codex بكثافة.

### 6.9 health checks

وجود live/ready endpoints و`system:health` command يعطي أساساً للتشغيل والمراقبة.

### 6.10 runbooks والوثائق

الوثائق الحالية صريحة في الفجوات. وجود `PRODUCTION_READINESS.md`, `SECURITY_BASELINE.md`, `TESTING_STRATEGY.md`, وrunbooks للتشغيل يقلل الفوضى.

### 6.11 billing foundation

الاشتراكات والخطط والفواتير والمدفوعات اليدوية موجودة كأساس. هذا يميز المشروع عن متجر عادي.

### 6.12 domain/theme/storefront foundation

وجود الدومينات والثيم وإعدادات المتجر وstorefront عام يضع أساس Shopify-like واضح.

---

## 7. المخاطر والفجوات الحالية

### P0 - يجب إغلاقها قبل beta واسعة أو production

| الخطر | التفسير | المطلوب |
|---|---|---|
| Production readiness غير مكتمل | توجد runbooks وأساس، وأضيف skeleton للـ staging في `deploy/staging/`. تم إثبات smoke مؤقت كامل محلياً مع خدمات disposable، لكنه ليس staging خارجي حقيقي. | نشر صورة backend جديدة بعد إصلاح S3، تشغيل staging deployment خارجي، TLS/proxy، queues/scheduler، health، monitoring، rollback. |
| Frontend verification path | الفحص المحلي 2026-05-12 أثبت `audit/build/typecheck/e2e`، ومسار Docker الكامل للواجهة أثبت `install/typecheck/build/e2e`. | ربط نفس العقد بالـ CI required gates وعدم قبول merge إذا انكسر أحدها. |
| CI required gates | تم إثبات Quality Gates داخل GitHub Actions في PR #1 / run `25743248405`، وتفعيل required checks على `main` مع strict status checks وadmin enforcement. | إبقاء هذه البوابة مطلوبة عند أي تعديل للـ workflow، ومراقبة تنبيهات GitHub الخاصة بانتقال Actions runtime من Node 20 إلى Node 24. |
| Clean deployment proof غير مكتمل | Dockerfiles موجودة، وتم إثبات build smoke محلياً وGHCR staging publish بعد Trivy scan عبر run `25751543062`. كما أضيف `deploy/staging/staging-smoke.sh` و`staging-ephemeral-smoke.sh`. المسار المؤقت كشف نقص S3 ثم أصبح يمر محلياً بعد إضافة اعتماد الإنتاج. | نشر tag/digest جديد للـ backend بعد الإصلاح، تشغيل `target=ephemeral` في GitHub Actions، ثم تشغيل staging حقيقي يستهلك tag/digest مثبتاً عبر `target=environment`. |
| Monitoring/alerting/backups/restore | docs موجودة لكن لا يوجد تشغيل فعلي مثبت. | uptime، failed jobs، queue/scheduler، restore drill، alerts. |
| Security hardening | CSP واسع، 2FA أصبح موجوداً للـ admin/support/tenant owner داخل Filament، لا emergency reset ولا session/device management كامل، وdependency audits صارت تمر محلياً وداخل CI، وأضيف image vulnerability scan إلى Dockerfile Checks وpublish workflow. | إبقاء scans خضراء، ثم emergency 2FA reset، CSP tightening، secrets rotation، vulnerability review workflow أوسع. |
| Store tenant scoping review | تم في 2026-05-09 توثيق `Store` كاستثناء من `BelongsToTenant`، وجعل `forTenant(null)` fail-closed، وإزالة `tenant_id` من `Storefront/StoreResource` العام. | يبقى audit لاحق لأي query جديد على `Store` وتوسيع platform/admin tests عند إضافة flows جديدة. |
| catalog pagination/sitemap 48-limit | تم إصلاحه في 2026-05-09: sitemap صار يجمع المنتجات عبر pagination ويثبت ذلك E2E، والـ backend test يؤكد cap الصفحة الثانية. | يبقى sitemap index لاحقاً للمتاجر التي تتجاوز حد URL الآمن لكل sitemap. |
| cart duplicate item quantity normalization | تم إصلاحه في 2026-05-09: request validation و`CreateQuickOrder` يرفضان تكرار `product_id` في نفس checkout. | يبقى تحسين metrics للـ abuse/idempotency لاحقاً. |
| secrets hygiene | تم في 2026-05-09 إضافة `scripts/security/secret-hygiene.sh` و`scripts/release/clean-export-check.sh` وربطهما بالـ CI لمنع tracked env/private keys والتحقق من clean export package. | secret inventory، rotation procedure، وربط secret manager لاحقاً. |

### P1 - مهمة لبناء SaaS تجارية قابلة للبيع

- caching/revalidation للـ storefront.
- merchant onboarding wizard.
- store readiness/publish gate.
- product variants/options.
- stock movement ledger.
- product import/export.
- bulk order operations.
- order timeline غني.
- shipping zones/templates.
- COD reconciliation.
- invoice PDF.
- dunning.
- support messages/attachments.
- analytics dashboards.
- notification workflows.

### P2/P3 - نمو المنصة بعد الأساس التجاري

- integrations مع shipping providers.
- SMS/WhatsApp providers.
- webhooks.
- public API.
- API tokens/scopes.
- app installation model.
- marketplace mode.
- advanced theme/page sections.
- multi-warehouse إن أثبت السوق الحاجة.

---

## 8. التقييم الحالي

الأرقام تقريبية ومقصود بها توجيه القرار، لا إعطاء شهادة جاهزية.

| المجال | التقييم | التفسير |
|---|---:|---|
| Backend maturity | 7.5/10 | منظم، مختبر، وفيه domains كثيرة. يحتاج نضج revenue ops وstock/variants وبعض operational proof. |
| Tenancy/security model | 7/10 | أساس قوي متعدد الطبقات. يحتاج مراجعة `Store`, أي `withoutGlobalScope`, 2FA, CSP, scans. |
| Storefront maturity | 6.5/10 | جيد كبداية customer-facing، وaudit/build/typecheck/e2e تمر محلياً، وتم إصلاح sitemap pagination. يحتاج pagination UI، caching، UX polish، وa11y، وإثبات نفس العقد داخل CI. |
| Production readiness | 4.5/10 | runbooks وhealth وsmoke مؤقت كامل موجودة، لكن staging خارجي وTLS/restore/monitoring غير مثبتة. |
| Commercial SaaS readiness | 5/10 | foundation قوي، لكن onboarding/revenue dashboards/billing ops/support ops ناقصة. |
| DevOps maturity | 6/10 | Docker/CI baseline موجود، GitHub required gates مثبتة، مسار الواجهة الأخضر ثبت محلياً وداخل CI، وأضيف workflow يدوي للـ staging smoke مع target مؤقت كامل. ما زالت صورة backend الجديدة بعد S3، أسرار staging، وmonitoring/restore proof ناقصة. |
| Documentation maturity | 7/10 | docs كثيرة وصريحة. تحتاج إبقاءها متزامنة مع الفحص الحالي. |
| Testing maturity | 7.5/10 | backend جيد، وfrontend audit/build/typecheck/e2e يمر عند التشغيل المتسلسل، كما مر Docker storefront verification وGitHub Actions Quality Gates. المتبقي توسيع الاختبارات عند إضافة flows تجارية أكبر. |

التقييم العام: foundation قوي، pre-production، وليس production-ready.

---

## 9. خارطة الطريق الكبرى

### Phase 0: Foundation Hardening

الهدف: منع البناء فوق أرضية غير مثبتة.

- `مكتمل محلياً 2026-05-09`: repository hygiene.
- `مكتمل محلياً 2026-05-09`: clean clone/export rehearsal.
- `مكتمل 2026-05-09 ومتحقق مجدداً 2026-05-12`: بيئة frontend موحدة عبر Docker.
- `مكتمل محلياً 2026-05-12`: تحديث Next إلى `15.5.18` وإغلاق `pnpm audit --audit-level moderate`.
- `مكتمل محلياً 2026-05-12`: `pnpm build`, `pnpm typecheck`, و`pnpm test:e2e` تمر محلياً عند التشغيل المتسلسل، وآخر e2e أعطى `6 passed`.
- `مكتمل محلياً 2026-05-12`: `./storefront/scripts/verify-docker.sh all` مر بالكامل، بما فيه Playwright `6 passed`.
- `مكتمل محلياً 2026-05-12`: Dockerfile checks وDocker image build smoke للـ backend/storefront عبر `docker buildx build --check` و`docker buildx build --load`.
- `مكتمل محلياً 2026-05-12`: إضافة Trivy `0.70.0` إلى `Dockerfile Checks` و`container-images` لفحص صور backend/storefront ضد fixed `HIGH` و`CRITICAL` OS/library vulnerabilities، ومر الفحص محلياً للصورتين.
- `مكتمل جزئياً`: تقوية workflow بإضافة Composer audit، Pint، pnpm audit، E2E required، وDocker image build smoke.
- `مكتمل 2026-05-12`: إثبات `.github/workflows/quality.yml` داخل GitHub Actions على PR #1 / run `25743248405`.
- `مكتمل 2026-05-12`: تفعيل required checks على `main`: `Repository Hygiene`, `Backend`, `Storefront`, `Dockerfile Checks`, `Storefront E2E`.
- `مكتمل جزئياً`: staging deployment skeleton في `deploy/staging/`، ونجح `docker compose config` مع tags المنشورة، وأضيف smoke runner fail-closed وworkflow يدوي `.github/workflows/staging-smoke.yml`.
- `مكتمل محلياً 2026-05-12`: smoke مؤقت كامل عبر `deploy/staging/staging-ephemeral-smoke.sh all` مر على صورة backend محلية بعد إضافة `league/flysystem-aws-s3-v3`، وشمل migrations و`StorefrontDemoSeeder` وreadiness لكل من database/cache/queue/storage/redis/search.
- `مكتمل 2026-05-12`: Docker image push/promotion عبر GHCR workflow للـ staging channel في run `25751543062` بعد Trivy image scan.
- `مكتمل 2026-05-12`: دمج إصلاح S3 + smoke المؤقت في PR #7 / commit `096bc05`.
- `مكتمل 2026-05-12`: نشر صور backend/storefront جديدة عبر `container-images` run `25756290200` بالوسم `staging-20260512-096bc05` بعد Trivy image scan.
- `مكتمل 2026-05-12`: تشغيل **Staging Smoke** بـ `target=ephemeral` و`mode=all` على الصور المنشورة الجديدة في run `25756545567` ونجاح readiness بما فيها S3 storage.
- `التالي مباشرة`: تعبئة GitHub environment `staging` بالقيم الحقيقية وتشغيل **Staging Smoke** بـ `target=environment` و`mode=all` على خدمات staging خارجية حقيقية، ثم الانتقال إلى restore drill وmonitoring/alerting.
- monitoring.
- backup schedule.
- restore drill.
- alerting.
- error tracking.
- security baseline hardening.

معيار الخروج: لا تقبل ميزات تجارية ضخمة قبل أن يصبح backend + storefront + CI + staging قابلين للتكرار.

### Phase 1: SaaS Usability

الهدف: جعل التاجر يستطيع الوصول إلى متجر قابل للنشر بدون مطور.

- merchant onboarding wizard.
- store readiness checklist.
- publish gate.
- vendor dashboard.
- admin dashboard.
- support workflow.
- billing dashboard أولي.
- إعدادات قانونية وتجارية واضحة.

معيار الخروج: تاجر جديد يستطيع إنشاء متجر، إعداد الشحن والدفع والمنتجات الأساسية، ومعرفة ما ينقص قبل النشر.

### Phase 2: Commerce Core Expansion

الهدف: دعم متاجر حقيقية بكتالوج ومخزون وطلبات أكثر تعقيداً.

- product variants/options.
- stock movements ledger.
- product import/export.
- bulk order operations.
- order timeline.
- checkout UX.
- optional OTP أو phone confirmation عند الحاجة.

معيار الخروج: المتاجر ذات المنتجات المتنوعة والمخزون المتغير يمكنها العمل بدون حلول يدوية خطيرة.

### Phase 3: Algerian Shipping & COD

الهدف: جعل المنصة مناسبة فعلاً للسوق الجزائري.

- shipping zones.
- shipping templates.
- commune/wilaya rules.
- desk/home delivery support إن كان مناسباً.
- COD reconciliation.
- shipment batches.
- provider abstraction.

معيار الخروج: التاجر يستطيع إدارة الشحن والتحصيل والفشل في التوصيل بطريقة قابلة للمراجعة.

### Phase 4: Billing & Revenue Operations

الهدف: تحويل billing من foundation إلى نظام إيرادات.

- invoice PDF.
- dunning.
- payment proof.
- manual payment review.
- MRR/ARR.
- trial conversion.
- plan limits.
- usage meters.
- revenue dashboards.

معيار الخروج: مالك المنصة يرى الإيرادات والمخاطر والتحصيل وحدود الخطط بوضوح.

### Phase 5: Storefront Growth

الهدف: storefront قابل للنمو والبحث والتحويل.

- caching/revalidation.
- SEO v2.
- theme sections.
- image optimization.
- accessibility.
- Arabic/RTL polish.
- French support لاحقاً.
- mobile-first checkout.
- sitemap scale.
- product filters/search.

معيار الخروج: storefront سريع، قابل للفهرسة، ومناسب للهاتف والمتاجر الأكبر.

### Phase 6: Analytics & Intelligence

الهدف: قرارات مبنية على بيانات.

- vendor analytics.
- platform analytics.
- delivery analytics.
- product analytics.
- customer analytics.
- aggregation jobs.
- daily metrics tables.
- failed delivery insights.

معيار الخروج: dashboards لا تعتمد على queries ثقيلة مباشرة على جداول transactional.

### Phase 7: Integrations & Ecosystem

الهدف: فتح المنصة للخدمات الخارجية بدون ربط عشوائي.

- webhooks.
- public API.
- shipping providers.
- SMS/WhatsApp providers.
- app installation model لاحقاً.
- API tokens/scopes.
- integration logs.

معيار الخروج: كل integration يمر عبر abstraction، signing، retry، idempotency، logging، وscopes.

### Phase 8: Scale & Enterprise Hardening

الهدف: جاهزية أعلى لعملاء أكبر وتشغيل أثقل.

- performance profiling.
- queue scaling.
- caching layers.
- CDN.
- object storage hardening.
- session/device management.
- 2FA.
- CSP tightening.
- security audits.
- incident response.
- backup encryption.
- observability maturity.

معيار الخروج: المنصة قابلة لتحمل نمو حقيقي مع إجراءات أمان وتشغيل واضحة.

---

## 10. خطة زمنية مقترحة غير ملزمة

الجدول التالي توجيهي. يتغير حسب نتائج الاختبارات، سرعة التطوير، وتعقيد السوق.

| الفترة | التركيز | النتائج المتوقعة |
|---|---|---|
| الشهر 1 | Foundation/CI/Staging/Security baseline | بيئة frontend موحدة، CI required، staging أولي، monitoring/backup/security gaps محددة ومغلقة جزئياً. |
| الشهر 2 | Onboarding/Dashboards/Readiness | merchant onboarding، readiness gate، vendor/admin dashboards أولية، support workflow أفضل. |
| الشهر 3 | Variants/Inventory/Orders | variants، stock movements، bulk orders، order timeline، تحسين checkout. |
| الشهر 4 | Shipping/COD/Billing | shipping zones/templates، COD reconciliation، invoice PDF، dunning/payment proof. |
| الشهر 5 | Storefront/SEO/Theme/Analytics | caching، sitemap scale، SEO v2، theme sections، analytics aggregation. |
| الشهر 6 | Integrations/Scale/Security hardening | webhooks، API tokens، integrations abstraction، 2FA، CSP tightening، observability maturity. |

لا يجب اعتبار هذا الجدول وعد إطلاق. المشروع غير مستعجل؛ الهدف بناء صحيح ومتدرج.

---

## 11. Backlog مفصل حسب الدومين

### 11.1 Platform Owner

- الحالة الحالية: admin panel موجود، plans/subscriptions/invoices/support/audit foundation موجودة.
- المطلوب: dashboards للإيراد، الصحة التشغيلية، tenants المعرضين للخطر، failed jobs، billing alerts.
- الأولوية: P1 بعد Phase 0.
- معايير القبول: dashboard لا يكسر tenant isolation، محمي بسياسات، لا يستخدم queries ثقيلة بدون pagination/aggregation، موثق.

### 11.2 Merchant

- الحالة الحالية: vendor panel موجود مع catalog/orders/shipping/billing/settings/theme.
- المطلوب: onboarding wizard، readiness checklist، store publish gate، dashboard يومي.
- الأولوية: P1.
- معايير القبول: تاجر جديد يستطيع إكمال الحد الأدنى للنشر، وكل خطوة لها validation server-side.

### 11.3 Customer

- الحالة الحالية: storefront وcart/checkout/track-order موجودة.
- المطلوب: UX mobile أفضل، states أوضح، ثقة أعلى، سرعة أعلى.
- الأولوية: P1/P2.
- معايير القبول: checkout يعمل من الهاتف، لا توجد totals موثوقة في العميل، وe2e يغطي happy path.

### 11.4 Checkout

- الحالة الحالية: server-side totals، idempotency، abuse guard، inventory reservation.
- المطلوب: metrics، tuning، stricter no-key duplicate handling، optional OTP/confirmation.
- الأولوية: P0/P1.
- معايير القبول: اختبارات idempotency/concurrency، عدم تكرار الطلبات الحساسة، audit/logging مناسب بدون PII خام.

### 11.5 Catalog

- الحالة الحالية: products/categories/images/search foundation.
- المطلوب: variants/options، filters، product SEO fields، import/export.
- الأولوية: P1.
- معايير القبول: variants لا تكسر checkout/inventory، والـ API paginated، والاختبارات تغطي tenant isolation.

### 11.6 Inventory

- الحالة الحالية: inventory item لكل منتج، reservation/release/settle.
- المطلوب: stock movement ledger، low stock alerts، variant inventory.
- الأولوية: P1.
- معايير القبول: كل حركة مخزون قابلة للتدقيق، checkout يستخدم locks، ولا يوجد تعديل مخزون غير مفسر.

### 11.7 Orders

- الحالة الحالية: order status transitions، payments، shipments، histories.
- المطلوب: order timeline، bulk operations، filters، export، cancellation reasons أوضح.
- الأولوية: P1.
- معايير القبول: كل انتقال حالة مصرح ومختبر، bulk action يستخدم transactions/audit.

### 11.8 Shipping

- الحالة الحالية: wilayas/communes/rates/companies/shipments/returns.
- المطلوب: zones، templates، shipment batches، COD reconciliation، provider abstraction.
- الأولوية: P1.
- معايير القبول: لا integration مباشر بدون abstraction، وكل حساب شحن يتم server-side.

### 11.9 Billing

- الحالة الحالية: plans/features/subscriptions/invoices/manual payments/lifecycle.
- المطلوب: invoice PDF، payment proof، dunning، revenue analytics، usage meters.
- الأولوية: P1.
- معايير القبول: تغييرات مالية audited، server-side only، اختبارات لحالات التأخر والتجديد والتعليق.

### 11.10 Storefront

- الحالة الحالية: صفحات عامة وسلة وcheckout وSEO foundation.
- المطلوب: الحفاظ على dependency audit أخضر داخل CI، ثم caching/revalidation، pagination UI، image optimization، accessibility، better empty/error states.
- الأولوية: P1.
- معايير القبول: `pnpm audit`, `pnpm build`, `pnpm typecheck`, وe2e تمر، ولا تعتمد الواجهة على totals موثوقة.

### 11.11 SEO

- الحالة الحالية: sitemap/robots/canonical/OpenGraph/JSON-LD، وsitemap product pagination مثبت.
- المطلوب: sitemap index للمتاجر الضخمة، product SEO fields، image OG، structured data أوسع، custom domain smoke.
- الأولوية: P1.
- معايير القبول: sitemap يغطي أكثر من 48 منتج، ومع المتاجر الضخمة ينتقل إلى sitemap index بدل sitemap واحد كبير.

### 11.12 Theme System

- الحالة الحالية: theme settings، hero، trust/contact sections.
- المطلوب: configurable sections، presets، media strategy، preview.
- الأولوية: P2.
- معايير القبول: الثيم لا يسمح بكسر layout أو injection، ويحافظ على RTL/mobile.

### 11.13 Search

- الحالة الحالية: DB fallback وScout/Meilisearch foundation.
- المطلوب: indexing jobs، synonyms، filters، monitoring.
- الأولوية: P2.
- معايير القبول: search tenant-scoped، قابل لإعادة الفهرسة، ومراقب في production.

### 11.14 Notifications

- الحالة الحالية: أساس دعوات/اشتراكات وبعض notifications.
- المطلوب: order/vendor/customer/support notifications، templates، channels.
- الأولوية: P1/P2.
- معايير القبول: لا تسريب cross-tenant، retries مع idempotency عند الحاجة، logging واضح.

### 11.15 Support

- الحالة الحالية: support tickets وsupport panel.
- المطلوب: messages، attachments، SLA، macros، assignment workflow.
- الأولوية: P1.
- معايير القبول: support لا يرى إلا المسموح، attachments آمنة، actions audited.

### 11.16 Security

- الحالة الحالية: policies، tenancy، headers، throttles، readiness safeguards، 2FA للوحات الحساسة، dependency audits خضراء محلياً بعد تحديث Next، وsecret hygiene check.
- المطلوب: إبقاء dependency audits كحاجز CI، ثم emergency admin 2FA reset، CSP tightening، image/dependency vulnerability review workflow، session/device management، secrets rotation.
- الأولوية: P0.
- معايير القبول: tests، docs، CI scans، وproduction/staging validation.

### 11.17 DevOps

- الحالة الحالية: Dockerfiles، docker-compose، CI baseline، runbooks.
- المطلوب: required gates، staging deployment، image promotion، monitoring، restore drill.
- الأولوية: P0.
- معايير القبول: clean clone، CI green، staging proof، alerts tested، restore recorded.

### 11.18 Analytics

- الحالة الحالية: بعض أساس analytics/widgets.
- المطلوب: daily metrics، vendor/platform dashboards، delivery/product/customer analytics.
- الأولوية: P2.
- معايير القبول: aggregation jobs، no heavy dashboard queries، tenant isolation.

### 11.19 Integrations

- الحالة الحالية: مؤجلة عموماً.
- المطلوب: webhooks، API tokens/scopes، shipping/SMS/WhatsApp abstractions، integration logs.
- الأولوية: P2/P3.
- معايير القبول: signing، retries، idempotency، scopes، rate limits، audit.

### 11.20 Documentation

- الحالة الحالية: جيدة.
- المطلوب: إبقاء docs متزامنة، إضافة docs للدومينات التجارية عند نضجها.
- الأولوية: مستمرة.
- معايير القبول: كل feature تغير architecture/testing/security/runbooks تحدث الوثائق المناسبة.

### 11.21 Testing

- الحالة الحالية: backend جيد، وfrontend audit/build/typecheck/e2e تمر محلياً، ومسار Docker الكامل للواجهة مر في 2026-05-12.
- المطلوب: CI required، security scans، وإبقاء e2e مستقراً داخل CI.
- الأولوية: P0.
- معايير القبول: tests تعمل من clean clone/CI، والأرقام محدثة في docs.

---

## 12. مهام Codex المقترحة

### 12.1 قالب عام لأي مهمة Codex

```text
المهمة:
<وصف صغير ومحدد>

السياق:
<الدومين والملفات/الوثائق ذات العلاقة>

المطلوب:
<قائمة تنفيذ واضحة>

القيود:
- لا تغييرات خارج النطاق.
- احترم tenant isolation وPolicies وserver-side business rules.
- لا تثق في totals من الواجهة.

الاختبارات:
<الأوامر أو ملفات الاختبار المطلوبة>

التوثيق:
<ملفات docs التي يجب تحديثها إن تغير السلوك>

معيار القبول:
<نتيجة قابلة للتحقق>

ما لا يجب تغييره:
<ملفات أو دومينات ممنوعة في هذه المهمة>
```

### 12.2 أمثلة prompts قصيرة

#### تثبيت frontend environment

```text
المهمة: اجعل بيئة storefront قابلة للتشغيل داخل WSL أو Docker.
المطلوب: وثق وثبت طريقة تشغيل pnpm install/typecheck/build/e2e من clean clone.
القيود: لا تعدل منطق المنتج.
الاختبارات: pnpm typecheck, pnpm build, pnpm test:e2e أو سبب موثق للفشل.
```

#### catalog pagination/sitemap

```text
الحالة: مكتمل 2026-05-09 للـ 48-limit.
ما تم: storefront sitemap يستخدم pagination، والاختبارات تغطي الصفحة الثانية.
المتبقي لاحقاً: sitemap index للمتاجر الضخمة جداً إذا اقتربت من حد URL الآمن.
```

#### Store tenant scoping review

```text
المهمة: راجع tenant scoping لنموذج Store.
الحالة: مكتمل مبدئياً في 2026-05-09. بقي `Store` بدون BelongsToTenant كاستثناء موثق، لكن `forTenant(null)` صار fail-closed وتم إغلاق تسريب tenant_id من public StoreResource.
المطلوب لاحقاً: audit لأي query جديد على Store، وتوسيع platform/admin tests عند بناء flows جديدة.
القيود: لا تكسر domain resolution أو admin/support flows.
الاختبارات: tenant isolation وstore resolution tests.
```

#### CI hardening

```text
المهمة: قو CI واجعله قابلاً ليكون required gate.
المطلوب: backend tests/audit/Pint, storefront audit/typecheck/build, Docker checks/build smoke, required e2e مع artifacts عند الفشل.
القيود: لا تضف أسراراً.
معيار القبول: workflow يمر على clean branch وموثق في TESTING_STRATEGY.
```

#### Merchant onboarding

```text
المهمة: ابن onboarding wizard للتاجر.
المطلوب: خطوات store basics, legal info, shipping, payment, theme, first product.
القيود: كل validation في backend، ولا publish قبل readiness.
الاختبارات: feature tests وFilament workflow tests.
```

#### Store readiness

```text
المهمة: أضف store readiness checklist وpublish gate.
المطلوب: تحقق من بيانات المتجر، الشحن، payment method، legal pages، منتج منشور.
الاختبارات: policy + readiness action tests.
```

#### Product variants

```text
المهمة: صمم ونفذ product variants/options.
القيود: لا تكسر checkout أو inventory.
الاختبارات: variant selection، pricing server-side، inventory tenant isolation.
```

#### Stock movements

```text
المهمة: أضف stock movement ledger.
المطلوب: سجل لكل reserve/release/settle/manual adjustment.
الاختبارات: checkout inventory transitions وauditability.
```

#### COD reconciliation

```text
المهمة: أضف COD reconciliation foundation.
المطلوب: shipment collected amount، reconciliation status، batch review.
القيود: لا integration مباشر مع مزود خارجي.
```

#### invoice PDF

```text
المهمة: أضف توليد PDF للفواتير.
المطلوب: template آمن، أرقام minor units، download permissions.
الاختبارات: authorization، rendering smoke، invoice totals.
```

#### caching/revalidation

```text
المهمة: طبق استراتيجية caching/revalidation للstorefront.
السياق: ADR 0011 موجود كاتجاه.
القيود: لا تعرض بيانات متجر غير نشط، ولا تثق بالواجهة في المال.
الاختبارات: cache invalidation وstore unavailable behavior.
```

#### 2FA

```text
الحالة: منفذ للوحات Filament للـ super admins، platform support، وtenant owners، مع recovery codes وaudit.
المتبقي: emergency admin reset procedure مع audit ومراجعة UX يدوية.
الاختبارات: schema/model/service/middleware auth flows وpanel access.
```

#### webhooks

```text
المهمة: صمم webhooks foundation.
المطلوب: subscriptions، signed delivery، retries، logs، idempotency.
القيود: لا تبن app marketplace كامل الآن.
```

---

## 13. Definition of Done

أي ميزة تعتبر مكتملة فقط إذا تحقق الآتي حسب نطاقها:

1. الاختبارات المناسبة موجودة وتمر.
2. tenant isolation مثبت باختبارات عند لمس tenant-owned data.
3. policies مضافة/محدثة ومسجلة.
4. migrations آمنة، قابلة للمراجعة، ولا تكسر بيانات قائمة بدون خطة.
5. المال والمخزون والشحن والخصومات محسوبة server-side فقط.
6. لا توجد client-trusted totals.
7. العمليات الحساسة داخل transactions عند الحاجة.
8. idempotency أو حماية ضد التكرار موجودة للعمليات المعرضة لإعادة الإرسال.
9. audit log موجود للعمليات المالية أو التشغيلية الحساسة.
10. public payloads لا تكشف internal IDs غير ضرورية.
11. الأداء مأخوذ في الاعتبار: pagination، indexes، caching، أو jobs عند الحاجة.
12. الأمن مأخوذ في الاعتبار: validation، authorization، PII، secrets.
13. docs المناسبة محدثة.
14. CI green أو سبب عدم تشغيل جزء محدد موثق بوضوح.
15. rollback/migration notes مذكورة عند تغيير بنية حساسة.

---

## 14. قواعد منع الفوضى أثناء استخدام Codex

1. لا تعط Codex مهمة ضخمة جداً. قسم العمل إلى مهام صغيرة قابلة للتحقق.
2. لا تسمح بإعادة هيكلة شاملة بدون سبب معماري واضح.
3. كل مهمة لها نطاق ملفات واضح.
4. كل مهمة لها tests أو سبب موثق لعدم وجودها.
5. كل مهمة تغير سلوكاً عاماً تحدث docs.
6. أي تغيير في checkout/billing/inventory يتطلب مراجعة دقيقة.
7. أي `withoutGlobalScope` يتطلب اختباراً وتبريراً.
8. أي public payload يجب ألا يكشف internal IDs غير ضرورية.
9. أي تعديل في tenant-owned models يجب أن يثبت isolation.
10. لا تضف integrations مباشرة بدون abstraction.
11. لا تبدأ microservices الآن.
12. لا تبدأ marketplace كامل الآن.
13. لا تبدأ mobile app قبل نضج API/storefront.
14. لا تعدل migrations/models/controllers/frontend/tests في مهمة توثيق فقط.
15. لا تقبل "نجح عندي" بدون أوامر تحقق مكتوبة.

---

## 15. ما لا يجب فعله الآن

- لا microservices الآن.
- لا marketplace checkout الآن.
- لا app marketplace الآن.
- لا page builder حر ومعقد الآن.
- لا mobile app الآن.
- لا إطلاق production بدون staging/backup/monitoring.
- لا إضافة features بدون CI/tests.
- لا اعتماد `no-store` لكل شيء على المدى الطويل.
- لا كشف `tenant_id` في public APIs إن لم يكن ضرورياً.
- لا الاعتماد على duplicate checkout best-effort فقط في الحالات الحساسة.
- لا integrations خارجية قبل abstraction وlogging وretry/idempotency.
- لا بناء analytics dashboards ثقيلة مباشرة فوق transactional tables.
- لا إضافة variants قبل تثبيت أثرها على checkout/inventory/order items.

---

## 16. الأولويات العشر القادمة

1. `مكتمل 2026-05-12`: تحديث `next` إلى `15.5.18` وإعادة توليد `storefront/pnpm-lock.yaml` وتمرير `pnpm audit --audit-level moderate`.
2. `مكتمل 2026-05-12`: إعادة تشغيل التحقق الكامل للواجهة: `pnpm build`, `pnpm typecheck`, `pnpm test:e2e`، ثم `./storefront/scripts/verify-docker.sh all`.
3. `مكتمل 2026-05-12`: تمرير `.github/workflows/quality.yml` داخل GitHub Actions وتفعيلها في branch protection كـ required checks.
4. `مكتمل 2026-05-12`: تشغيل `container-images` workflow فعلياً إلى GHCR للـ staging channel.
5. `قيد التنفيذ`: أضيف مسار GitHub Actions يدوي لتوليد ملفات staging المهملة من GitHub environment وتشغيل `deploy/staging/staging-smoke.sh`. المتبقي الآن تعبئة أسرار/متغيرات `staging` حسب `deploy/staging/GITHUB_ENVIRONMENT.md` ثم تشغيل workflow **Staging Smoke** أولاً بـ `mode=validate` ثم `mode=all`.
6. تفعيل monitoring/alerting/error tracking.
7. `مكتمل 2026-05-09`: مراجعة tenant scoping الأساسية لـ `Store`، مع إبقائه exception موثقاً وfail-closed عند `forTenant(null)`.
8. `مكتمل 2026-05-09`: إصلاح catalog pagination وsitemap حتى لا تختفي المنتجات بعد أول 48 منتج.
9. security hardening: أضيف 2FA للوحات Filament الحساسة في 2026-05-16؛ المتبقي emergency reset، CSP، vulnerability review workflow، secrets rotation. تم إضافة secret hygiene وclean export baseline في 2026-05-09، وأضيف image vulnerability scanning إلى CI وpublish workflow في 2026-05-12.
10. merchant onboarding + store readiness، ثم product variants + stock movements.

هذا الترتيب يبدأ بالبنية والموثوقية قبل الميزات؛ لأن المشروع سيكبر عبر Codex، وأي ضعف في CI أو العزل أو التشغيل سيصبح مكلفاً لاحقاً.

---

## 17. ربط الوثيقة بباقي ملفات docs

### مراجع أساسية موجودة

- `ARCHITECTURE.md`: البنية العامة والدومينات وقرارات التقنية.
- `DEVELOPMENT_WORKFLOW.md`: قواعد التطوير والتحقق وCI workflow.
- `LOCAL_DEVELOPMENT.md`: setup محلي وclean workspace.
- `TESTING_STRATEGY.md`: استراتيجية الاختبار وأوامر التحقق.
- `PRODUCTION_READINESS.md`: runbook الإنتاج والحد الأدنى قبل beta.
- `SECURITY_BASELINE.md`: الوضع الأمني والفجوات.
- `TENANCY_RULES.md`: عقد tenant isolation.
- `BACKUP_RESTORE_RUNBOOK.md`: النسخ الاحتياطي والاسترجاع.
- `MONITORING_ALERTING_RUNBOOK.md`: المراقبة والتنبيهات.
- `QUEUE_SCHEDULER_RUNBOOK.md`: queue/scheduler supervision.
- `REVERSE_PROXY_RUNBOOK.md`: reverse proxy وcustom domains.
- `ALGERIA_GEOGRAPHY.md`: بيانات الولايات والبلديات وقواعدها.
- `STOREFRONT_CART.md`: عقد السلة والcheckout في الواجهة.
- `STOREFRONT_SEO.md`: SEO وsitemap وrobots.
- `STOREFRONT_THEME.md`: الثيم وأقسام العرض.
- `docs/adr/0001-modular-monolith.md`
- `docs/adr/0002-shared-database-tenancy.md`
- `docs/adr/0003-laravel-filament-backend.md`
- `docs/adr/0004-nextjs-storefront.md`
- `docs/adr/0005-backend-source-of-truth-for-commerce-money.md`
- `docs/adr/0006-do-not-trust-client-totals.md`
- `docs/adr/0007-69-wilayas-not-enabled-now.md`
- `docs/adr/0008-marketplace-deferred.md`
- `docs/adr/0009-manual-payments-first.md`
- `docs/adr/0010-algerian-shipping-strategy.md`
- `docs/adr/0011-storefront-caching-revalidation.md`
- `docs/adr/0012-production-deployment-topology.md`

### ملفات مقترح إضافتها لاحقاً عند نضج الدومينات

- `docs/BILLING.md`: عند توسيع invoice PDF/dunning/revenue ops.
- `docs/INVENTORY.md`: عند إضافة stock movements وvariants.
- `docs/SHIPPING_COD.md`: عند إضافة COD reconciliation ومزودي الشحن.
- `docs/WEBHOOKS_API.md`: عند بناء public API/webhooks.
- `docs/ANALYTICS.md`: عند إضافة daily metrics وdashboards.

---

## 18. الفجوات والتناقضات التي تحتاج تحققاً أو معالجة لاحقة

1. تم تصحيح حالة lockfiles: لا يوجد الآن `package-lock.json` في الجذر أو داخل `storefront/`، وبقيت الواجهة تعتمد على `storefront/pnpm-lock.yaml`.
2. تم تحديث الواجهة إلى `next@15.5.18`، و`pnpm audit --audit-level moderate` صار يمر بدون ثغرات معروفة، وتم إثبات ذلك داخل GitHub Actions.
3. تم تأكيد أن `pnpm typecheck` لا يجب أن يعمل بالتوازي مع `pnpm build` لأن `.next/types` قد تكون في حالة توليد جزئية. التشغيل المتسلسل في 2026-05-12 نجح.
4. تم إغلاق مراجعة `Store` الأساسية: يبقى exception من `BelongsToTenant` لحلّ المتجر/الدومين، لكن `scopeForTenant(null)` صار fail-closed، وتم توثيق ذلك.
5. تم إزالة `tenant_id` من `Storefront/StoreResource` العام، مع اختبار يمنع رجوعه في `resolve` و`home`.
6. تم إصلاح sitemap 48-limit: `storefront/src/lib/api.ts` صار يقرأ pagination meta، و`storefront/src/app/sitemap.ts` يجمع كل صفحات المنتجات المتاحة.
7. تم إصلاح تكرار `product_id` في cart checkout عبر validation وداخل `CreateQuickOrder`.
8. docs الإنتاج والأمن صريحة في أن monitoring/backup/restore/proxy موجودة كrunbooks لا كدليل تشغيل production.
9. تم التحقق محلياً من Dockerfile checks وimage build smoke للـ backend/storefront بتاريخ 2026-05-12، وتم إثبات GitHub required checks وGHCR publish. يوجد الآن smoke runner وworkflow يدوي جاهزان، وأضيف Trivy image scanning إلى CI وpublish workflow، لكن لم يتم بعد إثبات staging smoke حقيقي لأن GitHub environment `staging` موجودة بلا secrets أو variables.

---

## 19. قواعد القرار المستقبلية

عند ظهور خيار بين ميزة جذابة وأساس تشغيلي، يقدم الأساس التشغيلي إذا كانت الميزة ستزيد المخاطر.

عند ظهور خيار بين سرعة تنفيذ Codex ونظافة التصميم، تقسم المهمة وتضاف اختبارات بدلاً من قبول patch كبير.

عند ظهور تعارض بين تجربة الواجهة ومصدر الحقيقة، ينتصر backend. يمكن تحسين UX، لكن لا يمكن نقل القرار المالي أو التشغيلي إلى العميل.

عند ظهور حاجة integration، يبنى abstraction أولاً. لا يربط shipping/SMS/payment provider مباشرة داخل checkout أو controller.

عند ظهور رغبة في marketplace أو mobile app، تؤجل حتى ينضج API، security، billing، storefront، وoperations.

---

## 20. خلاصة العمل القادم

المشروع جيد بما يكفي ليستحق البناء الطويل، وليس جيداً بما يكفي للإطلاق المتسرع. نحن ما زلنا داخل Phase 0، لكن بوابة أمان الواجهة أُغلقت محلياً وداخل GitHub Actions، وتم تفعيل required gates على `main`، وتم إثبات GHCR staging publish، وأصبح staging smoke قابلاً للتشغيل بسكربت fail-closed وworkflow يدوي، وارتفع مستوى Dockerfile Checks بإضافة image vulnerability scan. الخطوة التالية الدقيقة التي تحتاج مدخلاً خارجياً هي تعبئة GitHub environment `staging` بالخدمات والأسرار ثم تشغيل smoke حقيقي، وبعدها monitoring/backup/restore/security hardening.

بعد ذلك يمكن الانتقال بثقة إلى SaaS usability، ثم commerce expansion، ثم shipping/COD الجزائري، ثم billing/revenue ops، ثم growth/integrations/scale.

الوثيقة يجب أن تبقى living document. أي نتيجة تحقق جديدة، أو قرار معماري، أو تغير في جاهزية الإنتاج يجب أن ينعكس هنا أو في الوثيقة المتخصصة المرتبطة بها.
