<?php

namespace App\Providers;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\Domain;
use App\Models\FailedDeliveryReason;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Shipment;
use App\Models\ShippingCompany;
use App\Models\ShippingRate;
use App\Models\Store;
use App\Models\StoreSetting;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\SupportTicket;
use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Models\TenantUser;
use App\Models\ThemeSetting;
use App\Models\User;
use App\Policies\AuditLogPolicy;
use App\Policies\CategoryPolicy;
use App\Policies\CouponPolicy;
use App\Policies\CustomerPolicy;
use App\Policies\DomainPolicy;
use App\Policies\FailedDeliveryReasonPolicy;
use App\Policies\InventoryItemPolicy;
use App\Policies\InvoicePolicy;
use App\Policies\OrderPolicy;
use App\Policies\OrderReturnPolicy;
use App\Policies\PaymentMethodPolicy;
use App\Policies\PaymentPolicy;
use App\Policies\ProductImagePolicy;
use App\Policies\ProductPolicy;
use App\Policies\ShipmentPolicy;
use App\Policies\ShippingCompanyPolicy;
use App\Policies\ShippingRatePolicy;
use App\Policies\StorePolicy;
use App\Policies\StoreSettingPolicy;
use App\Policies\SubscriptionPaymentPolicy;
use App\Policies\SubscriptionPolicy;
use App\Policies\SupportTicketPolicy;
use App\Policies\TenantInvitationPolicy;
use App\Policies\TenantPolicy;
use App\Policies\TenantUserPolicy;
use App\Policies\ThemeSettingPolicy;
use App\Support\Auth\TwoFactorAuthentication;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(CurrentTenant::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(AuditLog::class, AuditLogPolicy::class);
        Gate::policy(Tenant::class, TenantPolicy::class);
        Gate::policy(TenantInvitation::class, TenantInvitationPolicy::class);
        Gate::policy(TenantUser::class, TenantUserPolicy::class);
        Gate::policy(Store::class, StorePolicy::class);
        Gate::policy(StoreSetting::class, StoreSettingPolicy::class);
        Gate::policy(ThemeSetting::class, ThemeSettingPolicy::class);
        Gate::policy(Category::class, CategoryPolicy::class);
        Gate::policy(Coupon::class, CouponPolicy::class);
        Gate::policy(Domain::class, DomainPolicy::class);
        Gate::policy(Product::class, ProductPolicy::class);
        Gate::policy(InventoryItem::class, InventoryItemPolicy::class);
        Gate::policy(ProductImage::class, ProductImagePolicy::class);
        Gate::policy(Customer::class, CustomerPolicy::class);
        Gate::policy(Order::class, OrderPolicy::class);
        Gate::policy(Payment::class, PaymentPolicy::class);
        Gate::policy(Subscription::class, SubscriptionPolicy::class);
        Gate::policy(Invoice::class, InvoicePolicy::class);
        Gate::policy(SubscriptionPayment::class, SubscriptionPaymentPolicy::class);
        Gate::policy(ShippingRate::class, ShippingRatePolicy::class);
        Gate::policy(PaymentMethod::class, PaymentMethodPolicy::class);
        Gate::policy(ShippingCompany::class, ShippingCompanyPolicy::class);
        Gate::policy(FailedDeliveryReason::class, FailedDeliveryReasonPolicy::class);
        Gate::policy(Shipment::class, ShipmentPolicy::class);
        Gate::policy(OrderReturn::class, OrderReturnPolicy::class);
        Gate::policy(SupportTicket::class, SupportTicketPolicy::class);

        Gate::before(function (User $user, string $ability, array $arguments = []): ?bool {
            if (! $user->isSuperAdmin()) {
                return null;
            }

            $subject = $arguments[0] ?? null;

            if (
                ($subject === AuditLog::class || $subject instanceof AuditLog)
                && in_array($ability, ['create', 'update', 'delete', 'deleteAny', 'forceDelete', 'forceDeleteAny', 'restore', 'restoreAny', 'replicate', 'reorder'], true)
            ) {
                return null;
            }

            return true;
        });

        Event::listen(Logout::class, function (): void {
            app(TwoFactorAuthentication::class)->forgetSession(request());
        });
    }
}
