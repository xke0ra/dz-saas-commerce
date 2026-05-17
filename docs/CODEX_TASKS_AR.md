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

Next backlog:

1. تنفيذ real staging فعلياً باستخدام checklist، بدون أسرار في repo.
2. تنفيذ backup restore drill فعلي وتسجيل evidence.
3. Integrate order settlement/restock/returns with stock movements.
4. Product variants ADR/design.
5. Store readiness publish gate.
6. CSP report-only.
7. Observability provider selection and integration.
8. Storefront caching ADR implementation.
9. COD reconciliation foundation.

## Definition Of Done

- Tests: أضيفت أو حدّثت بحسب المخاطر، ولا توجد skips لإخفاء فشل.
- Docs: الوثائق المرتبطة محدثة أو السبب موثق.
- CI: أوامر التحقق الأساسية تمر محلياً أو الفشل موثق بسبب بيئة خارجية.
- Tenant isolation: أي query يتجاوز global scope guarded ومبرر.
- Security review: لا secrets، لا PII raw في logs، ولا public API يكشف معرفات داخلية بلا سبب.
- No unrelated changes: لا formatting واسع ولا refactor خارج المهمة.
