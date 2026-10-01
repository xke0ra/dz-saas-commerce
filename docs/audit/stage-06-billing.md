# Stage 6: Billing and SaaS Control Plane Audit

**Audit Date:** 2026-10-01  
**Audit Branch:** `main`  
**Audit Commit:** `bd58620db449ac6211584eb0dc0b125ac3656fb8`  

---

## 1. SaaS Control Plane Architecture

The multi-tenant billing engine governs tenant subscription tiers, usage quotas, feature gating, invoice issuance, and account lifecycle states:
- **`Plan` & `PlanFeature`:** Platform plans (e.g. Free, Starter, Pro, Enterprise) with associated feature keys (`PlanFeatureKey`: `MaxProducts`, `MaxOrdersPerMonth`, `MaxStaffUsers`, `CustomDomain`, `AdvancedAnalytics`, `CouponsEnabled`, etc.).
- **`Subscription`:** Tenant-owned model tracking `status` (`Trialing`, `Active`, `GracePeriod`, `PastDue`, `Suspended`, `Cancelled`), `current_period_starts_at`, `current_period_ends_at`, `grace_period_ends_at`, and `is_current`.
- **`Invoice`:** Financial records tracking `status` (`Issued`, `Paid`, `PartiallyPaid`, `Overdue`, `Cancelled`), billing period dates, due date, subtotal, discount, total, and payment allocation.
- **`SubscriptionPayment`:** Manual payment proof submissions (bank slip/CCP/BaridiMob reference) confirmed or rejected by platform administrators.
- **`UsageCounter`:** Aggregates periodic usage for generic features.

---

## 2. Feature Gating & Enforcement Points

The `SubscriptionFeatureGate` service (`backend/app/Support/Billing/SubscriptionFeatureGate.php`) acts as the centralized platform enforcement gate:

| Protected Resource / Action | Feature Key Checked | Enforcement Location | Action When Limit Exceeded |
| :--- | :--- | :--- | :--- |
| **Storefront Order Creation** | `MaxOrdersPerMonth` | `CreateQuickOrder.php` line 41 | Throws `ValidationException` rejecting customer checkout |
| **Product Creation** | `MaxProducts` | `ProductPolicy.php` line 33 | Policy denies create authorization |
| **Staff Member Invitation** | `MaxStaffUsers` | `TenantUserPolicy.php` line 33, `InviteTenantUser.php` | Denies invitation creation |
| **Custom Domain Attachment** | `CustomDomain` | `DomainPolicy.php` line 50 | Denies adding custom domain |
| **Advanced Vendor Analytics** | `AdvancedAnalytics` | `CanViewAnalyticsWidgets.php` | Hides advanced chart and statistics widgets |
| **Coupon Discount Application** | `Coupons` | `CalculateCouponDiscount.php` | Ignores/rejects coupon code during checkout |

**Finding:** Feature limits cannot be bypassed through the vendor panel or storefront checkout, as checks are embedded in policies and transactional domain actions.

---

## 3. Subscription Lifecycle & Automation

Automated background processing is orchestrated by `ProcessBillingLifecycle` (`backend/app/Actions/Billing/ProcessBillingLifecycle.php`), scheduled daily at 02:00 in `bootstrap/app.php`:
1. **Overdue Marking:** Transitions unpaid invoices past `due_at` from `Issued` to `Overdue`. Transitions associated current subscriptions to `PastDue`. Writes audit log events.
2. **Renewal Invoicing:** Dispatches renewal invoices in advance of period end via `IssueSubscriptionInvoice`.
3. **Renewal Reminders:** Dispatches `SubscriptionRenewalReminderNotification` to tenant owners.
4. **Period Expiration & Suspension:**
   - Moves expired subscriptions into `GracePeriod`.
   - Once grace period expires without confirmed payment, transitions subscription to `Suspended`.
   - Automatically cascades suspension to the tenant (`tenants.status = Suspended`) and its storefronts (`stores.status = Suspended`).
   - Storefront immediately becomes unavailable (404/Unavailable rendered via `StorefrontController::abortIfStorefrontUnavailable`).

---

## 4. Manual Payment Flow

Per ADR 0009 ("Manual payments first"):
1. Tenant owner submits payment reference and optional receipt image (`SubscriptionPaymentResource`).
2. Super Admin reviews submission in `/admin` panel.
3. Super Admin triggers `ConfirmSubscriptionPayment`:
   - Validates amount against open invoices under database lock.
   - Marks payment as `Confirmed`.
   - Marks invoice as `Paid`.
   - Extends subscription `current_period_ends_at` and restores `status = Active`.
   - Restores tenant and store status if previously suspended.
   - Writes immutable `AuditLog` entry.
4. Alternatively, Super Admin triggers `RejectSubscriptionPayment` with a mandatory rejection reason.

---

## 5. Billing Domain Findings

### Finding `BILL-001`: Manual Verification for SaaS Subscriptions
- **Severity:** INFO (Documented Design Direction)
- **Status:** CONFIRMED IMPLEMENTED
- **Reference:** ADR 0009
- **Description:** SaaS subscription renewals and upgrades currently rely on manual bank transfer / CCP payment receipt verification by super administrators rather than automated recurring credit card or debit charges.
- **Impact:** Administrative overhead for platform operators; scaling to thousands of merchants will require integrating an automated payment gateway.
