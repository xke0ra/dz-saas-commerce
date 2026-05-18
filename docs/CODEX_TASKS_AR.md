# قواعد مهام Codex

هذا الملف مرجع دائم قبل إعطاء أي prompt لاحق لـ Codex داخل `dz-saas-commerce`.

## قواعد عامة

- نفذ مهمة واحدة صغيرة في كل مرة.
- لا تحول المشروع إلى microservices.
- لا تبن marketplace الآن.
- لا تغيّر معمارية واسعة بدون ADR.
- لا تعدل checkout أو inventory أو billing أو tenancy بدون tests مناسبة.
- لا تستخدم `withoutGlobalScope('current_tenant')` إلا مع guard صريح بـ `tenant_id` أو `store_id` واختبار عند الحاجة.
- لا تضع منطق مالي موثوق في storefront؛ backend هو مصدر الحقيقة للأسعار والخصومات والشحن والمخزون والtotals.
- لا تضع secrets في الكود أو الوثائق أو أمثلة قابلة للنسخ.
- لا تضف dependencies جديدة إلا إذا كانت ضرورية ومبررة.
- لا تستخدم skip/todo لإخفاء فشل tests.
- لا تخلط refactor واسع مع feature صغيرة.

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
pnpm install --frozen-lockfile
pnpm typecheck
pnpm build
pnpm test:e2e
```

### معيار القبول

- السلوك المطلوب مثبت.
- tests أو docs تغطي التغيير.
- لا يوجد كسر في tenant isolation.
- لا توجد تغييرات غير مرتبطة.

### مخاطر rollback

اذكر ما الذي يمكن إرجاعه بسهولة، وما الذي يحتاج migration أو data repair.

## Backlog أولي مرتب

Completed foundation:

- إصلاح موثوقية Storefront E2E.
- توحيد نسخة pnpm بين `packageManager` وCI/scripts/docs.
- Store readiness foundation.
- Audit matrix.
- Operations readiness docs/checklists محدثة: staging readiness checklist, restore drill evidence template, monitoring baseline matrix.
- 2FA للـ super admin/platform support/tenant owner داخل لوحات Filament، مع recovery codes وaudit coverage.
- Emergency admin 2FA reset procedure عبر Artisan command، مع reason إلزامي وaudit صريح.
- Stock Movement Ledger foundation: migration/model/enum/factory/relationships/tests/docs، بدون ربط checkout أو returns بعد.
- Checkout reservation integration: quick checkout reservations تسجل `reserved` stock movements داخل نفس transaction.
- Order cancellation/release integration: `ReleaseOrderInventoryReservations` يسجل `released` stock movements عند تحرير الحجز فعلياً.
- Order settlement integration: `SettleOrderInventory` يسجل `settled` stock movements عند تسوية المخزون فعلياً.
- Return restock integration: `RestockOrderReturn` يسجل `restocked` stock movements عند زيادة المخزون فعلياً.
- Manual inventory adjustment action with AuditLog: `AdjustInventoryManually` يسجل `manual_adjustment`/`correction` stock movements و`inventory_manual_adjustment` AuditLog بدون UI/API.
- Product Variants ADR/design: `docs/adr/0013-product-variants-inventory-design.md` يحدد تصميم variants/options وتأثيره على inventory/checkout/order items/stock movements/storefront.
- Product variants schema foundation: migrations للجداول والأعمدة nullable، constraints، وtenant integrity tests بدون تغيير checkout/storefront/models/actions.
- Product variant models/factories/relationships: طبقة Eloquent فوق schema foundation مع tests، بدون تغيير checkout/storefront/actions/UI/API.
- Vendor variant management foundation: موارد Filament منفصلة للـ options/values/variants/pivot مع policies واختبارات tenant scoping، بدون checkout/storefront/API/migrations.
- Product variant option-values UX refinement: فلترة/validation تمنع ربط variant بقيمة option من منتج آخر داخل نفس tenant، مع تحسين محدود لتوضيح `option_signature`.
- Checkout product_variant_id support: quick checkout backend يقبل `product_variant_id` اختيارياً، يتحقق من tenant/product/status، يستخدم سعر ومخزون variant، ويحفظ snapshot وحركة `reserved`.
- Variant inventory uniqueness/schema activation: تفكيك unique القديم على `inventory_items [tenant_id, product_id]` واستبداله بـ partial unique indexes للـ simple inventory والـ variant inventory.
- Release/settlement/restock product_variant_id propagation review: lifecycle inventory actions تبحث عن `InventoryItem` حسب sellable unit وتسجل `product_variant_id` في حركات `released`/`settled`/`restocked`.
- Storefront variant selection backend serialization: product detail API يعرض active variants/options/availability للـ picker بدون frontend UI وبدون تغيير checkout contract.
- Storefront variant picker UI: صفحة product detail تختار variant من options، تعرض السعر/التوفر، وتضيف `product_variant_id` إلى quick/cart checkout بدون تغيير backend contract.

Next backlog:

1. Product type/simple-vs-variable enforcement.
2. Store readiness publish gate.
3. تنفيذ real staging فعلياً باستخدام checklist، بدون أسرار في repo.
4. COD reconciliation foundation.
5. Product variant UX polish.
6. تنفيذ backup restore drill فعلي وتسجيل evidence.
7. CSP report-only.
8. Observability provider selection and integration.
9. Storefront caching ADR implementation.

## Definition Of Done

- Tests: أضيفت أو حدّثت بحسب المخاطر، ولا توجد skips لإخفاء فشل.
- Docs: الوثائق المرتبطة محدثة أو السبب موثق.
- CI: أوامر التحقق الأساسية تمر محلياً أو الفشل موثق بسبب بيئة خارجية.
- Tenant isolation: أي query يتجاوز global scope guarded ومبرر.
- Security review: لا secrets، لا PII raw في logs، ولا public API يكشف معرفات داخلية بلا سبب.
- No unrelated changes: لا formatting واسع ولا refactor خارج المهمة.
