<?php

namespace App\Actions\Stores;

use App\Enums\DomainStatus;
use App\Enums\PaymentMethodType;
use App\Enums\StoreStatus;
use App\Enums\TenantStatus;
use App\Models\Domain;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ShippingRate;
use App\Models\Store;
use App\Support\Billing\SubscriptionFeatureGate;

class EvaluateStoreReadiness
{
    public function __construct(
        private readonly SubscriptionFeatureGate $featureGate,
    ) {}

    /**
     * @return array{
     *     ready: bool,
     *     checks: array<int, array{key: string, label: string, passed: bool, severity: string, message: string}>,
     *     missing_required_count: int,
     *     missing_recommended_count: int
     * }
     */
    public function handle(Store $store): array
    {
        $store->loadMissing(['tenant', 'storeSetting', 'themeSetting']);

        $checks = [
            $this->check(
                key: 'store.public_name',
                label: 'Store has a public name',
                passed: filled($store->name),
                severity: 'required',
                message: 'Set the public store name before treating the store as ready.',
            ),
            $this->check(
                key: 'store.active_status',
                label: 'Store is active',
                passed: $store->status === StoreStatus::Active,
                severity: 'required',
                message: 'The store must be active before it is considered ready.',
            ),
            $this->check(
                key: 'store.route_identifier',
                label: 'Store has a usable domain, subdomain, or slug',
                passed: $this->hasRouteIdentifier($store),
                severity: 'required',
                message: 'Configure a verified active domain, direct domain, subdomain, or slug.',
            ),
            $this->check(
                key: 'store.support_contact',
                label: 'Store has a public support contact',
                passed: $this->hasSupportContact($store),
                severity: 'required',
                message: 'Add a public phone, support phone, WhatsApp number, or public email.',
            ),
            $this->check(
                key: 'payments.cash_on_delivery',
                label: 'Cash on delivery is enabled',
                passed: $this->hasActiveCashOnDelivery($store),
                severity: 'required',
                message: 'Enable an active cash-on-delivery payment method for this tenant.',
            ),
            $this->check(
                key: 'shipping.active_rate',
                label: 'At least one shipping rate is active',
                passed: $this->hasActiveShippingRate($store),
                severity: 'required',
                message: 'Create at least one active shipping rate.',
            ),
            $this->check(
                key: 'catalog.published_product',
                label: 'At least one product is visible on the storefront',
                passed: $this->hasVisibleProduct($store),
                severity: 'required',
                message: 'Publish at least one active product for the tenant.',
            ),
            $this->check(
                key: 'tenant.operational_status',
                label: 'Tenant status allows operations',
                passed: $this->tenantStatusAllowsOperations($store),
                severity: 'required',
                message: 'Tenant status must be active or trial.',
            ),
            $this->check(
                key: 'subscription.feature_access',
                label: 'Tenant subscription allows feature access',
                passed: $this->featureGate->hasAccess($store->tenant_id),
                severity: 'required',
                message: 'An active, trialing, or grace-period subscription is required.',
            ),
            $this->check(
                key: 'theme.basic_settings',
                label: 'Active theme settings exist',
                passed: $store->themeSetting !== null,
                severity: 'recommended',
                message: 'Add active theme settings for a polished storefront.',
            ),
            $this->check(
                key: 'legal.policy_pages',
                label: 'Legal and policy content exists',
                passed: $this->hasLegalContent($store),
                severity: 'recommended',
                message: 'Add terms, privacy, returns, and shipping policy content.',
            ),
        ];

        $missingRequired = $this->missingCount($checks, 'required');
        $missingRecommended = $this->missingCount($checks, 'recommended');

        return [
            'ready' => $missingRequired === 0,
            'checks' => $checks,
            'missing_required_count' => $missingRequired,
            'missing_recommended_count' => $missingRecommended,
        ];
    }

    /**
     * @return array{key: string, label: string, passed: bool, severity: string, message: string}
     */
    private function check(string $key, string $label, bool $passed, string $severity, string $message): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'passed' => $passed,
            'severity' => $severity,
            'message' => $message,
        ];
    }

    private function hasRouteIdentifier(Store $store): bool
    {
        if (filled($store->domain) || filled($store->subdomain) || filled($store->slug)) {
            return true;
        }

        return Domain::query()
            ->withoutGlobalScope('current_tenant')
            ->where('tenant_id', $store->tenant_id)
            ->where('store_id', $store->id)
            ->where('status', DomainStatus::Active->value)
            ->whereNotNull('verified_at')
            ->exists();
    }

    private function hasSupportContact(Store $store): bool
    {
        $settings = $store->storeSetting;

        return $settings !== null && (
            filled($settings->public_phone)
            || filled($settings->support_phone)
            || filled($settings->whatsapp_phone)
            || filled($settings->public_email)
        );
    }

    private function hasActiveCashOnDelivery(Store $store): bool
    {
        return PaymentMethod::query()
            ->withoutGlobalScope('current_tenant')
            ->where('tenant_id', $store->tenant_id)
            ->where('type', PaymentMethodType::CashOnDelivery->value)
            ->where('is_active', true)
            ->exists();
    }

    private function hasActiveShippingRate(Store $store): bool
    {
        return ShippingRate::query()
            ->withoutGlobalScope('current_tenant')
            ->where('tenant_id', $store->tenant_id)
            ->where('is_active', true)
            ->exists();
    }

    private function hasVisibleProduct(Store $store): bool
    {
        return Product::query()
            ->withoutGlobalScope('current_tenant')
            ->where('tenant_id', $store->tenant_id)
            ->visibleOnStorefront()
            ->exists();
    }

    private function tenantStatusAllowsOperations(Store $store): bool
    {
        return in_array($store->tenant?->status, [TenantStatus::Active, TenantStatus::Trial], true);
    }

    private function hasLegalContent(Store $store): bool
    {
        $settings = $store->storeSetting;

        return $settings !== null
            && filled($settings->terms_content)
            && filled($settings->privacy_content)
            && filled($settings->return_policy_content)
            && filled($settings->shipping_policy_content);
    }

    /**
     * @param  array<int, array{passed: bool, severity: string}>  $checks
     */
    private function missingCount(array $checks, string $severity): int
    {
        return collect($checks)
            ->where('severity', $severity)
            ->where('passed', false)
            ->count();
    }
}
