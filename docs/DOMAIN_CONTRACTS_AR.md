# عقود الدومينات

هذه الوثيقة تمنع إدخال منطق خاطئ مستقبلاً. عند وجود تعارض بينها وبين الكود الحالي، عالج التعارض بتغيير صغير أو ADR قبل بناء feature كبيرة.

## 1. Tenancy Contract

- كل بيانات tenant-scoped يجب أن تملك `tenant_id`.
- لا توجد علاقات cross-tenant بين بيانات تجارية.
- العلاقات الحساسة تحتاج composite foreign keys مثل `(tenant_id, store_id)` أو `(tenant_id, product_id)` عندما تسمح قاعدة البيانات بذلك.
- `BelongsToTenant` أو بديل واضح مطلوب لكل model مملوك لمستأجر.
- لا يُزال global scope `current_tenant` إلا لسبب واضح.
- أي `withoutGlobalScope('current_tenant')` يجب أن يضيف `where('tenant_id', ...)` أو guard مكافئ، ومعه test عند لمس checkout/billing/inventory/tenancy.
- Public storefront لا يثق بالـ host فقط إذا احتاج عملية حساسة؛ يجب حل store/tenant بوضوح.

## 2. Checkout Contract

- storefront يرسل `items`, customer data, shipping selection, payment intent فقط.
- backend يحسب prices, discounts, shipping fees, totals, currency, payment status, and inventory reservation.
- الواجهة لا تفرض `subtotal`, `discount`, `shipping_fee`, أو `total`.
- inventory reservation يتم داخل transaction وبقفل عند الحاجة.
- quick checkout reservation يسجل `reserved` stock movement داخل نفس transaction عند إنشاء order جديد.
- checkout يجب أن يستخدم idempotency key أو duplicate window fallback واضح.
- order items تحفظ snapshots: product name, SKU, unit price, quantity, line total، ومع variant اختياري تحفظ `product_variant_id`, `variant_title`, `variant_sku`, و`selected_options`.
- checkout backend يدعم `product_variant_id` اختيارياً داخل cart items. عند وجوده يجب أن يكون تابعاً لنفس tenant ونفس `product_id` وأن يكون active، مع بقاء checkout للمنتجات simple عبر `product_id` فقط.
- storefront لم يتغير بعد ولا يملك variant picker؛ أي `product_variant_id` يصل إلى checkout يجب أن يعاد التحقق منه في backend ولا يعتمد على العميل.
- أي checkout failure يجب أن يرجع validation آمن بدون تسريب tenant data أو internal ids غير ضرورية.

## 3. Inventory Contract

- المخزون لا يعدل عشوائياً من controllers أو UI callbacks.
- أي تعديل مستقبلي على `quantity` أو `reserved_quantity` يجب أن يسجل `stock movement`.
- `stock_movements` سجل تشغيلي append-only للمخزون، وليس بديلاً عن `AuditLog`.
- Laravel backend هو مصدر الحقيقة الوحيد لحركات المخزون.
- storefront لا يكتب `stock movement` ولا يقرر مخزوناً موثوقاً.
- `ReleaseOrderInventoryReservations` يسجل `released` stock movement عند تحرير الحجز فعلياً.
- `SettleOrderInventory` يسجل `settled` stock movement عند تسوية المخزون فعلياً.
- `RestockOrderReturn` يسجل `restocked` stock movement عند زيادة المخزون فعلياً بسبب return restock.
- manual inventory adjustment يجب أن يمر عبر `AdjustInventoryManually` وأن يكتب `StockMovement` و`AuditLog` معاً.
- أي UI/API مستقبلي لتعديل المخزون يجب أن يستخدم `AdjustInventoryManually` فقط.
- `reserved_quantity` يعني كمية محجوزة لطلبات لم تُسوَّ نهائياً.
- `available` يجب أن يعني `quantity - reserved_quantity` عندما `track_quantity=true`.
- backorders يجب أن تكون قراراً صريحاً محفوظاً على inventory item أو policy واضحة.
- checkout لا يخصم `quantity` مباشرة؛ يحجز أولاً ثم settle/release حسب حالة الطلب.
- schema foundation للـ variants يضيف `product_variant_id` nullable إلى inventory/order/stock movement tables مع tenant-scoped constraints.
- checkout backend يستطيع حجز `InventoryItem` المرتبط بـ `product_variant_id` عند إرساله، ولا يسقط إلى parent inventory إذا لم يوجد variant inventory.
- uniqueness في `inventory_items` أصبح على sellable unit: صف simple واحد لكل `tenant_id + product_id` عندما `product_variant_id IS NULL`، وصف واحد لكل `tenant_id + product_variant_id` عندما `product_variant_id IS NOT NULL`.
- variants يجب أن تعامل كـ sellable unit عند تنفيذها سلوكياً: المخزون يكون على `product` للمنتج simple وعلى `product_variant` للمنتج variable.
- لا يجوز checkout على parent variable product بدون `product_variant_id` بعد تفعيل سلوك variable products.

## 4. Billing Contract

- `plans`, `features`, `subscriptions`, و`usage counters` هي مصدر قيود SaaS.
- feature gate يجب أن يسبق العمليات المقيدة مثل product limits, custom domain, staff limits, coupons, analytics.
- أي تغيير plan/subscription/payment يجب أن يكون audited.
- payment proof/manual billing لاحقاً يجب أن يسجل actor, decision, reference, proof metadata, rejection reason.
- لا تعتمد الواجهة على حالة اشتراك غير مؤكدة من client state.

## 5. Storefront API Contract

- API لا يكشف `tenant_id` للعميل إلا إذا وُجد سبب موثق.
- الواجهة لا ترسل totals موثوقة ولا تفرض السعر النهائي.
- responses يجب أن تبقى مستقرة: أسماء الحقول العامة لا تتغير بدون migration plan أو versioning.
- product/catalog responses يجب أن تعرض فقط منتجات مرئية ومسموحة لذلك store.
- checkout responses يجب أن تحتوي order confirmation آمن، لا raw internal operational data.

## 6. Security/Audit Contract

- العمليات الحساسة يجب أن تسجل `actor`, `action/event`, `auditable`, `old_values`, `new_values`, و`metadata` عند الحاجة.
- لا تسجل PII raw مثل الهاتف أو IP في application logs؛ استخدم masking أو hashing عند الحاجة.
- Audit logs نفسها immutable من واجهة الإدارة.
- أي security-sensitive admin action يحتاج audit matrix entry قبل التنفيذ.
- secrets لا توضع في docs أو tests أو committed config.

## 7. Operations Contract

- أي production-facing feature تحتاج health/readiness consideration.
- إذا اعتمدت feature على queue أو scheduler أو storage أو search، يجب توثيق ذلك في docs/runbook.
- readiness لا يجب أن يعطي false positive عند فقدان PostgreSQL, cache, Redis, storage, queue tables, أو Meilisearch عندما تكون مطلوبة.
- أي deployment change يحتاج smoke path واضح وrollback notes.
- backup/restore وmonitoring ليست features لاحقة اختيارية؛ هي شرط قبل beta/production.
