# التحليل العميق وخارطة الطريق الاستراتيجية لمنصة dz-saas-commerce

آخر تحديث: 2026-05-19

نوع الوثيقة: المرجع الاستراتيجي الأعلى لحالة المشروع، عقوده التنفيذية العامة، مستوى الجاهزية، وخارطة الطريق القريبة.

قاعدة قراءة مهمة: هذه الوثيقة تحليل هندسي داخلي وليست valuation ولا ضمان إطلاق. أي نسبة اكتمال هنا تقديرية ومحافظة. real staging الخارجي لم ينفذ بعد، ولا يجوز تحويل runbooks أو smoke مؤقت إلى ادعاء production readiness.

مصادر هذه النسخة:

- قراءة ملفات `backend/`, `storefront/`, `docs/`, `.github/workflows/`, و`deploy/`.
- مطابقة الوثائق مع الكود الحالي بتاريخ 2026-05-19.
- عدم تشغيل full backend/storefront suites في هذه الجولة لأنها docs-only ولم تغير التطبيق.
- آخر baseline موثق سابقاً لاختبارات كاملة بقي مرجعاً تاريخياً فقط ما لم يعاد تشغيله.

---

## 1. Executive Summary

`dz-saas-commerce` أصبح monorepo متقدماً لبناء منصة SaaS تجارة إلكترونية متعددة المستأجرين للسوق الجزائري. النواة التجارية ليست مجرد storefront؛ المشروع يحتوي backend Laravel/Filament، storefront Next.js، tenancy، كتالوج، variants/options، checkout/COD، idempotency، مخزون وstock movement ledger، orders، shipments، returns/refunds، coupons، subscriptions/billing، audit، support، 2FA، وطبقة store readiness.

أقوى جزء حالياً هو backend domain model: كثير من السلوك الحساس موضوع في Actions، مع policies، tenant scopes، composite constraints، وtests تغطي checkout/inventory/returns/security/readiness/variants. الواجهة أصبحت أكثر من proof-of-concept: تدعم product listing/detail، cart، quick checkout، track order، SEO/crawl routes، وvariant picker يرسل `product_variant_id`.

التشغيل ما زال غير مكتمل. توجد Dockerfiles، CI quality gates، image publish workflow، staging smoke skeleton، health/readiness، runbooks للـ staging/backup/reverse-proxy/queue/monitoring، لكن real staging الخارجي لم ينفذ بسبب انتظار VPS/provider وdomain/hostname وقيم staging الحقيقية. لذلك المشروع pre-production قوي، وليس production-ready.

النتيجة العملية: المشروع مناسب لمواصلة البناء المنظم، لكنه لا يصلح لإطلاق production أو بيع white-label ناضج قبل إثبات staging حقيقي، monitoring/observability، restore drill، hardening، وصقل UX/ops.

---

## 2. Current Architecture

### 2.1 Monorepo

- `backend/`: Laravel 13, PHP 8.3, Filament 5.6, REST API, domain Actions, policies, migrations, tests.
- `storefront/`: Next.js 15.5.18, React 19, TypeScript, Tailwind, Playwright e2e.
- `docs/`: وثائق معمارية وتشغيلية وADRs وعقود دومين.
- `deploy/`: staging compose/smoke, reverse proxy, backup, systemd supervision examples.
- `.github/workflows/`: quality gates, container image publish, manual staging smoke.
- `docker-compose.yml`: خدمات محلية PostgreSQL, Redis, Meilisearch, MinIO, Mailpit.

هذا الشكل مناسب الآن. لا توجد حاجة لـ microservices قبل ضغط تشغيلي حقيقي.

### 2.2 Backend

الـ backend هو مصدر الحقيقة للأسعار، الشحن، الخصومات، المخزون، الطلبات، الدفع، الاشتراكات، والصلاحيات.

مكونات أساسية:

- Tenancy: `CurrentTenant`, `TenantResolver`, `ResolveTenantFromRequest`, `BelongsToTenant`.
- Public API: `backend/routes/api.php`.
- Web routes: invitation acceptance, vendor order slip, tenant switch.
- Console routes: billing lifecycle, checkout idempotency prune, emergency 2FA reset, system health.
- Domain logic: `app/Actions`, `app/Support`, policies, observers.
- Health/readiness: HTTP endpoints + `php artisan system:health`.
- Search/storage/cache/queues: Scout/Meilisearch, S3-compatible storage support, Redis cache/session/queue, Laravel scheduler.

### 2.3 Filament Panels

- `admin`: platform super admin.
- `support`: super admin/platform support.
- `vendor`: tenant users, tenant switcher, catalog, variants/options, inventory, orders, returns, billing, settings.

2FA مطلوب للـ super admin/platform support، ولـ tenant owner داخل vendor عندما يوجد tenant context.

### 2.4 Storefront

الـ storefront يعرض المتجر ولا يقرر حقائق مالية أو تشغيلية. الموجود:

- home/products/categories/search.
- product detail مع variants/options picker للمنتجات `variable`.
- cart scoped لكل store في `localStorage`.
- quick checkout وcart checkout عبر route proxy.
- track order.
- legal pages.
- metadata, OpenGraph, robots, sitemap, JSON-LD.

الفجوات: UX polish، pagination/filter UI، accessibility، caching/revalidation، real integration e2e ضد Laravel live backend.

### 2.5 API Boundaries

Public API الحالي REST-first:

- `GET /api/storefront/resolve`
- `GET /api/storefront/{store}/home`
- `GET /api/storefront/{store}/products`
- `GET /api/storefront/{store}/products/{slug}`
- `GET /api/storefront/{store}/categories`
- `GET /api/storefront/{store}/search`
- `POST /api/storefront/{store}/checkout`
- `GET /api/storefront/{store}/track-order`
- geography endpoints للولايات والبلديات.

الحد المهم: storefront يرسل identifiers والكمية وبيانات العميل فقط؛ Laravel يعيد التحقق من كل شيء.

---

## 3. Domain Modules

### 3.1 Tenants And Stores

Tenants يملكون stores وmembership وplans. `Store` استثناء موثق من `BelongsToTenant` لأنه يدخل في domain resolution والplatform flows، لذلك أي query عليه يحتاج مراجعة tenant-aware. public store resource لا يكشف `tenant_id`.

Store readiness موجودة عبر `StoreReadinessChecker`:

- store must target active.
- tenant must be active أو trial.
- subdomain/store settings/theme/payment/shipping مطلوبة.
- يجب وجود منتج sellable واحد على الأقل.
- custom domain وTLS وreal deployment ليست جزءاً من readiness gate الحالي.

### 3.2 Catalog

موجود:

- categories/products/images/search.
- `ProductType`: `simple` و`variable`.
- `ProductOption`, `ProductOptionValue`, `ProductVariant`, pivot.
- Vendor Filament resources لإدارة options/values/variants/pivot.
- validation يمنع ربط option value بvariant من منتج آخر داخل نفس tenant.

ما زال ناقصاً:

- توليد أوتوماتيكي للتواقيع/options UX.
- filters/import/export/product SEO fields.
- UX polish للـ variants.

### 3.3 Products, Variants, Options

السلسلة الحالية مكتملة وظيفياً بالحد الأول:

- schema foundation للجداول والأعمدة.
- models/factories/relationships.
- vendor variant management.
- checkout `product_variant_id`.
- variant inventory uniqueness.
- lifecycle propagation.
- storefront product detail serialization.
- storefront variant picker.
- `products.type` enforcement.

قواعد مهمة:

- `simple` يشترى بدون variant.
- `variable` يتطلب `product_variant_id`.
- checkout يرفض variant على simple product.
- storefront picker UX مساعد فقط؛ backend يعيد التحقق دائماً.

### 3.4 Inventory And Stock Movements

موجود:

- `InventoryItem` لكل sellable unit.
- simple inventory: `product_variant_id IS NULL`.
- variant inventory: `product_variant_id IS NOT NULL`.
- partial unique indexes تمنع duplicate inventory rows.
- reservation داخل checkout transaction.
- lifecycle actions:
  - `reserved`
  - `released`
  - `settled`
  - `restocked`
- manual adjustment action:
  - `manual_adjustment`
  - `correction`
  - `AuditLog` event `inventory_manual_adjustment`.

ملاحظة دقيقة: lifecycle flows التي تنطلق من order/order item تسجل `product_variant_id` عند وجوده. manual adjustment يسجل المخزون وAuditLog من `InventoryItem`، ولا يجب توثيق سلوك variant-reporting له بما يتجاوز الكود الحالي.

### 3.5 Checkout And COD

موجود:

- quick checkout وcart checkout.
- COD payment method.
- server-side totals.
- shipping rate lookup by wilaya/commune/delivery type.
- coupon calculation.
- inventory reservation.
- `Idempotency-Key`.
- duplicate-window fallback عند غياب المفتاح.
- abuse guard by IP/phone/store.
- variant checkout support.
- order item snapshots تشمل variant title/SKU/options عند وجود variant.

ناقص:

- COD reconciliation foundation.
- metrics/observability للـ abuse/idempotency/rate limits.
- real integration e2e ضد backend live.

### 3.6 Orders, Shipments, Returns, Refunds

موجود:

- order status transitions and histories.
- payment status workflows.
- shipments and shipment status histories.
- failed delivery reasons.
- returns workflow.
- refund/restock/settlement/release actions مع stock movements.

ناقص:

- COD reconciliation.
- richer order timeline.
- batch shipment operations.
- export/reporting.

### 3.7 Coupons

موجود كأساس:

- coupon domain.
- feature gate by plan.
- redemption during checkout.

ناقص:

- analytics and campaign management.
- operational fraud metrics.

### 3.8 Subscriptions And Billing

موجود:

- plans/features.
- tenant subscriptions.
- invoices.
- manual subscription payments.
- billing lifecycle command/job.
- subscription payment confirm/reject.

ناقص:

- invoice PDF.
- dunning كامل.
- payment proof upload/review lifecycle كامل.
- revenue dashboard وMRR/ARR.

### 3.9 Store Readiness

`StoreReadinessChecker` أصبح contract مهم قبل أي publish flow:

- structured `ready/errors/warnings`.
- `assertReady`.
- `assertProductReady`.
- simple and variable product inventory rules.
- stable error codes.

لكنه لا يثبت staging أو production. هو domain validation فقط.

---

## 4. Security State

موجود:

- Filament panel access controls.
- platform roles وtenant roles/permissions.
- policies registered.
- tenant scopes/database constraints.
- public API throttles.
- checkout idempotency and abuse guard.
- no public `tenant_id` in storefront store resource.
- secret hygiene scripts and CI baseline.
- security headers middleware and Next headers.
- production readiness safeguards for `APP_DEBUG`/`APP_KEY`.
- encrypted 2FA fields and recovery codes.
- 2FA challenge for sensitive panels.
- emergency `security:reset-two-factor` with required reason and audit.
- AuditLog foundation and immutable policy.

مهم:

- emergency reset لا يلغي الجلسات النشطة حالياً.
- CSP واسع عمداً حتى تتم validation حقيقية في staging/browser.
- لا يوجد session/device management كامل.
- لا يوجد secret rotation procedure كامل.
- لا يوجد production error tracking أو alert routing.
- real staging secrets غير مكونة داخل المستودع، ويجب ألا تضاف له.

---

## 5. Data Integrity

نقاط قوة:

- tenant-scoped FKs وcomposite constraints في علاقات حساسة.
- partial unique indexes للـ simple/variant inventory.
- product variant tenant integrity.
- checkout idempotency request hash.
- stock movement ledger append-oriented.
- server-side recalculation للمال والمخزون.
- readiness rules تمنع نشر store/product غير قابل للبيع.

قيود حالية:

- ليست كل invariants قابلة للتعبير في DB constraints، خصوصاً علاقة `product.type` مع `inventory_items.product_variant_id`.
- `Store` exception يحتاج audit مستمر لأي query جديد.
- duplicate-window fallback بدون idempotency key هو best-effort.
- manual inventory adjustment ليس UI/API مكتمل بعد.

---

## 6. Operations State

موجود:

- Dockerfiles للbackend/storefront.
- GHCR publish workflow.
- Trivy image scan policy.
- `.github/workflows/quality.yml`.
- `.github/workflows/staging-smoke.yml`.
- `deploy/staging/staging-smoke.sh`.
- ephemeral staging smoke path.
- health/readiness HTTP endpoints.
- queue/scheduler runbooks.
- backup/restore runbooks and examples.
- reverse proxy runbook.
- monitoring baseline docs.

ما هو جاهز:

- contract and scripts for staging smoke.
- local/ephemeral proof path documented.
- image references documented historically.

ما هو pending:

- VPS/provider or hosting platform.
- domain/temporary hostname.
- staging environment secrets/variables.
- external staging smoke through real URL.
- TLS/custom domain validation.
- restore drill.
- monitoring/alerting provider and routing.
- centralized logging and error tracking.

لا يوجد دليل على تنفيذ staging خارجي حقيقي.

---

## 7. Testing State

Backend coverage strengths:

- tenancy and tenant integrity.
- checkout idempotency, abuse-related duplicate guards, coupons, product variants.
- inventory ledger and lifecycle propagation.
- manual inventory adjustment.
- returns/restock/settlement/release.
- 2FA and emergency reset.
- store readiness.
- storefront API variant serialization.
- security headers and trusted proxy.

Storefront coverage strengths:

- home/listing/crawl routes.
- product variant picker.
- mobile navigation.
- quick checkout.
- simple product checkout without variant id.
- cart checkout item payloads.
- track order.

Coverage gaps:

- real integration e2e against live Laravel backend.
- production-like staging smoke not executed externally.
- monitoring/alert tests not integrated with a provider.
- performance/load budgets.
- accessibility audit.
- full dashboard e2e for merchant/admin workflows.

هذه الجولة لم تشغل full suites لأنها docs-only ولم تغير التطبيق.

---

## 8. Product And Commercial Readiness

### 8.1 Valuable Now

- Backend commerce engine قوي بما يكفي ليكون أساس SaaS.
- Multi-tenant architecture واضحة.
- COD checkout مناسب للسوق الجزائري كبداية.
- Variants/product type/inventory chain أصبحت حقيقية.
- Storefront usable وليس مجرد placeholder.
- Security/ops docs أفضل من كثير من المشاريع المبكرة.

### 8.2 Missing Before Real Launch

- real staging execution after VPS/domain.
- production hardening review.
- monitoring/observability.
- restore drill and backup proof.
- COD reconciliation.
- operational playbooks tested by a human.

### 8.3 Missing Before White-label Sale

- onboarding and readiness UX.
- tenant/admin dashboards more polished.
- variant UX polish and import/export.
- billing/revenue operations.
- support attachments/messages/SLA.
- deployment/upgrade/backup proof that can be explained to a buyer.

### 8.4 Missing Before Investor/Demo Readiness

- stable demo environment with seeded data.
- real staging smoke proof and screenshots.
- concise product narrative and dashboard demos.
- monitoring dashboard proof.
- documented remaining risks without pretending production is done.

---

## 9. Completed Milestones

- root monorepo with backend/storefront/docs/deploy.
- ADR set under `docs/adr`.
- multi-tenant Laravel backend.
- Filament panels: admin/support/vendor.
- storefront Next.js with cart/checkout/track-order/SEO.
- checkout/COD server-authoritative flow.
- checkout idempotency and duplicate fallback.
- stock movement ledger foundation.
- reservation/release/settlement/restock stock movements.
- manual inventory adjustment action with AuditLog.
- 2FA for sensitive Filament panels.
- emergency 2FA reset artisan command with audit.
- product variants/options ADR and implementation chain.
- vendor variant management.
- variant checkout support.
- variant inventory uniqueness.
- variant lifecycle propagation.
- storefront product detail variant serialization.
- storefront variant picker UI.
- `ProductType` simple/variable enforcement.
- store readiness validation checker.
- staging deployment runbook/checklist/smoke proof template.
- CI and image publish workflows documented.

---

## 10. Pending Milestones

- real staging environment after VPS/domain.
- COD reconciliation foundation.
- production hardening review.
- monitoring/observability foundation.
- variant UX polish.
- restore drill execution.
- backup schedule deployment.
- white-label packaging and demo readiness.

---

## 11. Readiness Estimates

هذه أرقام تقديرية هندسية داخلية، وليست valuation أو ضمان إطلاق.

| المجال | التقدير | السبب |
|---|---:|---|
| Core commerce engine | 72% | checkout/orders/payments/coupons/shipping/returns موجودة، لكن COD reconciliation وops metrics ناقصة. |
| Catalog/variants | 78% | variants chain مكتملة وظيفياً، لكن UX polish/import/export/filters ناقصة. |
| Checkout/order lifecycle | 76% | idempotency/reservation/status/payment جيدة، لكن reconciliation/observability/concurrency hardening أعمق ناقصة. |
| Inventory/ledger | 74% | ledger وحركات lifecycle قوية، لكن UI/API للتعديل اليدوي والتنبيهات والتقارير ناقصة. |
| Security/admin readiness | 66% | policies/2FA/audit/headers جيدة، لكن sessions/CSP/rotation/error tracking تحتاج hardening. |
| Storefront | 65% | storefront usable مع variants/cart/SEO، لكن UX/a11y/caching/integration e2e ناقصة. |
| Ops/staging | 48% | runbooks وscripts موجودة، لكن real staging proof وrestore/monitoring غير منفذة. |
| Production readiness | 42% | لا production launch قبل staging proof وmonitoring وhardening وrestore drill. |
| Overall completion | 63% | foundation التجاري قوي، لكن التشغيل والجاهزية التجارية لا تزال مرحلة pre-production. |

---

## 12. Current Blockers

### يمنع production launch

- real staging لم ينفذ بعد.
- لا يوجد proof خارجي لـ TLS/custom domains/reverse proxy.
- لا restore drill مسجل.
- لا monitoring/alert routing/error tracking فعلي.
- لا production hardening review مكتمل.
- COD reconciliation غير موجود.

### يمنع white-label sale

- نقص التشغيل المثبت والrunbook evidence.
- onboarding/publish UX غير مكتمل.
- variant UX يحتاج صقلاً.
- billing/revenue operations غير ناضجة.
- لا package واضح للنسخ/الترقية/الدعم.

### يمنع investor/demo readiness

- لا demo/staging URL ثابت مثبت بالدليل.
- لا smoke proof حقيقي مملوء.
- لا monitoring proof.
- لا سرد demo مختصر يفرق بين المنجز والpending.

---

## 13. Risks

- الخلط بين runbook وdeployment proof.
- توسيع features قبل real staging.
- `Store` exception يسبب query غير tenant-safe إذا لم يراجع.
- duplicate-window fallback لا يكفي وحده للعمليات عالية الحساسية.
- CSP واسع حتى يثبت في staging/browser.
- lack of observability يخفي فشل queues/billing/checkout.
- backend/README ما زال scaffold Laravel وليس مرجع المشروع؛ المرجع الحالي هو root `README.md` و`docs/`.

---

## 14. Recommended Next Tasks

1. COD reconciliation foundation.
2. Real staging execution after VPS/domain.
3. Production hardening review.
4. Monitoring/observability foundation.
5. Product variant UX polish.

---

## 15. Documentation Rules

- أي تغيير يمس checkout, inventory, billing, tenancy, security, deploy, CI, أو storefront contracts يجب أن يحدث الوثائق المتخصصة.
- لا تنقل منطق المال أو المخزون إلى storefront.
- لا تعتبر staging جاهزاً إلا بعد proof خارجي محفوظ.
- لا تضف أسراراً إلى docs أو examples.
- لا تغير business logic في جولات docs-only.
