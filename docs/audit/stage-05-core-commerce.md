# Stage 5: Core Commerce Domain Audit

**Audit Date:** 2026-10-01  
**Audit Branch:** `main`  
**Audit Commit:** `bd58620db449ac6211584eb0dc0b125ac3656fb8`  

---

## 1. Domain-by-Domain Implementation & Integrity Analysis

### 1.1 Catalog & Product Hierarchy
- **Architecture:** Managed via `Category`, `Product`, `ProductImage`, and `ProductOption` models.
- **Product Typing (`ProductType`):** Strict differentiation between `ProductType::Simple` and `ProductType::Variable`.
- **Tenant Isolation:** Enforced via `tenant_id` on all tables, `BelongsToTenant` scope, and composite foreign keys (`products_category_same_tenant_fk`, `product_images_product_same_tenant_fk`).
- **Storefront Search:** `SearchStorefrontProducts` utilizes Laravel Scout with Meilisearch engine, filtering by active status, publication date, and `tenant_id`.
- **Public Serialization:** `ProductResource` hides cost prices (`cost_price_minor`) and internal metadata from public API responses.

### 1.2 Product Variants & Options (ADR 0013)
- **Architecture:** `ProductOption`, `ProductOptionValue`, `ProductVariant`, `ProductVariantOptionValue`.
- **Option Signature:** Unique index `['tenant_id', 'product_id', 'option_signature']` prevents duplicate variant permutations.
- **Price Fallback Logic:** `ProductVariant::effectivePriceMinor()` checks `$this->price_minor ?? (int) $this->product?->price_minor`. Variant price overrides take precedence; otherwise the parent price is inherited.
- **Variant Validation in Checkout:**
  - Simple products reject payloads with `product_variant_id`.
  - Variable products require valid `product_variant_id`.
  - Variant must belong to the same product (`$variant->product_id === $item['product_id']`) and same tenant.
  - Inactive variants are rejected with 422.

### 1.3 Inventory & Stock Reservation
- **Sellable Unit Granularity:**
  - Simple products: `InventoryItem` where `product_variant_id IS NULL`.
  - Variable products: `InventoryItem` where `product_variant_id` is set.
- **Database Engine Constraints:**
  - Unique partial indexes enforce single inventory rows per sellable unit.
  - PostgreSQL constraint: `CHECK (allow_backorders OR reserved_quantity <= quantity)`.
  - Non-negative constraints: `quantity >= 0` and `reserved_quantity >= 0`.
- **Reservation Lifecycle:**
  - Checkout increments `reserved_quantity` under row-level lock (`lockForUpdate()`).
  - Available stock calculation: `quantity - reserved_quantity`.
  - Direct mutations without ledger entries are forbidden; inventory movements are handled by dedicated actions.

### 1.4 Stock Movement Ledger
- **Append-Only Ledger (`StockMovement`):** Every quantity mutation logs:
  - `type` (`StockMovementType`: `Reserved`, `Settled`, `Released`, `Restocked`, `Adjustment`, `Initial`)
  - `quantity_delta` and `reserved_delta`
  - `balance_quantity_after` and `balance_reserved_after`
  - Reference entities (`order_id`, `order_item_id`, `order_return_id`, `actor_id`)
  - Reason code string (`StockMovementReason`)
- **Immutability:** Stock movements are never updated or deleted; reconciliation is achieved solely through new balancing records.

### 1.5 Checkout & Financial Recalculation (ADRs 0005, 0006)
- **Source of Truth:** Executed within `CreateQuickOrder::handle()` inside a single atomic database transaction (`DB::transaction`).
- **Financial Recalculation:**
  - Client totals are ignored.
  - Unit price fetched server-side from `Product` or `ProductVariant`.
  - `subtotal_minor = SUM(unit_price_minor * quantity)`.
  - `shipping_fee_minor` resolved from `ShippingRate` matching wilaya, commune, and delivery type.
  - `discount_minor` calculated server-side by `CalculateCouponDiscount`.
  - `total_minor = subtotal_minor + shipping_fee_minor - discount_minor`.
- **Concurrency & Row Locking:**
  - Products, variants, and inventory items are queried with `lockForUpdate()`.
  - Prevents race conditions during simultaneous checkouts of limited stock.
- **Idempotency Protection (`CheckoutIdempotency`):**
  - First-class support for `Idempotency-Key` header with database locks (`CheckoutIdempotencyRecord`).
  - Fallback duplicate window: 60-second window matching identical customer phone and SHA-256 request payload hash replays original order instead of duplicate order creation.

### 1.6 Orders & State Machine
- **Order Model & Snapshots:** Order snapshots customer phone, name, wilaya, commune, address, note, subtotal, shipping fee, discount, total, and order items.
- **Status State Machine (`TransitionOrderStatus`):**
  - Strict transition graph (`ALLOWED_TRANSITIONS`). Illegal transitions throw `ValidationException`.
  - Setting order to `Confirmed` timestamps `confirmed_at`.
  - Setting order to `Delivered` invokes `SettleOrderInventory` (deducts stock and releases reservation).
  - Setting order to `Cancelled` or `Returned` invokes `ReleaseOrderInventoryReservations` (releases reserved stock).
- **Audit History:** Every status change writes an `OrderStatusHistory` entry.

### 1.7 Payments (Manual & COD)
- **Manual Flow (ADR 0009):** Cash on delivery and bank transfer are first-class.
- **Payment Lifecycle:**
  - Order creation creates initial `Payment` record with status `Pending`.
  - `RecordOrderPayment`: Validates order can receive payment, verifies `amount_minor === outstandingAmount`, asserts payment method tenant ownership, marks payment as `Paid`, and updates order `payment_status` to `Paid`.
  - `RefundOrderPayment`: Validates paid status, marks all payments as `Refunded`, updates order `payment_status` to `Refunded`.
- **Payment Gateways:** Automated online gateways (CIB/Edahabia/Satim) are NOT FOUND (deferred per ADR 0009).

### 1.8 Shipping & Delivery
- **Shipping Rates (ADR 0010):** Internal shipping tables mapping `wilaya_id` and optional `commune_id` to delivery types (`Home` vs `StopDesk`).
- **Shipment Lifecycle (`TransitionShipmentStatus`):**
  - Shipment transitions synchronize order status (`ReadyToShip` -> `Packed`, `Shipped` -> `Shipped`, `Delivered` -> `Delivered`).
  - Failed deliveries record `failed_delivery_reason_id` and `failure_note`.
  - Order inventory is automatically settled upon delivery or released upon cancellation/return.

### 1.9 Returns & Restocking
- **Return Lifecycle:** `OrderReturn` records items returned and reasons.
- **Restocking Action (`RestockOrderReturn`):**
  - Atomically increments `inventory_items.quantity` under row-level lock.
  - Idempotent via `metadata['restocked_at']`.
  - Records `StockMovementType::Restocked` with positive delta.

---

## 2. Integrity Evaluation Summary

| Subsystem / Workflow | Tenant Isolated? | Transactional? | Concurrency Safe? | Ledger Tracked? | Source of Truth Verified? |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **Catalog / Variants** | YES | YES | YES | N/A | Server-side DB |
| **Inventory Reservation** | YES | YES | YES (`lockForUpdate`) | YES (`StockMovement`) | PostgreSQL check constraints |
| **Checkout Pricing** | YES | YES | YES | N/A | Server-side recalculation |
| **Checkout Idempotency** | YES | YES | YES | N/A | `CheckoutIdempotencyRecord` |
| **Order State Transitions** | YES | YES | YES (`lockForUpdate`) | YES (`OrderStatusHistory`) | `ALLOWED_TRANSITIONS` state machine |
| **Inventory Settlement** | YES | YES | YES (`lockForUpdate`) | YES (`StockMovementType::Settled`) | Idempotent via metadata timestamp |
| **Inventory Release** | YES | YES | YES (`lockForUpdate`) | YES (`StockMovementType::Released`) | Idempotent via metadata timestamp |
| **Payments** | YES | YES | YES (`lockForUpdate`) | N/A | Outstanding balance check |
| **Shipments** | YES | YES | YES (`lockForUpdate`) | YES (`ShipmentStatusHistory`) | Synchronized with order status |
| **Returns Restocking** | YES | YES | YES (`lockForUpdate`) | YES (`StockMovementType::Restocked`) | Idempotent via metadata timestamp |

**Finding:** Core commerce domain is robustly designed and implemented with transactional safety, row-level locking, append-only ledger tracking, and server-side pricing recalculation. No critical financial or inventory corruption vectors were identified.
