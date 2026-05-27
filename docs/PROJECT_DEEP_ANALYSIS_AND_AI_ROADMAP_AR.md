# التحليل العميق وخارطة الطريق الاستراتيجية لمنصة dz-saas-commerce

آخر تحديث: 2026-05-27

نوع الوثيقة: المرجع الاستراتيجي الأعلى لحالة المشروع، عقوده التنفيذية العامة، مستوى الجاهزية، وخارطة الطريق القريبة.

قاعدة قراءة مهمة: هذه الوثيقة تحليل هندسي داخلي وليست valuation ولا ضمان إطلاق. أي نسبة اكتمال هنا تقديرية ومحافظة. real staging الخارجي أصبح مثبتاً جزئياً لـ HTTPS/Caddy/nginx/2FA/demo storefront، لكن لا يجوز تحويل ذلك إلى ادعاء production readiness.

مصادر هذه النسخة:

- قراءة ملفات `backend/`, `storefront/`, `docs/`, `.github/workflows/`, و`deploy/`.
- مطابقة الوثائق مع الحالة التشغيلية الموثقة حتى 2026-05-27.
- proof خارجي محفوظ في `docs/STAGING_SMOKE_PROOF_2026-05-26_AR.md`.
- نتائج التحقق المحلية لإصلاح 2FA: Composer audit، Pint، focused 2FA tests، وfull backend suite.
- Storefront e2e/Docker baseline بقي مرجعاً تاريخياً من 2026-05-12 ما لم يعاد تشغيله.

---

## 1. Executive Summary

`dz-saas-commerce` أصبح monorepo متقدماً لبناء منصة SaaS تجارة إلكترونية متعددة المستأجرين للسوق الجزائري. النواة التجارية ليست مجرد storefront؛ المشروع يحتوي backend Laravel/Filament، storefront Next.js، tenancy، كتالوج، variants/options، checkout/COD، idempotency، مخزون وstock movement ledger، orders، shipments، returns/refunds، coupons، subscriptions/billing، audit، support، 2FA، وطبقة store readiness.

أقوى جزء حالياً هو backend domain model: كثير من السلوك الحساس موضوع في Actions، مع policies، tenant scopes، composite constraints، وtests تغطي checkout/inventory/returns/security/readiness/variants. الواجهة أصبحت أكثر من proof-of-concept: تدعم product listing/detail، cart، quick checkout، track order، SEO/crawl routes، وvariant picker يرسل `product_variant_id`.

التشغيل تحسن عملياً لكنه ما زال غير مكتمل. توجد Dockerfiles، CI quality gates، image publish workflow، health/readiness، runbooks للـ staging/backup/reverse-proxy/queue/monitoring، وتم إثبات staging خارجي على DigitalOcean لـ mayfairs.app مع HTTPS، Caddy أمام nginx edge داخلي، 2FA setup/challenge، وdemo storefront. مع ذلك لا توجد بعد backup automation، restore drill، monitoring/alerting، log aggregation، أو rollback proof، لذلك المشروع pre-production قوي وليس production-ready.

النتيجة العملية: المشروع مناسب لمواصلة البناء المنظم وعرض demo staging مضبوط، لكنه لا يصلح لإطلاق production أو بيع white-label ناضج قبل backup/restore drill، monitoring/observability، rollback proof، hardening، وصقل UX/ops.

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
- 2FA setup/challenge for sensitive panels، وتم إثبات إصلاح setup/challenge على staging بعد commit `045c264`.
- emergency `security:reset-two-factor` with required reason and audit.
- AuditLog foundation and immutable policy.

مهم:

- emergency reset لا يلغي الجلسات النشطة حالياً.
- CSP واسع عمداً وفيه allowances permissive مثل `unsafe-inline`/`unsafe-eval` حتى تتم browser/e2e validation وتضييقه لاحقاً.
- لا يوجد session/device management كامل.
- لا يوجد secret rotation procedure كامل.
- لا يوجد production error tracking أو alert routing.
- staging secrets موجودة خارج المستودع ويجب ألا تضاف له.

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
- external staging foundation on DigitalOcean for mayfairs.app.
- Caddy public TLS -> `127.0.0.1:8080` -> nginx edge topology.
- HTTPS checks for mayfairs.app/api/admin.
- Filament/Livewire HTTPS assets smoke.
- mandatory 2FA setup/challenge smoke after the fix.
- staging demo storefront with COD, shipping rates, products, and inventory.

ما هو pending:

- backup automation deployment.
- restore drill evidence.
- monitoring/alerting provider and routing.
- centralized logging and error tracking.
- release rollback proof.
- Cloudflare Proxied decision/smoke.
- custom domains/TLS automation beyond primary mayfairs.app domains.

يوجد دليل على staging خارجي جزئي، لكنه لا يغطي backup/restore أو monitoring أو production hardening.

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
- production-like staging smoke now exists for HTTPS/2FA/demo storefront, but broader release/rollback/monitoring/restore proof is still missing.
- monitoring/alert tests not integrated with a provider.
- performance/load budgets.
- accessibility audit.
- full dashboard e2e for merchant/admin workflows.

التحقق المحلي المسجل لإصلاح 2FA شمل `composer audit --no-interaction`, `php vendor/bin/pint --test`, focused 2FA tests (`24 passed`, `136 assertions`)، وfull backend suite (`292 passed`, `1448 assertions`). هذا لا يستبدل storefront e2e جديداً أو monitoring/restore proof.

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

- production hardening review.
- monitoring/observability.
- restore drill and backup proof.
- release rollback proof.
- Cloudflare Proxied decision or documented DNS-only operating choice.
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

- richer demo script and sanitized screenshots around the now-working staging demo.
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
- 2FA staging setup/challenge fix deployed and verified on mayfairs staging.
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
- external staging proof for HTTPS, Caddy/nginx routing, 2FA, and demo storefront.
- CI and image publish workflows documented.

---

## 10. Pending Milestones

- COD reconciliation foundation.
- production hardening review.
- monitoring/observability foundation.
- variant UX polish.
- restore drill execution.
- backup schedule deployment.
- release rollback proof.
- Cloudflare Proxied smoke/decision.
- custom domains/TLS automation design.
- white-label packaging beyond staging demo readiness.

---

## 11. Readiness Estimates

هذه أرقام تقديرية هندسية داخلية، وليست valuation أو ضمان إطلاق.

| المجال | التقدير | السبب |
|---|---:|---|
| Core commerce engine | 72% | checkout/orders/payments/coupons/shipping/returns موجودة، لكن COD reconciliation وops metrics ناقصة. |
| Catalog/variants | 78% | variants chain مكتملة وظيفياً، لكن UX polish/import/export/filters ناقصة. |
| Checkout/order lifecycle | 76% | idempotency/reservation/status/payment جيدة، لكن reconciliation/observability/concurrency hardening أعمق ناقصة. |
| Inventory/ledger | 74% | ledger وحركات lifecycle قوية، لكن UI/API للتعديل اليدوي والتنبيهات والتقارير ناقصة. |
| Security/admin readiness | 70% | policies/2FA/audit/headers جيدة، و2FA مثبت على staging، لكن session/device management وCSP/rotation/error tracking تحتاج hardening. |
| Storefront | 65% | storefront usable مع variants/cart/SEO، لكن UX/a11y/caching/integration e2e ناقصة. |
| Ops/staging | 58% | real staging foundation مثبت لـ HTTPS/2FA/demo، لكن backup/restore/monitoring/rollback وCloudflare Proxied غير منفذة. |
| Production readiness | 45% | لا production launch قبل backup/restore، monitoring، rollback proof، hardening، وCloudflare/custom-domain decisions. |
| Overall completion | 65% | foundation التجاري قوي وdemo staging أصبح حقيقياً، لكن التشغيل والجاهزية التجارية لا تزال مرحلة pre-production. |

---

## 12. Current Blockers

### يمنع production launch

- staging proof الحالي محدود بـ HTTPS/Caddy/nginx/2FA/demo store ولا يغطي production gates.
- لا restore drill مسجل.
- لا monitoring/alert routing/error tracking فعلي.
- لا backup automation مثبتة.
- لا rollback proof مسجل.
- لا production hardening review مكتمل.
- COD reconciliation غير موجود.

### يمنع white-label sale

- نقص التشغيل المثبت والrunbook evidence.
- onboarding/publish UX غير مكتمل.
- variant UX يحتاج صقلاً.
- billing/revenue operations غير ناضجة.
- لا package واضح للنسخ/الترقية/الدعم.

### يمنع investor/demo readiness

- demo/staging URL موجود ومثبت بالدليل، لكن يحتاج demo script وسرد مختصر وصور/لقطات sanitized.
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

1. Backup/restore drill على staging وتوثيق RPO/RTO.
2. Monitoring/alerting baseline مع uptime/readiness/failed-jobs/TLS/backup-age.
3. Release rollback proof على staging.
4. Cloudflare Proxied smoke/decision.
5. Custom domains/TLS automation design beyond primary mayfairs.app domains.

---

## 15. Documentation Rules

- أي تغيير يمس checkout, inventory, billing, tenancy, security, deploy, CI, أو storefront contracts يجب أن يحدث الوثائق المتخصصة.
- لا تنقل منطق المال أو المخزون إلى storefront.
- لا تعتبر أي staging gate جديداً جاهزاً إلا بعد proof خارجي محفوظ ومحدث.
- لا تضف أسراراً إلى docs أو examples.
- لا تغير business logic في جولات docs-only.
