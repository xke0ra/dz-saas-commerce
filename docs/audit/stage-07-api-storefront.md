# Stage 7: Public API & Storefront Contract Audit

**Audit Date:** 2026-10-01  
**Audit Branch:** `main`  
**Audit Commit:** `bd58620db449ac6211584eb0dc0b125ac3656fb8`  

---

## 1. Public API Endpoint Inventory

All public storefront endpoints are defined in `backend/routes/api.php` under the `storefront` route prefix:

| Method | Endpoint | Controller Action | Rate Limit | Frontend Caller (`storefront/src/lib/api.ts`) | Auth Required? |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `GET` | `/api/system/health/live` | `SystemHealthChecker::live()` | None | Health monitoring / probes | No |
| `GET` | `/api/system/health/ready` | `SystemHealthChecker::ready()` | None | Deployment / readiness probes | No |
| `GET` | `/api/storefront/geography/wilayas` | `GeographyController@wilayas` | 120/min | `getWilayas()` | No |
| `GET` | `/api/storefront/geography/communes` | `GeographyController@communes` | 120/min | `getCommunes(wilayaId)` | No |
| `GET` | `/api/storefront/resolve` | `StorefrontController@resolve` | 120/min | `resolveStore(host)` | No |
| `GET` | `/api/storefront/{store}/home` | `StorefrontController@home` | 120/min | `getHome(store)` | No |
| `GET` | `/api/storefront/{store}/products` | `StorefrontController@products` | 120/min | `getProductsPage(store, query)` | No |
| `GET` | `/api/storefront/{store}/products/{slug}`| `StorefrontController@product` | 120/min | `getProduct(store, slug)` | No |
| `GET` | `/api/storefront/{store}/categories` | `StorefrontController@categories` | 120/min | `getCategories(store)` | No |
| `GET` | `/api/storefront/{store}/categories/{slug}`| `StorefrontController@category` | 120/min | `getCategory(store, slug)` | No |
| `GET` | `/api/storefront/{store}/search` | `StorefrontController@search` | 120/min | `searchProducts(store, q)` | No |
| `POST` | `/api/storefront/{store}/checkout` | `StorefrontController@checkout` | **20/min** | `submitCheckout(store, payload)` | No |
| `GET` | `/api/storefront/{store}/track-order` | `StorefrontController@trackOrder` | **60/min** | `trackOrder(store, num, phone)` | No |

---

## 2. Information Leakage Verification

Public API serialization was evaluated across all storefront resources:

1. **`StoreResource` (`backend/app/Http/Resources/Storefront/StoreResource.php`):**
   - Serializes: `id`, `name`, `slug`, `domain`, `subdomain`, `status`, `locale`, `currency`, `settings`, `store_setting`, `theme_setting`.
   - **`tenant_id` is NOT leaked.**
2. **`ProductResource` (`backend/app/Http/Resources/Storefront/ProductResource.php`):**
   - Serializes public catalog attributes, options, image URLs, and variant public cards.
   - **`tenant_id` is NOT leaked.**
   - **`cost_price_minor` is strictly omitted.**
   - Inactive variants (`status != active`) are filtered out prior to serialization.
3. **`CategoryResource` (`backend/app/Http/Resources/Storefront/CategoryResource.php`):**
   - **`tenant_id` is NOT leaked.**
4. **`OrderResource` (`backend/app/Http/Resources/Storefront/OrderResource.php`):**
   - Returns customer receipt view (`order_number`, customer name/phone, delivery type, status, line items, and totals).
   - Internal tenant metadata and staff notes are omitted.

---

## 3. Commercial Authority & Input Manipulation Resistance

The storefront contract was examined for price, fee, discount, or inventory manipulation:

- **Client Totals:** `QuickCheckoutRequest` and `CheckoutPayload` (`storefront/src/lib/types.ts`) accept customer contact details, delivery address, optional coupon code, product ID or items array, and optional variant IDs.
- **Untrusted Fields:** There are no fields for `subtotal`, `total`, `discount`, `shipping_fee`, or unit price.
- **Total Calculation:** The backend recalculates every financial figure server-side from active database records.
- **Geographic Cross-Checks:** `QuickCheckoutRequest::after()` asserts that the submitted `commune_id` belongs to `wilaya_id`.
- **Variant Tampering:** Rejects variable product checkouts without variant, simple product checkouts with variant, variants belonging to other products, or variants belonging to other tenants.
- **Duplicate Prevention:** Rejects duplicate product IDs or duplicate variant IDs in the checkout items list.

---

## 4. Contract Findings

### Finding `API-001`: Unversioned Storefront API Routes
- **Severity:** MEDIUM
- **Status:** CONFIRMED IMPLEMENTED
- **Reference:** Proposed ADR 0016 (`docs/adr/0016-api-versioning-strategy.md`)
- **Description:** Storefront endpoints reside under `/api/storefront/*` without a version prefix (`/api/v1/storefront/*`).
- **Impact:** Any breaking schema changes in future iterations will disrupt existing deployed storefronts or mobile apps.
- **Recommended Fix:** Implement ADR 0016 by prefixing storefront routes with `/v1/` or configuring header-based API versioning.

### Finding `API-002`: Unimplemented Storefront Caching Policy
- **Severity:** MEDIUM
- **Status:** PARTIAL
- **Reference:** Proposed ADR 0011 (`docs/adr/0011-storefront-caching-revalidation.md`)
- **Description:** Next.js client `storefrontFetch` forces `cache: "no-store"` across all API calls, and all Next.js page components run `force-dynamic`.
- **Impact:** High load on the Laravel backend and PostgreSQL database under sustained traffic spikes.
- **Recommended Fix:** Implement Route-specific ISR/revalidation per ADR 0011 (e.g. cache home/categories for 60s, product details for 30s, keep checkout and order tracking dynamic).
