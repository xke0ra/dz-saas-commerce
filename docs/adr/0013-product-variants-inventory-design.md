# ADR 0013: Product Variants And Inventory Design

Date: 2026-05-17

Status: Proposed

هذه جولة design فقط. لا يوجد في هذا القرار أي migration أو model أو تغيير checkout/storefront منفذ الآن.

## Context

- `Product` هو الكيان التجاري الحالي للكتالوج، ويحتوي على `sku`, `price_minor`, `compare_at_price_minor`, `cost_price_minor`, `status`, `metadata`، ويدخل في search index.
- لا يوجد حالياً `ProductVariant` أو `ProductOption`.
- `InventoryItem` مرتبط حالياً بـ `product_id` فقط، ويوجد unique على `tenant_id + product_id`.
- `InventoryItem` يحمل `quantity`, `reserved_quantity`, `track_quantity`, `allow_backorders`.
- `OrderItem` يحفظ snapshot للمنتج: `product_id`, `product_name`, `product_sku`, `quantity`, `unit_price_minor`, `total_minor`, `metadata`.
- quick checkout يستقبل `product_id` و`quantity`، أو `items[]` بنفس الشكل، ثم يحسب السعر من `products.price_minor`.
- quick checkout يحجز المخزون بالبحث عن `InventoryItem` عبر `tenant_id + product_id`.
- `StockMovement` مرتبط بـ `product_id` و`inventory_item_id`، وأحياناً `order_id`, `order_item_id`, `order_return_id`.
- stock ledger الحالي يسجل `reserved`, `released`, `settled`, `restocked`, و`manual_adjustment`/`correction`.
- storefront product API يعرض product واحداً مع inventory summary على مستوى product فقط.
- vendor UI الحالي يدير products وinventory items على مستوى product فقط.

## Problem

المتاجر الواقعية تحتاج variants/options مثل المقاس، اللون، السعة، النكهة، أو bundle configuration. هذه القيم ليست metadata تجميلية فقط، لأنها قد تغيّر:

- SKU.
- السعر.
- تكلفة الشراء.
- حالة النشر.
- المخزون المتاح.
- قابلية الشحن.
- اختيار العميل في storefront.
- line item snapshot داخل الطلب.
- stock movement ledger والتقارير.

إذا بقي checkout يشتري parent `product_id` فقط لمنتج له variants، فلن نستطيع معرفة أي SKU بيع، ولا أي مخزون يجب حجزه أو تسويته أو إرجاعه.

## Decision

التصميم المفضل هو Option B: إضافة `product_variants` ككيان مستقل، وربط المخزون والطلبات وحركات المخزون بالـ sellable unit.

### Option A: إبقاء `inventory_items.product_id` فقط وتخزين variant داخل metadata

الفكرة:

- لا نضيف جدول variants.
- نخزن `color`, `size`, `variant_sku` أو option signature في `metadata`.
- checkout يرسل `product_id` وربما metadata.

المزايا:

- أسرع في التنفيذ الأولي.
- لا يحتاج migration واسعة مباشرة.

العيوب:

- لا توجد constraints حقيقية على SKU أو option uniqueness.
- صعب منع duplicate variants أو invalid option combinations.
- صعب ضمان tenant integrity بعلاقات مركبة.
- checkout سيعتمد على metadata قابلة للتلاعب إن لم يعاد بناؤها في backend.
- stock ledger لن يملك مفتاحاً مستقراً للتقارير variant-level.
- search/import/export سيحتاج parsing غير موثوق للـ json.
- لا يناسب vendor UI أو future APIs.

النتيجة: مرفوض.

### Option B: `product_variants` ككيان مستقل

الفكرة:

- `Product` يبقى parent للعرض والـ SEO والتصنيف.
- `ProductVariant` يمثل وحدة بيع محددة عندما يكون المنتج variable.
- `InventoryItem` يصبح قادراً على الارتباط بـ `product_variant_id`.
- `OrderItem` يحفظ `product_id` و`product_variant_id` nullable مع snapshots.
- `StockMovement` يحفظ `product_id`, `product_variant_id` nullable، و`inventory_item_id`.

المزايا:

- constraints واضحة.
- SKU وسعر ومخزون لكل variant.
- checkout يستطيع شراء sellable unit محدد.
- stock ledger يبقى دقيقاً على مستوى variant.
- search/import/export وvendor UI يمكن بناؤها فوق نموذج واضح.
- tenant integrity يمكن فرضه بـ composite foreign keys.

العيوب:

- يحتاج migrations انتقالية مدروسة.
- يحتاج تحديثات متعددة في checkout/storefront/inventory actions.
- يحتاج UI لإدارة options والقيم وتوليد variants لاحقاً.

النتيجة: مقبول كتصميم مفضل.

### Option C: اعتبار كل variant كـ product مستقل مع `parent_product_id`

الفكرة:

- كل لون/مقاس يصبح product منفصل.
- المنتج الأب يستخدم للتجميع فقط.

المزايا:

- يستفيد من بعض جداول product الحالية.
- يجعل inventory على product كما هو تقريباً.

العيوب:

- يخلط product للعرض مع sellable unit.
- يعقد SEO، الصور، التصنيفات، search، وstorefront grouping.
- قد يسبب duplicate content وتكرار بيانات product.
- order/reporting تحتاج فهم parent/child في كل مكان.
- إدارة options ستكون أقل وضوحاً من `ProductVariant`.

النتيجة: مرفوض للمرحلة الأولى.

## Proposed Schema Overview

هذا schema مقترح لمراحل لاحقة فقط، وليس migration في هذه الجولة.

### `products`

إضافة مستقبلية مقترحة:

- `type` string default `simple`
  - القيم: `simple`, `variable`.

قرار SKU:

- `products.sku` يبقى مستخدماً للمنتجات simple.
- للمنتجات variable يمكن أن يبقى SKU للأب اختيارياً لأغراض داخلية، لكن SKU الذي يدخل checkout والمخزون يجب أن يكون variant SKU.

### `product_options`

- `id` ULID primary key.
- `tenant_id` required.
- `product_id` required.
- `name` string.
- `position` unsigned integer default 0.
- timestamps.

قيود مقترحة:

- FK `tenant_id` إلى `tenants`.
- FK `product_id` إلى `products`.
- composite FK `[tenant_id, product_id]` references `products [tenant_id, id]`.
- unique `[tenant_id, product_id, name]`.
- check `btrim(name) <> ''`.

### `product_option_values`

- `id` ULID primary key.
- `tenant_id` required.
- `product_option_id` required.
- `value` string.
- `position` unsigned integer default 0.
- timestamps.

قيود مقترحة:

- FK `tenant_id` إلى `tenants`.
- FK `product_option_id` إلى `product_options`.
- composite FK `[tenant_id, product_option_id]` references `product_options [tenant_id, id]`.
- unique `[tenant_id, product_option_id, value]`.
- check `btrim(value) <> ''`.

### `product_variants`

- `id` ULID primary key.
- `tenant_id` required.
- `product_id` required.
- `sku` nullable في migration الأولى، ثم يمكن جعله مطلوباً للمنتجات variable بعد data readiness.
- `option_signature` string required.
- `title` أو `display_name` nullable.
- `price_minor` nullable.
- `compare_at_price_minor` nullable.
- `cost_price_minor` nullable.
- `status` string.
- `sort_order` unsigned integer default 0.
- `metadata` jsonb nullable.
- timestamps.

قيود مقترحة:

- FK `tenant_id` إلى `tenants`.
- FK `product_id` إلى `products`.
- composite FK `[tenant_id, product_id]` references `products [tenant_id, id]`.
- unique `[tenant_id, product_id, option_signature]`.
- unique partial على `[tenant_id, sku]` عندما `sku IS NOT NULL`.
- check nonnegative prices when nullable.
- check `btrim(option_signature) <> ''`.

### `product_variant_option_values`

Pivot مقترح لتطبيع علاقة variant بالقيم المختارة:

- `id` ULID primary key أو composite primary key.
- `tenant_id` required.
- `product_variant_id` required.
- `product_option_id` required.
- `product_option_value_id` required.
- timestamps.

قيود مقترحة:

- composite FKs لكل علاقة tenant-scoped.
- unique `[tenant_id, product_variant_id, product_option_id]`.
- unique `[tenant_id, product_variant_id, product_option_value_id]`.
- يجب أن تنتمي option/value لنفس product الخاص بالvariant.

ملاحظة: `option_signature` يبقى normalized key سريعاً لل uniqueness والقراءة، بينما pivot يخدم العلاقات والاستعلامات.

### `inventory_items`

إضافة مستقبلية:

- `product_variant_id` nullable.

المرحلة الانتقالية:

- المنتج simple يستخدم `product_id` و`product_variant_id = null`.
- المنتج variable يجب أن يكون لكل variant قابل للبيع `InventoryItem`.
- `inventory_item_id` يبقى مصدر الحقيقة للـ stock ledger.

قيود مقترحة:

- FK `product_variant_id` إلى `product_variants`.
- composite FK `[tenant_id, product_variant_id]` references `product_variants [tenant_id, id]`.
- composite FK `[tenant_id, product_id, product_variant_id]` إن أضيف unique مناسب في `product_variants`.
- استبدال unique الحالي `[tenant_id, product_id]` بقيود partial:
  - unique `[tenant_id, product_id] WHERE product_variant_id IS NULL`.
  - unique `[tenant_id, product_variant_id] WHERE product_variant_id IS NOT NULL`.
- منع أكثر من inventory item لنفس variant.
- منع `product_variant_id` لمنتج simple.
- منع `product_variant_id = null` لمنتج variable بعد اكتمال migration وdata backfill.

### `order_items`

إضافة مستقبلية:

- `product_variant_id` nullable.
- `variant_title` nullable.
- `variant_sku` nullable.
- `selected_options` jsonb nullable، أو داخل `metadata` بقرار migration واضح.

Snapshots مطلوبة:

- `product_name`.
- `product_sku`.
- `variant_title`.
- `variant_sku`.
- selected options مثل `{ "Size": "XL", "Color": "Black" }`.

قيود مقترحة:

- composite FK `[tenant_id, product_variant_id]` references `product_variants [tenant_id, id]`.
- إذا `product_variant_id` موجود، يجب أن يتبع نفس `product_id`.

### `stock_movements`

إضافة مستقبلية:

- `product_variant_id` nullable.

قواعد:

- `inventory_item_id` يبقى مصدر الحقيقة للحركة.
- `product_id` يبقى denormalized/explicit لتسهيل الاستعلامات والتقارير القديمة.
- `product_variant_id` يساعد variant-level reporting.
- كل flows التي تنشئ movements يجب أن تنسخ `product_variant_id` من `InventoryItem`/`OrderItem` بعد تفعيل schema.

قيود وفهارس مقترحة:

- composite FK `[tenant_id, product_variant_id]` references `product_variants [tenant_id, id]`.
- index `[tenant_id, product_variant_id, occurred_at]`.
- check اختياري: إذا `product_variant_id` ليس null يجب أن يكون مرتبطاً بنفس `product_id`.

## Domain Rules

### Catalog

- المنتج قد يكون `simple` أو `variable`.
- `simple product` لا يملك variants قابلة للبيع.
- `variable product` يجب أن يملك variant واحداً على الأقل قبل النشر.
- `variant SKU` يجب أن يكون unique داخل tenant عندما يكون موجوداً.
- `product SKU` يبقى للمنتج الأب أو للمنتجات simple؛ checkout لا يعتمد على parent SKU عندما توجد variants.
- options يجب أن تكون tenant-scoped ومرتبطة بمنتج واحد.
- لا يسمح بتكرار نفس option signature داخل product واحد.

### Inventory

- inventory must be tracked at sellable unit level.
- sellable unit هو:
  - `product` للمنتج simple.
  - `product_variant` للمنتج variable.
- لا يجوز checkout على parent product إذا كان variable وله variants.
- كل تعديل مخزون variant يجب أن يكتب `StockMovement`.
- `InventoryItem` يجب أن يكون واضحاً: product-level للـ simple، variant-level للـ variable.
- `allow_backorders` يبقى على sellable unit inventory item.

### Checkout

- payload المستقبلي يجب أن يدعم:
  - `product_id`.
  - `product_variant_id` nullable.
  - `quantity`.
- إذا product requires variant، يجب رفض checkout بدون `product_variant_id`.
- إذا product simple، يجب رفض `product_variant_id` غير تابع له أو غير null حسب contract النهائي.
- السعر يحسب من backend:
  - `product_variant.price_minor` إذا موجود.
  - وإلا `product.price_minor`.
- availability يحسب من `InventoryItem` للـ sellable unit.
- `OrderItem` يجب أن يحفظ snapshot للvariant/options وقت الشراء.
- idempotency request hash يجب أن يشمل `product_variant_id` عندما يدعم checkout ذلك.

### Storefront

- product detail يجب أن يعرض options/values/variants المتاحة.
- UX المرحلة الأولى: disabled أو out-of-stock variants تظهر كخيارات disabled إذا كان ذلك يساعد العميل على فهم الخيارات، وتختفي فقط إذا كانت غير منشورة.
- الواجهة لا تعتمد على السعر أو المخزون كحقيقة نهائية.
- cart item key يجب أن يصبح `product_id + product_variant_id` للمنتجات variable، وليس `product_id` فقط.
- product cards يمكن أن تعرض price range أو lowest active variant price لاحقاً، لكن backend يجب أن يحدد ذلك.

### Search

- product search يفهرس product كوثيقة أساسية مع variants summary في المرحلة الأولى.
- لا يلزم index كل variant كوثيقة مستقلة في المرحلة الأولى.
- يمكن إضافة variant-level documents لاحقاً إذا احتجنا بحثاً على SKU أو option value بدقة أعلى.

### Tenant Integrity

- كل جداول variants/options/inventory/order references يجب أن تكون tenant-scoped.
- استخدم composite FKs حيث مناسب.
- أي query يستخدم `withoutGlobalScope('current_tenant')` في catalog/inventory/checkout يجب أن يضيف `where('tenant_id', ...)` واختبار tenant isolation.

## Phased Implementation Plan

### PR 1: Schema foundation فقط

النطاق:

- إضافة `product_options`.
- إضافة `product_option_values`.
- إضافة `product_variants`.
- إضافة `product_variant_id` nullable في `inventory_items`, `order_items`, `stock_movements`.
- إضافة constraints/indexes/tenant integrity tests فقط.
- لا checkout behavior change.

Rollback:

- منخفض إذا لم تُستخدم الأعمدة الجديدة بعد.
- يحتاج drop constraints/indexes/tables بالترتيب الصحيح.

### PR 2: Models/factories/tests

النطاق:

- إضافة models وrelations وfactories.
- إضافة tenant integrity tests.
- إضافة casts/status enum إن لزم.
- لا checkout/storefront behavior change.

Rollback:

- منخفض؛ إزالة classes/tests بدون data migration إضافية.

### PR 3: Vendor product variant management backend/forms foundation

النطاق:

- إدارة options/values/variants في vendor backend.
- validation لإنتاج option signatures.
- لا storefront بعد.
- لا checkout behavior change.

Rollback:

- متوسط؛ قد توجد data variants تجريبية يجب تعطيلها أو حذفها.

### PR 4: Checkout accepts `product_variant_id`

النطاق:

- تحديث request payload.
- تحديث validation.
- تحديث price calculation.
- تحديث inventory reservation ليستخدم variant inventory.
- تحديث `QuickCheckoutTest`.
- رفض checkout على parent variable product بدون variant.

Rollback:

- متوسط إلى عال؛ قد توجد orders مع `product_variant_id`.
- rollback يحتاج إيقاف قبول variants أو الحفاظ على compatibility للطلبات المنشأة.

### PR 5: Stock movements include `product_variant_id` in all flows

النطاق:

- checkout reservation.
- release/cancellation.
- settlement.
- return restock.
- manual adjustment.
- ledger tests لكل flow.

Rollback:

- متوسط؛ column nullable يخفف rollback، لكن التقارير variant-level قد تفقد الدقة إذا أزيلت.

### PR 6: Storefront product detail variant selection

النطاق:

- عرض options/values/variants.
- cart key يصبح product+variant.
- إرسال `product_variant_id` في checkout.
- UX للخيارات disabled/out-of-stock.

Rollback:

- متوسط؛ يجب الحفاظ على simple product checkout وعدم كسر cart المخزن في localStorage.

### PR 7: Import/export support لاحقاً

النطاق:

- import/export للoptions/variants/SKU/price/inventory.
- validation للتوقيعات والـ duplicate SKUs.

Rollback:

- متوسط إلى عال حسب حجم البيانات المستوردة؛ يحتاج dry-run وerror report قبل writes.

## Consequences

- يجب عدم تنفيذ variants كـ metadata فقط.
- كل تنفيذ لاحق يجب أن يحافظ على backend as source of truth للسعر والمخزون.
- product-level inventory يبقى صالحاً فقط للـ simple products.
- variant-level inventory هو القاعدة للـ variable products.
- stock movement ledger يجب أن يبقى append-only ويعكس sellable unit بدقة.
- هذا ADR يبقى `Proposed` حتى تثبت PRs التنفيذية schema/tests وسلوك checkout/storefront.
