# قواعد مهام Codex

آخر تحديث: 2026-05-19

هذا الملف مرجع دائم قبل إعطاء أي prompt لاحق لـ Codex داخل `dz-saas-commerce`.

## قواعد عامة

- نفذ مهمة واحدة صغيرة في كل مرة.
- لا تحول المشروع إلى microservices.
- لا تبن marketplace الآن.
- لا تغيّر معمارية واسعة بدون ADR.
- لا تعدل checkout أو inventory أو billing أو tenancy بدون tests مناسبة.
- لا تستخدم `withoutGlobalScope('current_tenant')` إلا مع guard صريح بـ `tenant_id` أو `store_id` واختبار عند الحاجة.
- لا تضع منطقاً مالياً موثوقاً في storefront؛ backend هو مصدر الحقيقة للأسعار والخصومات والشحن والمخزون والtotals.
- لا تضع secrets في الكود أو الوثائق أو أمثلة قابلة للنسخ.
- لا تضف dependencies جديدة إلا إذا كانت ضرورية ومبررة.
- لا تستخدم skip/todo لإخفاء فشل tests.
- لا تخلط refactor واسع مع feature صغيرة.
- في جولات docs-only لا تغيّر `backend/app`, `backend/database`, routes, `storefront/src`, tests, migrations, dependencies, deploy scripts, أو CI.

## قالب مهمة Codex

### العنوان

اسم قصير يصف التغيير.

### الهدف

ما المشكلة التي نغلقها؟ ما النتيجة المتوقعة؟

### النطاق

ما الذي يدخل في هذه المهمة؟ ما الذي لا يدخل؟

### الملفات المسموح لمسها

- ضع قائمة ملفات أو مجلدات محددة.

### الملفات الممنوع لمسها

- ضع قائمة الملفات الحساسة أو غير المرتبطة.

### الخطوات

1. اقرأ الوثائق والكود المرتبط.
2. اعرض ملخصاً قصيراً قبل التعديل.
3. نفذ تغييراً صغيراً قابلاً للمراجعة.
4. أضف tests أو docs حسب المخاطر.
5. شغل أوامر التحقق.

### أوامر التحقق

اكتب الأوامر الرسمية فقط، مثل:

```bash
cd backend
php artisan test
vendor/bin/pint --test

cd ../storefront
corepack enable
corepack prepare pnpm@11.1.2 --activate
pnpm install --frozen-lockfile
pnpm audit --audit-level moderate
pnpm typecheck
pnpm build
pnpm test:e2e
```

### معيار القبول

- السلوك المطلوب مثبت.
- tests أو docs تغطي التغيير.
- لا يوجد كسر في tenant isolation.
- لا توجد تغييرات غير مرتبطة.
- لا توجد أسرار.

### مخاطر rollback

اذكر ما الذي يمكن إرجاعه بسهولة، وما الذي يحتاج migration أو data repair.

## Completed Foundation

- إصلاح موثوقية Storefront E2E وتوحيد pnpm على `11.1.2`.
- CI Quality Gates وcontainer image publish workflow وstaging smoke workflow كمسارات موثقة.
- Store readiness foundation.
- Store readiness publish validation layer: `StoreReadinessChecker` يرجع `ready/errors/warnings` ويدعم `assertReady`/`assertProductReady` مع tests للـ simple/variable inventory. لم يربط بعد بـ publish action صريح.
- Audit matrix.
- Operations readiness docs/checklists: staging readiness checklist, staging deployment runbook, smoke proof template, restore drill evidence template, monitoring baseline matrix.
- 2FA للـ super admin/platform support/tenant owner داخل لوحات Filament، مع recovery codes وaudit coverage.
- Emergency admin 2FA reset procedure عبر Artisan command، مع reason إلزامي وaudit event `two_factor_reset_by_operator`.
- Stock Movement Ledger foundation: migration/model/enum/factory/relationships/tests/docs.
- Checkout reservation integration: quick checkout reservations تسجل `reserved` stock movements داخل نفس transaction.
- Order cancellation/release integration: `ReleaseOrderInventoryReservations` يسجل `released` stock movements عند تحرير الحجز فعلياً.
- Order settlement integration: `SettleOrderInventory` يسجل `settled` stock movements عند تسوية المخزون فعلياً.
- Return restock integration: `RestockOrderReturn` يسجل `restocked` stock movements عند زيادة المخزون فعلياً.
- Manual inventory adjustment action with AuditLog: `AdjustInventoryManually` يسجل `manual_adjustment`/`correction` stock movements و`inventory_manual_adjustment` AuditLog بدون UI/API عام.
- Product Variants ADR/design: `docs/adr/0013-product-variants-inventory-design.md`.
- Product variants schema foundation: `product_options`, `product_option_values`, `product_variants`, pivot، وأعمدة `product_variant_id` في inventory/order/stock movements.
- Product variant models/factories/relationships.
- Vendor variant management foundation عبر Filament resources منفصلة للـ options/values/variants/pivot.
- Product variant option-values UX refinement: منع ربط variant بقيمة option من منتج آخر داخل نفس tenant.
- Checkout `product_variant_id` support: quick checkout backend يقبل variant اختيارياً، يتحقق من tenant/product/status، يستخدم سعر ومخزون variant، ويحفظ snapshot وحركة `reserved`.
- Variant inventory uniqueness/schema activation: تفكيك unique القديم على `inventory_items [tenant_id, product_id]` واستبداله بـ partial unique indexes للـ simple inventory والـ variant inventory.
- Release/settlement/restock `product_variant_id` propagation: lifecycle inventory actions تبحث عن `InventoryItem` حسب sellable unit وتسجل `product_variant_id` في حركات `released`/`settled`/`restocked`.
- Storefront variant selection backend serialization: product detail API يعرض active variants/options/availability للـ picker.
- Storefront variant picker UI: صفحة product detail تختار variant من options، تعرض السعر/التوفر، وتضيف `product_variant_id` إلى quick/cart checkout.
- Product type/simple-vs-variable enforcement: `products.type` مصدر الحقيقة؛ checkout يرفض parent variable بدون variant ويرفض variant على simple product، والـ storefront يستخدم picker للـ variable فقط.
- Staging deployment runbook: `docs/STAGING_DEPLOYMENT_RUNBOOK_AR.md` مع checklist وsmoke proof template، بدون أسرار وبدون نشر حقيقي.

## Next Backlog

1. COD reconciliation foundation.
2. Real staging execution after VPS/domain.
3. Production hardening review.
4. Monitoring/observability foundation.
5. Product variant UX polish.

## Definition Of Done

- Tests: أضيفت أو حدّثت بحسب المخاطر، ولا توجد skips لإخفاء فشل.
- Docs: الوثائق المرتبطة محدثة أو السبب موثق.
- CI: أوامر التحقق الأساسية تمر محلياً أو الفشل موثق بسبب بيئة خارجية.
- Tenant isolation: أي query يتجاوز global scope guarded ومبرر.
- Security review: لا secrets، لا PII raw في logs، ولا public API يكشف معرفات داخلية بلا سبب.
- No unrelated changes: لا formatting واسع ولا refactor خارج المهمة.

## Documentation-only Audit Rule

في جولة analysis + docs refresh فقط:

- يسمح بتحديث `docs/` و`README.md` الجذري.
- لا يضاف migration أو dependency أو test إلا إذا طلب ذلك صراحة.
- لا يدعى real staging أو production readiness بدون proof خارجي محفوظ.
