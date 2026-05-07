# تحليل شامل وخطة تطوير تنفيذية لمشروع dz-saas-commerce

آخر تحديث مثبت: 2026-05-07

نوع الوثيقة: Living Roadmap + Execution Contract لعمل الإنسان وCodex/AI agents على المشروع.

نطاق التحقق المستخدم في هذا التحديث:

- `backend/`
- `storefront/`
- `docs/`
- `docker-compose.yml`
- ملفات الإعداد والاختبارات الموجودة فعلياً

قاعدة أساسية: هذه الوثيقة لا تصف الطموح فقط. كل حالة مذكورة يجب أن تكون مبنية على أثر موجود في الكود أو على نتيجة تحقق عملية. عند الشك تصنف النقطة كـ `غير مثبت / يحتاج تحقق`.

حالات التصنيف المعتمدة:

- `مكتمل`
- `مكتمل جزئياً`
- `قيد البناء`
- `مطلوب`
- `مؤجل`
- `غير مثبت / يحتاج تحقق`
- `لا ينفذ الآن`

---

## 0. الهدف النهائي للمنتج

الهدف النهائي هو بناء منصة SaaS تجارة إلكترونية جزائرية كاملة، قابلة للبيع والتشغيل، تخدم:

1. مالك المنصة:
   - إدارة tenants، المتاجر، الخطط، الاشتراكات، الفواتير، الدعم، والإيرادات.
   - مراقبة المخاطر التشغيلية والتحصيل المالي وصحة النظام.
   - بناء عروض تجارية قابلة للتوسع: خطط، add-ons، analytics، domains، support tiers.

2. التاجر:
   - إنشاء متجر احترافي بسرعة.
   - إدارة المنتجات، الطلبات، الشحن، الدفع، العملاء، الموظفين، الدعم، والصفحات القانونية.
   - الوصول لأول طلب حقيقي بدون تدخل مطور.

3. الزبون النهائي:
   - Storefront سريع وواضح.
   - تجربة Arabic/RTL أساسية مع قابلية French لاحقاً.
   - Quick order مناسب للسوق الجزائري.
   - تتبع طلب، ثقة، وضوح في السعر والشحن والدفع.

المبدأ غير القابل للتفاوض: Laravel هو مصدر الحقيقة للأسعار، الخصومات، الشحن، المخزون، الدفع، الاشتراكات، وحدود الخطة. الواجهة لا تكون مصدراً موثوقاً لأي total أو قرار مالي.

---

## 1. ملخص تنفيذي محدث

المشروع حالياً Modular Monolith عملي لمنصة SaaS e-commerce جزائرية. الخلفية مبنية على Laravel 13 وFilament 5.6، والواجهة العامة مبنية على Next.js 15 وReact 19. الكود تجاوز مرحلة البداية، لكنه لم يصل بعد إلى production-grade SaaS.

### درجة النضج حسب السطح

| المجال | الحالة | التقييم المختصر |
|---|---|---|
| Backend Laravel | مكتمل جزئياً بدرجة قوية | domains كثيرة موجودة مع Actions وPolicies واختبارات جيدة. |
| Storefront Next.js | مكتمل جزئياً | home/products/cart/checkout/track-order/SEO موجودة، لكن caching وUX polish وimage strategy غير مكتملة. |
| Tenancy | مكتمل جزئياً بدرجة قوية | shared DB + `tenant_id` + global scope + DB constraints + tests. الخطر المتبقي هو مراجعة أي `withoutGlobalScope`. |
| Billing | مكتمل جزئياً | plans/features/subscriptions/invoices/manual payments/lifecycle موجودة، لكن revenue ops وPDFs وledger/dunning التجاري غير مكتملة. |
| Shipping | مكتمل جزئياً | wilayas/communes/rates/companies/shipments/returns موجودة، لكن integrations وtemplates وCOD reconciliation غير موجودة. |
| Production Readiness | مكتمل جزئياً | Dockerfiles وenv production examples وrunbook وhealth/readiness foundation وsecurity headers baseline وفحص production runtime safeguards موجودة. يوجد CI workflow baseline في الجذر، لكنه غير مثبت كـ merge gate فعلي بعد. لا reverse proxy config، ولا backup/restore drill، ولا monitoring/error tracking. |

### أكبر 5 مخاطر حالية

1. `مطلوب P0`: Repository hygiene غير محسوم. الجذر ليس git repository، بينما `backend/` فقط يحتوي `.git`. توجد ملفات محلية مثل `backend/.env` و`storefront/.env.local` ويجب التأكد أنها غير ملتزمة أو ضمن أي حزمة تسليم.
2. `مطلوب P0`: يوجد CI workflow baseline في `.github/workflows/quality.yml`، لكنه غير فعال عملياً كـ merge gate حتى تحسم استراتيجية المستودع ويعمل داخل GitHub.
3. `مطلوب P0`: Production readiness غير مكتمل رغم بدء أساس Docker/runbook/CI/health/security baseline: لا image builds مثبتة في CI، لا backup/restore drill، لا monitoring/error tracking، ولا reverse proxy config.
4. `مطلوب P0/P1`: بيئة Playwright غير موثوقة حالياً: `pnpm` غير متاح في PATH، وChromium فشل بسبب `libnspr4.so`.
5. `مطلوب P1`: Checkout idempotency foundation والتنظيف المجدول أصبحا موجودين، لكن يحتاجان tuning عملي للـ limits، metrics، وتشغيلاً فعلياً داخل CI/observability قبل beta واسع.

### أفضل ترتيب منطقي للمرحلة القادمة

1. إغلاق Repository Hygiene + Local Dev Reliability المتبقي.
2. تفعيل CI Quality Gates على المستودع الحقيقي.
3. إكمال Production Readiness Foundation المتبقية: backup/restore drill، monitoring/error tracking، reverse proxy، وتضييق CSP بعد التحقق.
4. تثبيت Playwright/e2e أو smoke بديل في CI.
5. Merchant Onboarding Foundation.
6. Storefront UX/Performance Polish.

هذا الترتيب يسبق Merchant Onboarding لأن البيئة، الأسرار، CI، وproduction operations لم تثبت بعد كجاهزة.

---

## 2. نتائج التحقق الفعلية بتاريخ 2026-05-07

### Backend

الأوامر التي تم تشغيلها:

```bash
cd backend
php artisan test
php artisan route:list
php artisan route:list --path=api/storefront
php artisan migrate:status
```

النتائج المثبتة:

- `php artisan test`: `149 passed (608 assertions)` بعد تنفيذ checkout idempotency وhealth/readiness foundation وsecurity headers baseline وprune command وproduction runtime safeguards.
- `php artisan route:list`: `135 routes`.
- Storefront API routes: `11 routes`.
- migrations: آخر migration مضافة ومطبقة محلياً هي `2026_05_07_000000_create_checkout_idempotency_records_table`.
- `php artisan migrate:status`: آخر migration بحالة `Ran`.

### Storefront

الأوامر التي تم تشغيلها:

```bash
cd storefront
npm run typecheck
npm run build
./node_modules/.bin/playwright test
```

النتائج المثبتة:

- `npm run typecheck`: نجح.
- `npm run build`: نجح.
- `npm run test:e2e` بصيغته الحالية فشل لأن `pnpm` غير موجود في PATH.
- تشغيل Playwright مباشرة وصل إلى tests لكنه فشل قبل تنفيذ السيناريوهات لأن Chromium يفتقد `libnspr4.so`.
- عند تشغيل Next dev يدوياً، أول compile للصفحة الرئيسية استغرق تقريباً 60 ثانية، لذلك `timeout: 120_000` في Playwright قد يكون حساساً على بيئات بطيئة.

تصحيح مهم: الرقم القديم `Playwright e2e: 6 passed` يبقى نتيجة تاريخية في 2026-04-28، لكنه غير مثبت في بيئة 2026-05-06. الحالة الحالية: `غير مثبت / يحتاج إصلاح بيئة`.

---

## 3. تصحيح التناقضات الداخلية

| التناقض أو الادعاء القديم | التصحيح الحالي | الحالة |
|---|---|---|
| backend tests مذكورة سابقاً بأكثر من رقم قديم. | آخر تحقق فعلي: `149 passed (608 assertions)`. | مكتمل |
| Storefront e2e مذكور كـ `6 passed`. | غير مثبت في 2026-05-06 بسبب `pnpm` و`libnspr4.so`. | غير مثبت / يحتاج تحقق |
| Milestone shipping كان يقول إن seed البلديات جزئي. | الكود والاختبارات الحالية تثبت `58 active wilayas` و`1541 active communes`. | مكتمل كـ geography v1 |
| السبرنت التالي كان Merchant Onboarding مباشرة. | يعاد ترتيبه بعد Repository Hygiene وProduction Readiness وCI activation وCheckout Idempotency. | مصحح |
| Production readiness كان مقيم بدرجة أعلى من الدليل الفعلي. | بعد 2026-05-07 توجد Dockerfiles وrunbook وCI/health baseline أولية، لكن لا CI مثبت كـ gate فعلي ولا backup drill ولا monitoring، لذلك يبقى التقييم منخفضاً. | مصحح |
| أوامر الواجهة موثقة بـ `pnpm` فقط. | `packageManager` يطلب pnpm، لكن PATH الحالي لا يحتويه. يجب توثيق تفعيل corepack/pnpm. | مطلوب |

---

## 4. حالة المشروع الحالية حسب الدومينات

| المجال | الحالة | الموجود فعلاً | الناقص | المخاطر | الأولوية | الخطوة التالية |
|---|---|---|---|---|---|---|
| Architecture | مكتمل جزئياً | Modular monolith، backend/storefront منفصلان، وثائق architecture موجودة. | ADRs غير موجودة، وحدود modules غير موثقة كقرارات رسمية. | قرارات كبيرة قد تتغير بدون سجل. | P1 | إضافة `docs/adr/` لاحقاً وتوثيق القرارات. |
| Backend Laravel | مكتمل جزئياً | Laravel 13، Actions، Models، Policies، Resources، Tests، وأوامر/مسارات health readiness. | بعض orchestration في controllers. | تضخم `app/` مع نمو المجالات. | P1 | نقل المنطق المتكرر إلى Actions/Support عند التطوير. |
| Filament Admin Panel | مكتمل جزئياً | admin resources للخطط، المتاجر، الاشتراكات، الفواتير، الدعم، audit. | system status، revenue dashboard، runbooks داخل اللوحة. | تشغيل المنصة يحتاج DB/manual inspection. | P1 | إضافة operational dashboards بعد Phase 0.5. |
| Filament Vendor Panel | مكتمل جزئياً | catalog/orders/shipping/billing/support/settings/theme/resources، tenant switcher. | onboarding wizard، readiness checklist، UX يومية أعمق. | التاجر قد يحتاج مطور لإكمال الإعداد. | P1 | Merchant Onboarding بعد P0. |
| Support Panel | مكتمل جزئياً | `/support` panel، support tickets، platform support role. | messages, attachments, SLA, macros. | الدعم لا يزال CRUD أكثر من helpdesk. | P1 | Support Center v2 لاحقاً. |
| Tenancy | مكتمل جزئياً قوي | `CurrentTenant`, `TenantResolver`, middleware, `BelongsToTenant`, policies, DB constraints. | مراجعة دورية لكل `withoutGlobalScope`. | أي bypass خاطئ قد يسبب data leak. | P0 مستمر | قاعدة مراجعة إلزامية واختبارات isolation لأي مجال جديد. |
| Database Schema | مكتمل جزئياً قوي | 43 migrations، ULIDs، tenant constraints، money minor units، JSONB، وجدول checkout idempotency records مع prune command. | stock movements، variants، support messages. | schema الحالي قوي لكن ليس كاملاً تجارياً. | P1 | stock movements ثم variants. |
| Checkout / Orders | مكتمل جزئياً أقوى | quick checkout، cart payload، totals server-side، inventory lock، order status transitions، `Idempotency-Key`، duplicate window، rate limits by IP/phone/store، abuse logging، وتنظيف سجلات idempotency المنتهية. | tuning للـ limits، phone confirmation workflow، real e2e للـ checkout، metrics. | spam checkout أقل خطراً لكنه يحتاج observability وتشغيل production. | P1 | tuning/metrics ثم phone confirmation. |
| Payments | مكتمل جزئياً | COD، manual payment actions، refund/fail flows. | payment integrations، callbacks/webhooks، reconciliation. | future callbacks تحتاج signature/idempotency. | P1 | manual reconciliation ثم integrations. |
| Subscriptions / Billing | مكتمل جزئياً | plans, features, subscriptions, invoices, manual subscription payments, lifecycle, grace/suspension. | invoice PDF، dunning كامل، revenue dashboard، tax/commercial invoice، ledger. | billing جيد داخلياً لا يكفي revenue operations. | P1 | Billing/revenue ops v2. |
| Shipping / Delivery | مكتمل جزئياً | 58 wilayas، 1541 communes، rates، companies، shipments، failed reasons، returns. | provider integrations، rate templates، free shipping، COD collection reconciliation. | operational shipping complexity في الجزائر. | P1 | Shipping Engine v2. |
| Returns | مكتمل جزئياً | return statuses/actions، restock/refund workflow، tests. | return labels/provider flow/customer UI. | returns قد تفصل عن shipments في الواقع. | P1 | ربط أعمق مع shipping/payment. |
| Customers | مكتمل جزئياً | customer model، quick checkout create/update by phone. | customer portal، segmentation، privacy/export rules. | بيانات شخصية تحتاج ضوابط عرض وتصدير. | P1 | security/privacy controls قبل exports. |
| Catalog / Products | مكتمل جزئياً | categories, products, images, status, search, storefront APIs. | variants/options، tags/collections، import/export، SEO fields. | متاجر حقيقية تحتاج variants. | P1 | Product Variants and Catalog Maturity. |
| Inventory | مكتمل جزئياً | inventory item per product، reservation/release/settle، backorders. | stock movements، variant inventory، low stock notifications. | audit المخزون محدود. | P1 | stock movements قبل multi-warehouse. |
| Coupons / Promotions | مكتمل جزئياً | coupons، usage limits، plan feature gate، checkout discount server-side. | promotion campaigns، free shipping coupons، advanced targeting. | coupon abuse يحتاج rules أوسع رغم وجود rate/idempotency أساسياً. | P1 | promotion rules وabuse analytics. |
| Domains | مكتمل جزئياً | custom domains، verification token، active/failed/disabled، resolver. | production DNS runbook، SSL/reverse proxy strategy. | domain activation يحتاج تشغيل production واضح. | P0/P1 | توثيق reverse proxy/domain runbook. |
| Store Settings | مكتمل جزئياً | store settings resource/API exposure. | legal/commercial completeness، readiness validation. | نشر متجر ناقص البيانات. | P1 | onboarding checklist. |
| Theme Settings | مكتمل جزئياً | theme colors, hero, trust/contact sections foundation. | theme templates، image optimization، section builder. | storefronts قد تبدو متشابهة. | P1/P2 | backend-driven theme configuration. |
| Storefront Next.js | مكتمل جزئياً | home/products/product/categories/search/cart/track/legal/SEO. | caching/revalidation، rich empty/error/loading states، image strategy، accessibility audit. | `no-store` يضغط backend. | P1 | performance/UX polish. |
| Cart | مكتمل جزئياً | client-side localStorage per store، checkout items payload. | abandoned cart، server cart، stale product validation UX. | client cart قد يحتوي منتجات قديمة. | P1 | stale cart handling. |
| SEO | مكتمل جزئياً | sitemap، robots، canonical، OpenGraph، JSON-LD basic. | pagination للسيت ماب الكبير، product SEO fields، richer structured data. | SEO جيد كأساس لا كاكتفاء تجاري. | P1 | advanced SEO fields. |
| Search | مكتمل جزئياً | DB fallback، Scout/Meilisearch preparation. | indexing jobs/runbook، typo/synonyms/filter facets. | search production غير موثق. | P2 | Meilisearch production notes. |
| Analytics | مكتمل جزئياً | vendor analytics tests/widgets موجودة. | conversion funnel، failed delivery analytics، revenue by wilaya. | قرارات تجارية بدون analytics كافية. | P1/P2 | analytics roadmap بعد onboarding. |
| Audit Logs | مكتمل جزئياً قوي | audit logs، policies immutable تقريباً، tests. | coverage لكل الأحداث المستقبلية، export controls. | أي event مالي جديد يحتاج audit. | P0 مستمر | تحديث audit عند كل workflow حساس. |
| Notifications | مكتمل جزئياً | subscription notifications وtenant invitations موجودة. | order/customer/vendor/support notifications الشاملة. | النظام لا يزال صامتاً في عمليات مهمة. | P1 | Notification domain events. |
| Security | مكتمل جزئياً | policies، tenant isolation، throttles عامة، invitation hashing مثبت بالاختبارات، audit، security headers baseline، وفشل readiness عند `APP_DEBUG=true` أو `APP_KEY` مفقود في production. | 2FA، CSP production tightening، session hardening، vulnerability scanning، secret rotation workflow. | لا يصلح production بدون hardening. | P0 | Security roadmap أدناه. |
| Testing | مكتمل جزئياً قوي للbackend | 149 tests، 608 assertions، 26 feature test files، checkout idempotency/prune tests، system health tests، production runtime safeguards tests، security headers tests، Playwright specs موجودة، وCI workflow baseline موجود. | e2e غير قابل للتشغيل محلياً حالياً، CI غير مثبت كـ gate فعلي، security/production tests المتقدمة ناقصة. | regression risk مع AI agents. | P0 | تفعيل CI Quality Gates وإصلاح e2e env. |
| DevOps | مكتمل جزئياً | docker-compose محلي، Dockerfiles أولية، env production examples، production runbook، health/readiness foundation، production runtime safeguards، security headers baseline، CI workflow baseline. | CI غير مفعل كـ merge gate، reverse proxy config، backups، monitoring. | production غير جاهز. | P0 | إكمال Production Readiness Foundation وتفعيل CI. |
| Documentation | مكتمل جزئياً | architecture/security/testing/tenancy/storefront docs موجودة. | runbooks production، clean clone setup، ADRs. | docs هندسية جيدة لكن التشغيل ناقص. | P0/P1 | Phase 0.5 ثم ADRs. |
| Developer Experience | مكتمل جزئياً | composer/npm scripts، docs workflow. | pnpm availability، clean setup verification، root repo strategy. | setup يحتاج تخمين. | P0 | Local Dev Reliability. |
| AI/Codex Workflow | مكتمل جزئياً | roadmap وworkflow docs. | quality gates وADRs وقواعد صارمة مرتبطة بـ CI. | AI قد يضيف تغييرات واسعة بدون حواجز. | P0/P1 | CI + Codex rules. |

---

## 5. Phase 0.5 - Repository Hygiene, Secrets, and Local Dev Reliability

الأولوية: P0

الحالة: مكتمل جزئياً

سبب المرحلة: المشروع سيستمر بالتطور عبر Codex وAI agents. لذلك يجب أن تكون البيئة نظيفة، آمنة، قابلة للتكرار من clone نظيف، وخالية من أسرار أو artifacts غير مقصودة.

### أدلة الحالة الحالية

- الجذر `/home/ahmed/projects/dz-saas-commerce` ليس git repository.
- `backend/` يحتوي `.git`.
- `storefront/` و`docs/` لا يظهران داخل git root موحد من الجذر الحالي.
- توجد ملفات محلية: `backend/.env` و`storefront/.env.local`.
- Dockerfiles production baseline موجودة الآن للbackend والstorefront.
- لا توجد CI ظاهرة.
- `storefront/package.json` يحدد `pnpm@10.33.2`، لكن `pnpm` غير متاح في PATH الحالي.
- `.gitignore` موجود الآن في الجذر وداخل `backend/` و`storefront/` مع قواعد تمنع ملفات البيئة والـ dependencies والـ build/test artifacts.
- `docs/LOCAL_DEVELOPMENT.md` موجود لتوثيق clean local setup.
- `backend/.env.testing.example` موجود.

### المهام

1. `مطلوب`: تحديد استراتيجية المستودع:
   - إما جعل الجذر repository واحداً يضم `backend/`, `storefront/`, `docs/`.
   - أو توثيق سبب بقاء `backend/` فقط repository منفصل.
2. `مكتمل جزئياً`: تم فحص أنماط secrets مع استبعاد `.env` المحلي و`vendor/node_modules`. النتائج المتبقية هي dummy local credentials في `docker-compose.yml` و`.env.example`. لا يزال يلزم فحص أي zip/codex handoff سابق خارج workspace الحالي.
3. `مكتمل جزئياً`: `.gitignore` يمنع `.env` و`.env.local` في الجذر/backend/storefront. لا يزال يلزم تأكيد ذلك بعد حسم استراتيجية المستودع.
4. `مطلوب`: تدوير أي secrets ظهرت في ملفات محلية أو حزم سابقة.
5. `مكتمل`: تم تحديث `backend/.env.example` ليعكس PostgreSQL/Redis/Meilisearch/MinIO/Mailpit المحلي بقيم dummy development.
6. `مكتمل`: تم تحديث `storefront/.env.example` لتوثيق API/asset/storefront base URLs وfallback store identifiers.
7. `مكتمل`: تمت إضافة `backend/.env.testing.example`.
8. `مكتمل جزئياً`: تمت مراجعة وتحديث `.gitignore` بحيث يمنع:
   - `.env`
   - `.env.local`
   - `vendor`
   - `node_modules`
   - `.next`
   - `storage/logs`
   - build artifacts
   - local SQLite files
   - coverage artifacts
   - Playwright reports/test-results إذا لم تكن artifacts مقصودة
9. `مكتمل جزئياً`: تم تجاهل dependencies/build artifacts. لم تتم إزالة الموجود محلياً لأن بعضها قد يكون مستخدماً في البيئة الحالية.
10. `مكتمل`: تم توثيق local setup من clone نظيف في `docs/LOCAL_DEVELOPMENT.md`:
    - backend install
    - storefront install
    - docker-compose services
    - migrations
    - seed demo data
11. `مكتمل`: تم توثيق أوامر تشغيل backend:
    - `php artisan serve`
    - queue worker
    - scheduler
12. `مكتمل`: تم توثيق أوامر تشغيل storefront بصيغة صحيحة:
    - تفعيل pnpm عبر corepack أو استخدام `npx pnpm@10.33.2`.
    - عدم الاعتماد على `pnpm` غير موجود في PATH.
13. `مكتمل`: تم توثيق أوامر الاختبار:
    - backend tests
    - storefront typecheck/build
    - e2e مع system deps المطلوبة.
14. `مكتمل`: تم توثيق كيفية تشغيل queue worker والسcheduler محلياً.
15. `مكتمل`: تم توثيق seed بيانات demo آمنة بدون أسرار.

### منجزات 2026-05-07

- إضافة root `.gitignore` لحزمة workspace.
- تحديث `backend/.gitignore` و`storefront/.gitignore`.
- تحديث `backend/.env.example`.
- إضافة `backend/.env.testing.example`.
- تحديث `storefront/.env.example`.
- إضافة `docs/LOCAL_DEVELOPMENT.md`.
- تحديث `docs/DEVELOPMENT_WORKFLOW.md`.
- تحديث `docs/TESTING_STRATEGY.md`.
- تحديث `docs/SECURITY_BASELINE.md`.

### المتبقي لإغلاق Phase 0.5

1. حسم استراتيجية المستودع من المالك: root monorepo أم repos منفصلة.
2. تنفيذ clean-clone rehearsal فعلي بعد حسم Git strategy.
3. فحص أي ZIP أو handoff سابق خارج workspace الحالي.
4. تدوير أي secrets كانت قد شاركت خارج الجهاز المحلي.
5. إضافة `.env.production.example` ضمن Production Readiness.

### Definition of Done

- لا توجد secrets فعلية في الملفات الملتزمة أو الحزمة النظيفة.
- يمكن تشغيل المشروع من clone نظيف اعتماداً على الوثائق فقط.
- لا توجد `vendor`, `node_modules`, `.next`, build artifacts داخل الحزمة النظيفة.
- أوامر backend وstorefront والاختبارات موثقة وقابلة للتنفيذ.
- `pnpm` أو بديله موثق بوضوح.
- Codex يستطيع فهم التشغيل دون تخمين.

---

## 6. Immediate Next Sprint Recommendation

الحالة: محدث في 2026-05-07

تم بدء Repository Hygiene + Local Dev Reliability وProduction Readiness Foundation وCI baseline وCheckout Idempotency foundation وHealth/Readiness foundation وSecurity Headers baseline في 2026-05-07. لم تغلق مرحلة P0 بالكامل لأن استراتيجية المستودع، clean-clone rehearsal، CI الفعلي، backup/restore، وPlaywright/e2e لا تزال تحتاج إغلاقاً عملياً.

الترتيب المقترح:

1. `P0` إغلاق المتبقي من Repository Hygiene + Local Dev Reliability.
2. `P0` CI Quality Gates activation على المستودع الحقيقي.
3. `P0` Production Readiness Foundation v1 المتبقية: backup/restore + monitoring/error tracking + reverse proxy + CSP tightening.
4. `P0/P1` Playwright/e2e reliability أو smoke بديل في CI.
5. `P1` Merchant Onboarding Foundation.
6. `P1` Storefront UX/Performance Polish.

قبول السبرنت الأول:

- root/repo strategy موثقة أو محسومة.
- clean-clone rehearsal موثق بنتيجة عملية.
- أي أسرار شاركت خارج الجهاز المحلي تم تدويرها.
- `.env.production.example` موجود الآن للbackend والstorefront، ويتبقى التحقق عبر CI/clean-clone.
- Playwright system dependencies موثقة ومثبتة في CI لاحقاً.

---

## 7. Checkout Idempotency and Abuse Protection

الأولوية: P0 قبل أي beta عام

الحالة الحالية: مكتمل جزئياً قوي

الموجود فعلاً:

- checkout route عليه `throttle:20,1`.
- storefront API group عليه `throttle:120,1`.
- track order عليه `throttle:60,1`.
- Laravel يحسب totals ولا يثق بالواجهة.
- checkout يعمل داخل transaction ويستخدم locks للمنتجات والمخزون.
- جدول `checkout_idempotency_records` موجود ومربوط بـ tenant/store/order.
- دعم `Idempotency-Key` في Laravel checkout.
- Next.js quick order يرسل `Idempotency-Key` إلى route proxy، والـ proxy يمرره إلى Laravel.
- request hash محفوظ لكل محاولة checkout.
- customer phone محفوظ بصيغته normalized بعد validation.
- نفس `Idempotency-Key` لنفس tenant/store/payload يعيد نفس الطلب ولا ينشئ طلباً ثانياً.
- نفس `Idempotency-Key` مع payload مختلف يرجع `409 Conflict`.
- نفس key يمكن استخدامه بأمان في tenant/store مختلفين بدون تسريب أو conflict عابر للـ tenants.
- duplicate checkout window عند غياب header يعيد الطلب الموجود داخل نافذة قصيرة بدلاً من إنشاء duplicate واضح.
- rate limiting مخصص حسب IP وphone وstore عبر `CheckoutAbuseGuard`.
- logging لمحاولات rate-limit/idempotency conflict/duplicate replay مع hashes بدلاً من تسريب phone/IP الخام.
- أمر `checkout-idempotency:prune` موجود مع خيار `--dry-run`.
- الـ scheduler يشغل `checkout-idempotency:prune` يومياً عند 03:00.
- الاختبارات تغطي:
  - عدم إنشاء طلبين لنفس key.
  - conflict عند payload مختلف.
  - tenant/store isolation لهذه الطبقة.
  - duplicate order window.
  - استمرار totals/inventory داخل Laravel.
  - حذف السجلات المنتهية وترك السجلات النشطة.
  - dry-run بدون حذف.

الناقص:

1. `P1`: tuning عملي لقيم rate limits بعد مراقبة traffic حقيقي أو staging load.
2. `P1`: dashboard/metrics لمحاولات checkout المرفوضة أو المعادة.
3. `P1`: real integration e2e يثبت أن Next.js يرسل header وأن Laravel يعيد نفس order عند replay.
4. `P1`: phone confirmation أو OTP اختياري للمتاجر المعرضة للسبام.

ملاحظة تنفيذية: هذه المرحلة يجب أن تسبق onboarding العام لأن onboarding سيزيد عدد المتاجر العامة واحتمالية إساءة استخدام checkout.

---

## 8. Production Readiness Foundation

الأولوية: P0

الحالة: مكتمل جزئياً

الموجود فعلاً:

- `docker-compose.yml` محلي للخدمات: PostgreSQL, Redis, Meilisearch, MinIO, Mailpit.
- Laravel queue/scheduler capabilities موجودة ضمن الإطار.
- storefront build production ينجح محلياً.
- `backend/Dockerfile` موجود كـ PHP-FPM production baseline.
- `storefront/Dockerfile` موجود كـ Next.js production baseline.
- root/backend/storefront `.dockerignore` موجودة.
- `backend/.env.production.example` موجود.
- `storefront/.env.production.example` موجود.
- `docs/PRODUCTION_READINESS.md` موجود كـ runbook أولي.
- `GET /api/system/health/live` موجود.
- `GET /api/system/health/ready` موجود.
- `php artisan system:health --scope=live|ready --format=json` موجود.
- backend Docker `HEALTHCHECK` موجود للـ liveness.
- CI baseline يشغل readiness smoke بعد migrations.
- security headers baseline موجود عبر middleware في Laravel و`headers()` في Next.js.
- أمر `checkout-idempotency:prune` موجود ومجدول يومياً لصيانة جدول idempotency.
- readiness يحتوي فحص `environment` يفشل في production عند `APP_DEBUG=true` أو `APP_KEY` مفقود.

غير موجود أو غير مثبت:

- reverse proxy config فعلي.
- environment separation موثق أولياً في runbook، لكنه غير مثبت بتشغيل staging/production فعلي:
  - local
  - testing
  - staging
  - production
- CI فعلي داخل GitHub Actions غير مثبت.
- queue worker runbook يحتاج تشغيل/إشراف production فعلي.
- scheduler runbook يحتاج تشغيل/إشراف production فعلي.
- failed jobs handling runbook يحتاج alerting وإجراءات تشغيلية أعمق.
- log strategy.
- error tracking strategy.
- backup strategy.
- restore drill.
- storage strategy production S3-compatible.
- Meilisearch production notes.
- Redis production notes.
- database migration procedure.
- zero/minimal downtime deployment notes.
- maintenance mode/store unavailable behavior runbook.

### المهام المطلوبة

1. `مكتمل جزئياً`: Dockerfile backend production موجود ويحتاج CI build validation.
2. `مكتمل جزئياً`: Dockerfile storefront production موجود ويحتاج CI build validation.
3. `مطلوب P0`: reverse proxy strategy/config:
   - TLS termination
   - custom domains
   - Laravel API
   - Next storefront
   - static/storage assets
4. `مكتمل`: `.env.production.example` بدون أسرار للbackend والstorefront.
5. `مكتمل جزئياً`: `APP_DEBUG=false` موثق في production env examples ومفحوص في readiness عند `APP_ENV=production`، ويتبقى إثباته داخل deployment/staging فعلي.
6. `مكتمل جزئياً`: health endpoint/command يغطي:
   - DB
   - Redis
   - Queue
   - Storage
   - Meilisearch
7. `مكتمل`: readiness checks منفصلة عن liveness.
8. `مطلوب P0`: queue worker supervision/runbook تفصيلي.
9. `مطلوب P0`: scheduler supervision/runbook تفصيلي.
10. `مطلوب P0`: failed jobs handling مع alerts.
11. `مطلوب P0`: backup/restore runbook مع restore drill.
12. `مطلوب P1`: storage production strategy:
    - local dev: public/local/MinIO
    - production: S3-compatible private/public buckets
13. `مطلوب P1`: database migration procedure:
    - backups before migrations
    - maintenance mode when needed
    - rollback limits
14. `مطلوب P1`: error tracking وstructured logs.
15. `مكتمل جزئياً`: security headers baseline موجود، ويتبقى تضييق CSP والتحقق عبر browser/e2e قبل production.

### منجزات 2026-05-07

- إضافة `backend/Dockerfile`.
- إضافة `storefront/Dockerfile`.
- إضافة root/backend/storefront `.dockerignore`.
- إضافة `backend/.env.production.example`.
- إضافة `storefront/.env.production.example`.
- إضافة `backend/docker/php-production.ini`.
- إضافة `docs/PRODUCTION_READINESS.md`.
- إضافة `App\Support\System\SystemHealthChecker`.
- إضافة endpoints: `/api/system/health/live` و`/api/system/health/ready`.
- إضافة أمر `php artisan system:health --scope=live|ready --format=json`.
- إضافة backend Docker `HEALTHCHECK`.
- إضافة readiness smoke إلى CI baseline.
- إضافة security headers baseline للـ backend والـ storefront.
- إضافة `checkout-idempotency:prune` مع `--dry-run` وجدولته يومياً.
- إضافة فحص production runtime safeguards داخل readiness.

Definition of Done:

- production deployment يمكن شرحه وتشغيله من docs بدون تخمين.
- health/readiness checks قابلة للاستخدام في orchestration.
- backup/restore مجرب مرة واحدة على الأقل.
- لا توجد production secrets داخل repo.

---

## 9. CI Quality Gates

الأولوية: P0/P1

الحالة: مكتمل جزئياً

الدليل الحالي:

- تمت إضافة `.github/workflows/quality.yml` كـ baseline لجذر المشروع.
- الجذر الحالي ليس Git repository، بينما `backend/` فقط يحتوي `.git`. لذلك لا تعتبر هذه المرحلة مكتملة أو فعالة كـ merge gate حتى تحسم استراتيجية المستودع ويثبت تشغيل workflow داخل GitHub.
- لم يتم تشغيل workflow فعلياً داخل GitHub Actions بعد.
- تم فحص Dockerfiles محلياً عبر `docker buildx build --check` للـ backend والـ storefront بدون warnings.

### Pipeline الحالي المقترح والمنفذ كـ baseline

Jobs منفصلة:

1. Backend quality:
   - `composer validate`
   - `composer install --no-interaction --prefer-dist`
   - `php artisan migrate:fresh --seed` في testing DB عند الإمكان
   - `php artisan test`
   - `php artisan route:list` smoke check
   - PostgreSQL service للـ testing

2. Storefront quality:
   - تفعيل `pnpm@10.33.2` عبر corepack أو action مناسب
   - `pnpm install --frozen-lockfile`
   - `pnpm typecheck`
   - `pnpm build`
   - `pnpm lint` فقط إذا أضيف script رسمي

3. E2E:
   - install Playwright browsers/system deps
   - تشغيل mock backend e2e حالياً عند تفعيل `RUN_E2E=true`
   - upload artifacts عند الفشل

4. Dockerfile checks:
   - `docker buildx build --check -f backend/Dockerfile backend`
   - `docker buildx build --check -f storefront/Dockerfile storefront`

5. Dependency/cache:
   - composer cache
   - pnpm cache
   - Playwright browser cache عند الإمكان

قواعد merge:

- يمنع merge إذا فشل backend tests أو storefront typecheck/build.
- يجب منع merge إذا فشلت Dockerfile checks بعد تفعيل CI على المستودع الحقيقي.
- e2e يمكن أن يبدأ optional بسبب اعتماده الحالي على mocks وsystem dependencies، ثم يصبح required قبل beta.

المتبقي:

1. حسم هل الجذر يصبح Git repository/monorepo أم تبقى المستودعات منفصلة.
2. تشغيل `.github/workflows/quality.yml` في GitHub Actions فعلياً وتصحيح أي فرق بين CI والبيئة المحلية.
3. إضافة image build/push jobs عند اعتماد registry.
4. إضافة dependency vulnerability scanning.
5. جعل e2e gate إلزامياً بعد إصلاح Playwright dependencies وتحديد mocked vs real integration coverage.

---

## 10. Security Roadmap

### موجود

- tenant isolation كحد أمني.
- policies مسجلة لكثير من النماذج.
- super admin bypass مع استثناءات audit log mutation.
- public API throttles عامة.
- checkout لا يثق بالواجهة.
- invitation token hashing مثبت بالاختبارات.
- domain verification tokens وحالات domain.
- audit logs وسياسة immutable تقريباً.
- database constraints لعلاقات tenant حساسة.
- production runtime safeguards داخل readiness:
  - يفشل عند `APP_DEBUG=true` في production.
  - يفشل عند غياب `APP_KEY` في production.
- security headers baseline:
  - `X-Content-Type-Options`
  - `X-Frame-Options`
  - `Referrer-Policy`
  - `Permissions-Policy`
  - CSP واسع متوافق حالياً مع Filament/Livewire/storefront
  - HSTS عند HTTPS

### ناقص

- secrets handling رسمي.
- إثبات `APP_DEBUG=false` و`APP_KEY` عبر readiness في بيئة staging/production فعلية، لا محلياً فقط.
- CSP production tightening بعد browser/e2e validation.
- 2FA للـ super admin.
- 2FA للـ tenant owner.
- rate limits operational tuning by phone/store/IP.
- admin session security/device management.
- dependency vulnerability scanning.
- log redaction policy.
- backup encryption.
- file upload validation strategy.
- storage visibility strategy.
- webhook/payment callback security للمستقبل.

### مطلوب قبل beta

1. Phase 0.5 secrets/repository hygiene.
2. checkout idempotency tuning وmetrics بعد تنفيذ foundation والتنظيف المجدول.
3. 2FA للـ super admin.
4. review شامل لـ `withoutGlobalScope('current_tenant')`.
5. مراقبة rate limits by IP/phone/store للـ checkout وضبطها بعد staging.
6. log redaction للهواتف والعناوين عند الحاجة.
7. تثبيت security headers smoke في CI عند تفعيل workflow.

### مطلوب قبل production

1. 2FA للـ tenant owner أو enforceable policy.
2. backup encryption وrestore drill.
3. vulnerability scanning في CI.
4. production CSP مضبوط ومختبر مع Filament وstorefront.
5. signed webhooks/payment callbacks عند إضافة integrations.
6. file upload MIME/size/visibility validation.
7. session/device management للحسابات الحساسة.

---

## 11. Tenancy Roadmap

الحالة الحالية: مكتمل جزئياً قوي.

الموجود:

- shared database tenancy.
- `tenant_id` على الجداول التجارية.
- `CurrentTenant` scoped service.
- `TenantResolver` للـ host/user/session.
- `ResolveTenantFromRequest` middleware.
- `BelongsToTenant` global scope.
- policies وtenant permissions.
- composite DB constraints لعلاقات مهمة.
- Filament vendor tenant context.
- storefront tenant resolution by host ثم fallback identifier.
- custom domains عبر `domains` مع verification.
- tenant switcher v1.
- tests tenant isolation في عدة مجالات.

القاعدة الإلزامية:

أي استخدام لـ `withoutGlobalScope('current_tenant')` يجب أن يكون مبرراً ومصحوباً بفلترة `tenant_id` صريحة أو سياق admin/support واضح ومحمي بسياسة. المثال الآمن:

```php
Model::query()
    ->withoutGlobalScope('current_tenant')
    ->where('tenant_id', $tenantId);
```

المخاطر:

- admin/support cross-tenant queries تحتاج تمييزاً واضحاً.
- أي resource جديد في Filament يجب أن يمنع cross-tenant select options.
- public storefront resources يجب ألا تعرض internal tenant identifiers غير الضرورية.

المهام:

1. `P0 مستمر`: test tenant isolation لأي domain جديد.
2. `P0 مستمر`: مراجعة `withoutGlobalScope` في code review/CI grep report.
3. `P1`: إخفاء `tenant_id` من public StoreResource إن لم تكن هناك حاجة عامة له.
4. `P1`: stale session tenant cleanup عند إزالة membership.

---

## 12. Storefront Roadmap

الحالة الحالية: مكتمل جزئياً.

الموجود فعلاً:

- Next.js app router.
- store resolution عبر host/default store.
- product listing.
- product details.
- categories.
- search page/API.
- cart client-side.
- quick checkout.
- cart checkout.
- track order.
- legal pages.
- SEO metadata.
- sitemap.
- robots.
- JSON-LD basic: Store, Product, BreadcrumbList.
- theme colors/hero/trust/contact.
- Arabic/RTL مع copy يدعم French جزئياً.
- Playwright e2e specs مع mock backend موجودة.

النواقص:

- caching/revalidation: أغلب `storefrontFetch` يستخدم `cache: "no-store"`.
- loading states محدودة.
- error/empty states تحتاج polish.
- image optimization strategy غير مكتملة؛ تستخدم صفحات كثيرة `img` وليس `next/image`. هذا ليس خطأ مطلقاً، لكنه يحتاج قراراً عملياً حسب مصدر الصور/CDN.
- accessibility audit غير مثبت.
- performance budget غير مثبت.
- sitemap pagination للمتاجر الكبيرة غير موجود.
- real integration e2e غير موجود؛ الموجود mocked e2e.
- Playwright غير موثوق بيئياً حالياً.

المهام:

1. `P0`: إصلاح e2e environment.
2. `P1`: إضافة caching/revalidation strategy:
   - home
   - categories
   - product details
   - theme/store settings
3. `P1`: cache invalidation عند تحديث product/category/theme/store.
4. `P1`: تحسين error/empty/loading states.
5. `P1`: تحسين mobile UX للcheckout والسلة.
6. `P1`: accessibility smoke checks.
7. `P1/P2`: image optimization/CDN strategy.
8. `P2`: French language strategy كاملة إن كانت هدفاً تجارياً قريباً.

---

## 13. Billing Roadmap

الحالة الحالية: مكتمل جزئياً.

الموجود:

- plans.
- plan features.
- subscriptions.
- invoices.
- subscription payments.
- manual payment confirmation/rejection.
- grace periods.
- tenant/store suspension.
- renewal reminders.
- usage counters.
- feature gates.
- vendor billing overview.
- billing lifecycle tests.

الناقص:

- invoice PDF export.
- payment proof upload/preview workflow التجاري الكامل.
- tax/commercial invoice concerns.
- ledger/accounting model.
- revenue operations dashboard:
  - MRR
  - overdue
  - churn
  - active stores
- plan upgrade/downgrade scheduling الكامل.
- dunning workflow كامل.
- reconciliation للمدفوعات اليدوية.

الأولوية:

- `P1`: revenue dashboard وinvoice PDF وmanual payment review.
- `P1`: dunning notifications.
- `P2`: ledger/accounting بعد تثبيت نموذج الإيرادات.

---

## 14. Shipping Roadmap

الحالة الحالية: مكتمل جزئياً.

الموجود:

- 58 wilayas.
- 1541 communes.
- shipping companies.
- shipping rates.
- delivery types: home/desk.
- shipments.
- shipment status histories.
- failed delivery reasons.
- returns.
- tracking number support.

الناقص:

- COD collection reconciliation.
- delivery provider integration.
- rate templates.
- free shipping rules.
- shipping zones.
- provider labels.
- failed delivery analytics.
- delivery success rate dashboard.

69 ولاية:

- `مؤجل / research`: لا تفعل الآن.
- السبب: لا يوجد داخل المشروع mapping موثوق ومختبر يربط 1541 commune بالتقسيم الجديد إلى 69 ولاية.
- أي تفعيل قبل mapping رسمي سيكسر shipping rates وcheckout.

---

## 15. Testing Strategy محدثة

الحالة الحالية: مكتمل جزئياً.

### Backend

- الإطار: Pest 4 فوق PHPUnit 12.
- آخر تحقق: `149 passed (608 assertions)`.
- Feature test files المثبتة: `26`.
- المجالات المغطاة:
  - tenancy
  - tenant switcher
  - tenant integrity constraints
  - catalog
  - checkout
  - orders
  - payments
  - shipping
  - returns
  - billing
  - domains
  - geography
  - support
  - audit
  - analytics
  - system health/readiness
  - production runtime safeguards
  - security headers

### Storefront

- Playwright specs موجودة وتغطي mocked e2e:
  - home/listing
  - SEO/crawl
  - mobile navigation
  - quick order
  - cart order
  - track order
- الحالة الحالية: غير مثبتة بسبب environment failure.
- يجب التمييز بين:
  - mocked e2e: موجود كاختبارات.
  - real integration e2e مع Laravel حقيقي: غير مثبت / مطلوب لاحقاً.

### المطلوب

1. `P0`: إصلاح Playwright dependencies وpnpm.
2. `P0`: CI يشغل backend + storefront build.
3. `مكتمل جزئياً`: checkout idempotency tests الأساسية موجودة داخل `QuickCheckoutTest`، واختبارات prune موجودة داخل `CheckoutIdempotencyPruneTest`.
4. `P1`: security tests للـ 2FA/session/rate limits عند إضافتها وتوسيع abuse analytics.
5. `مكتمل جزئياً`: production readiness smoke tests الأساسية موجودة:
   - health endpoint
   - readiness endpoint
   - `system:health` command
   - production `APP_DEBUG`/`APP_KEY` safeguard
   - security headers smoke
6. `P1`: queue worker/scheduler smoke أو runbook verification.
7. `P1`: migration smoke في CI.
8. `تشغيلي`: backup/restore drill ليس test آلياً بالضرورة لكنه مطلوب قبل production.

---

## 16. Architecture Decision Records

الحالة: مطلوب

يجب إنشاء `docs/adr/` لاحقاً كعمل مستقل. لم تتم إضافة ADR files بعد.

ADRs المطلوبة:

1. ADR: اختيار Modular Monolith بدلاً من Microservices.
2. ADR: اختيار shared database tenancy.
3. ADR: اختيار Laravel + Filament للـ backend.
4. ADR: اختيار Next.js storefront منفصل.
5. ADR: backend هو مصدر الحقيقة للأسعار والخصومات والشحن.
6. ADR: عدم الوثوق بتوتال الواجهة.
7. ADR: لماذا 69 wilayas ليست مفعلة الآن.
8. ADR: لماذا marketplace مؤجل.
9. ADR: strategy للدفع اليدوي ثم integrations لاحقاً.
10. ADR: strategy للشحن الجزائري.
11. ADR: storefront caching/revalidation strategy.
12. ADR: production deployment topology.

---

## 17. AI/Codex Development Rules

هذه قواعد إلزامية لأي AI agent يعمل على المشروع:

1. لا يضيف feature كبيرة دون تحديث tests مناسبة.
2. لا يغير tenancy logic دون اختبارات tenant isolation.
3. لا يضيف field مالي دون migration constraints واختبارات.
4. لا يغير checkout دون اختبارات totals وidempotency أو تفسير واضح إن كانت المرحلة لا تمس idempotency.
5. لا يضع business logic داخل Filament resources إذا كان يجب أن يكون في Actions/Support.
6. لا يلمس `.env` بأسرار فعلية.
7. لا يضيف package جديد دون سبب واضح وتوافق مثبت.
8. لا يغير architecture decision دون ADR.
9. كل sprint يجب أن ينتهي بتحديث roadmap.
10. كل تغيير يجب أن يمر quality gates المتاحة.
11. عند الشك، يصنف النقطة `غير مثبت / يحتاج تحقق` ولا يخمن.
12. أي `withoutGlobalScope('current_tenant')` جديد يجب أن يبرر ويفلتر tenant أو يكون admin context واضحاً.
13. لا تعتمد على client totals في أي flow.
14. لا تنفذ marketplace الآن.

---

## 18. Evaluation Scores

التقييم من 10 بناءً على حالة الكود والوثائق والتحقق بتاريخ 2026-05-06 والتحديثات التنفيذية بتاريخ 2026-05-07:

| المجال | الدرجة | سبب مختصر |
|---|---:|---|
| Architecture | 8.0 | modular monolith واضح، لكن ADRs غير موجودة. |
| Backend | 8.3 | domains واسعة واختبارات قوية. |
| Tenancy | 8.5 | طبقات متعددة وDB constraints، مع خطر `withoutGlobalScope`. |
| Database | 8.6 | schema قوي وقيود جيدة، وأضيفت idempotency records، لكن variants/stock movements ناقصة. |
| Checkout | 8.6 | server-side totals وtransactions وidempotency وduplicate window وrate limits والتنظيف المجدول موجودة؛ ينقص tuning/metrics/phone confirmation. |
| Storefront | 7.2 | أساس جيد ويرسل Idempotency-Key للcheckout، لكن caching/e2e reliability/image strategy ناقصة. |
| Security | 7.0 | isolation وcheckout abuse وsecurity headers وproduction debug/key safeguards موجودة، لكن 2FA/session/vulnerability scanning/CSP tightening ناقصة. |
| Testing | 8.5 | backend قوي وcheckout idempotency/prune/system health/security headers/production safeguards tests مضافة وCI workflow baseline موجود، لكن e2e غير مثبت وCI لم يعمل كـ gate فعلي بعد. |
| DevOps | 5.6 | docker-compose محلي وDockerfiles/runbook/health readiness/security headers/scheduler maintenance/production safeguards/CI baseline موجودة، لكن لا monitoring/backup ولا CI مثبت فعلياً. |
| Documentation | 7.2 | docs هندسية جيدة، runbooks وADRs ناقصة. |
| Product Readiness | 5.8 | foundation جيد، onboarding وUX التجاري ناقصان. |
| Production Readiness | 5.1 | Dockerfiles وenv production examples وrunbook وhealth/readiness/security headers وproduction safeguards وCI baseline موجودة، لكن deployment/backup/monitoring غير مكتملة. |
| AI-readiness | 7.0 | roadmap/workflow وCI baseline جيدة، لكن repo hygiene وgates الفعلية غير مكتملة. |

---

## 19. Long-Term Roadmap بعد P0

### P0 قبل beta عام

1. Phase 0.5 Repository Hygiene.
2. Production Readiness Foundation v1.
3. CI Quality Gates activation.
4. Checkout Idempotency tuning/metrics وليس foundation أو cleanup command.
5. Security baseline: secrets, staging/prod verification لـ APP_DEBUG/APP_KEY safeguards، CSP tightening، super admin 2FA.

### P1 بعد P0

1. Merchant Onboarding Foundation.
2. Storefront UX/Performance Polish.
3. Billing Revenue Operations v2.
4. Shipping Engine v2.
5. Notifications and Communication.
6. Product Variants and Catalog Maturity.
7. Support Center v2.

### P2

1. Advanced analytics.
2. Advanced SEO and structured data.
3. API v2 and integrations.
4. Performance/Octane/CDN readiness.

### P3 / لا ينفذ الآن

1. Marketplace mode.
2. 69 wilayas activation بدون mapping رسمي.
3. Multi-warehouse قبل stock movements وvariant inventory.

---

## 20. Backlog تفصيلي حسب الدومين

### Tenancy

- `P0 مستمر`: tests لكل مجال tenant-owned جديد.
- `P1`: stale session tenant cleanup.
- `P1`: audit report لـ `withoutGlobalScope`.

### Catalog

- `P1`: variants/options.
- `P1`: bulk import/export.
- `P1`: product SEO fields.
- `P1`: max images per product enforcement.

### Inventory

- `P1`: stock movements.
- `P1`: variant inventory.
- `P2`: low stock notifications.

### Checkout

- `مكتمل جزئياً قوي`: idempotency records وabuse protection foundation.
- `مكتمل`: command مجدول لتنظيف expired idempotency records، ويتبقى metrics/tuning.
- `P1`: rate-limit tuning وabuse dashboard.
- `P1`: phone confirmation workflow.
- `P2`: abandoned cart.

### Orders

- `P1`: customer-visible timeline.
- `P1`: batch printable slips.
- `P1`: export controls.

### Payments

- `P1`: payment proof upload/review.
- `P2`: payment integrations.
- `P2`: signed callbacks.

### Shipping

- `P1`: zones/free shipping/templates.
- `P1`: COD reconciliation.
- `P2`: provider integrations.

### Billing

- `P1`: invoice PDF.
- `P1`: revenue dashboard.
- `P1`: dunning.
- `P2`: ledger/accounting.

### Support

- `P1`: ticket messages/replies.
- `P1`: attachments.
- `P1`: SLA.

### Storefront

- `P1`: caching/revalidation.
- `P1`: empty/error/loading states.
- `P1`: mobile checkout polish.
- `P1`: accessibility pass.
- `P2`: image optimization strategy.

### Infrastructure

- `P0`: CI.
- `P0`: Dockerfiles build validation في CI.
- `P0`: health/readiness.
- `P0`: backup/restore.
- `P1`: monitoring/error tracking.

---

## 21. Definition of Done لأي Milestone

لا تعتبر أي مرحلة مكتملة إلا إذا تحقق التالي:

1. business logic في Action/Support مناسب.
2. input العام له validation/FormRequest.
3. tenant-owned models scoped أو لها استثناء موثق.
4. cross-tenant relations محمية بقيود DB عند الإمكان.
5. Filament resources محمية بسياسات.
6. status transitions لها tests.
7. money/inventory/order operations لا تثق بالواجهة.
8. الاختبارات المناسبة تمر.
9. route list لا يكشف routes غير مقصودة.
10. لا توجد secrets hardcoded.
11. الوثائق والroadmap محدثة.
12. أي أثر أمني أو تشغيلي أو ربحي مسجل.

---

## 22. أوامر التحقق القياسية

Backend:

```bash
cd backend
php artisan test
php artisan route:list
php artisan migrate:status
```

Storefront بعد إصلاح pnpm:

```bash
cd storefront
corepack enable
pnpm install
pnpm typecheck
pnpm build
pnpm test:e2e
```

بديل مؤقت إذا لم يتوفر pnpm:

```bash
cd storefront
npm run typecheck
npm run build
npx --yes pnpm@10.33.2 exec playwright test
```

ملاحظة: Playwright يحتاج system dependencies مثل `libnspr4.so` في البيئة الحالية.

---

## 23. سجل التحديثات

### 2026-05-07

- بدء تنفيذ Phase 0.5 بدلاً من الاكتفاء بتوثيقها كخطة.
- إضافة root `.gitignore` لحماية الحزمة/المساحة من ملفات البيئة والـ generated artifacts.
- تحديث `backend/.gitignore` و`storefront/.gitignore` لتجاهل `.env.*` مع إبقاء ملفات examples.
- تحديث `backend/.env.example` ليعكس خدمات Docker المحلية: PostgreSQL, Redis, Meilisearch, MinIO, Mailpit.
- إضافة `backend/.env.testing.example` لبيئة الاختبار.
- تحديث `storefront/.env.example` لتوثيق base URLs وfallback store identifiers.
- إضافة `docs/LOCAL_DEVELOPMENT.md` كعقد تشغيل محلي من clone نظيف.
- تحديث `docs/DEVELOPMENT_WORKFLOW.md` بقواعد pnpm وlocal setup وrepository hygiene.
- تحديث `docs/TESTING_STRATEGY.md` لتصحيح حالة Playwright الحالية وتوثيق متطلبات pnpm/system deps.
- تحديث `docs/SECURITY_BASELINE.md` بقواعد secrets والهندسة الحالية لملفات البيئة.
- تحديث حالة Phase 0.5 إلى `مكتمل جزئياً` وتحديد ما بقي لإغلاقها.
- تحديث Immediate Next Sprint ليغلق ما تبقى من Phase 0.5 قبل Production Readiness.
- بدء تنفيذ Production Readiness Foundation v1 كمرحلة P0.
- إضافة `backend/Dockerfile` كـ PHP-FPM production baseline.
- إضافة `storefront/Dockerfile` كـ Next.js production baseline.
- إضافة root/backend/storefront `.dockerignore`.
- إضافة `backend/.env.production.example` و`storefront/.env.production.example`.
- إضافة `backend/docker/php-production.ini`.
- إضافة `docs/PRODUCTION_READINESS.md` كـ runbook إنتاجي أولي.
- تحديث تقييم DevOps وProduction Readiness بعد إضافة Docker/runbook baseline.
- إضافة `.github/workflows/quality.yml` كـ CI Quality Gates baseline للـ backend/storefront/Dockerfile checks وE2E اختياري.
- تحديث حالة CI Quality Gates إلى `مكتمل جزئياً` لأن workflow موجود لكنه غير مثبت كـ merge gate فعلي بسبب وضع المستودع.
- تحديث تقييم Testing وDevOps وProduction Readiness وAI-readiness بعد إضافة CI baseline.
- تنفيذ Checkout Idempotency and Abuse Protection foundation.
- إضافة جدول `checkout_idempotency_records` وموديل/خدمات idempotency وabuse guard.
- تمرير `Idempotency-Key` من Storefront Next.js إلى Laravel checkout.
- إضافة اختبارات عدم إنشاء طلبين لنفس key، conflict عند payload مختلف، tenant/store isolation، وduplicate window.
- تسجيل تحقق وسيط بعد checkout idempotency قبل إضافات health/security اللاحقة.
- تحديث تقييم Database وCheckout وStorefront وSecurity وTesting بعد تنفيذ checkout idempotency.
- تنفيذ Health/Readiness Foundation للbackend.
- إضافة `SystemHealthChecker` وendpoints للـ liveness/readiness.
- إضافة أمر `system:health` بصيغة JSON/table.
- إضافة readiness smoke إلى CI baseline وbackend Docker healthcheck.
- تحديث تقييم DevOps وProduction Readiness بعد إضافة health/readiness foundation.
- تنفيذ Security Headers baseline للbackend والstorefront.
- إضافة middleware للـ Laravel يضبط headers الأساسية وHSTS عند HTTPS.
- إضافة headers في `storefront/next.config.ts`.
- إضافة اختبارات security headers smoke.
- تسجيل تحقق backend بعد security headers baseline قبل إضافة prune command.
- تحديث تقييم Security وTesting وDevOps وProduction Readiness بعد security headers baseline.
- تنفيذ تنظيف سجلات checkout idempotency المنتهية عبر `checkout-idempotency:prune`.
- جدولة أمر التنظيف يومياً عند 03:00.
- إضافة اختبارات prune وdry-run.
- تحديث تحقق backend بعد prune command.
- تحديث تقييم Checkout وTesting وDevOps بعد إغلاق cleanup command.
- إضافة production runtime safeguards إلى readiness: فشل عند `APP_DEBUG=true` أو غياب `APP_KEY` في production.
- إضافة اختبارات لهذه الحالات داخل `SystemHealthTest`.
- تحديث آخر تحقق backend إلى `149 passed (608 assertions)`.
- تحديث تقييم Security وTesting وDevOps وProduction Readiness بعد production safeguards.

### 2026-05-06

- تم تحديث الوثيقة بناءً على تحليل Codex العميق للمشروع والكود الفعلي.
- تم تصحيح التناقضات في أرقام الاختبارات وحالة Playwright e2e.
- تم تصحيح حالة Algerian Geography إلى `58 active wilayas` و`1541 active communes`.
- تم إضافة Phase 0.5: Repository Hygiene, Secrets, and Local Dev Reliability.
- تم إعادة ترتيب السبرنتات بحيث لا يبدأ Merchant Onboarding قبل hygiene/production/CI/idempotency.
- تم إضافة Checkout Idempotency and Abuse Protection كمرحلة P0 قبل beta عام.
- تم توسيع Production Readiness لتشمل Dockerfiles، reverse proxy، environments، health/readiness، queues، scheduler، backups، storage، Redis، Meilisearch، migration procedure.
- تم إضافة CI Quality Gates مع backend/frontend/e2e jobs.
- تم تحديث Security Roadmap إلى موجود/ناقص/قبل beta/قبل production.
- تم تحديث Tenancy Roadmap وقاعدة `withoutGlobalScope('current_tenant')`.
- تم تحديث Storefront Roadmap مع caching/revalidation وmocked vs real e2e.
- تم تحديث Billing Roadmap وShipping Roadmap بناءً على الموجود فعلياً.
- تم تحديث Testing Strategy بنتائج 2026-05-06.
- تم إضافة قسم Architecture Decision Records المقترح.
- تم تحديث قواعد العمل مع Codex/AI agents.
- تم تحديث Evaluation Scores بناءً على حالة production/devops الحقيقية.

### 2026-04-28

- تحويل الوثيقة إلى Living Roadmap وExecution Contract.
- تنفيذ baseline documentation: architecture, tenancy, workflow, testing, security.
- تنفيذ Tenant Switcher v1.
- تنفيذ Algerian Geography v1.
- تنفيذ Storefront Cart + Checkout foundation.
- تنفيذ Storefront SEO/Crawl foundation.
- تنفيذ Theme Sections v1.
- تثبيت نتائج تاريخية للbackend والواجهة في ذلك التاريخ.

---

## 24. الخلاصة النهائية

المشروع يملك foundation هندسية جيدة جداً، خصوصاً في backend وtenancy والاختبارات الخلفية. أضيف أساس checkout idempotency/abuse protection، لكنه ليس جاهزاً للإنتاج ولا beta عام قبل إغلاق فجوات P0 المتبقية: repository hygiene، local dev reliability، CI الفعلي، production readiness، وsecurity hardening.

المسار الصحيح الآن هو تقوية خط الإنتاج والتشغيل قبل إضافة ميزات تجارية كبيرة. بعد ذلك يصبح Merchant Onboarding أعلى مرحلة منتجية قيمة لأنه يربط backend القوي بتجربة تاجر قابلة للبيع.
