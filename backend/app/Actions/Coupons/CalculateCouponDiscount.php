<?php

namespace App\Actions\Coupons;

use App\Data\Coupons\CouponDiscount;
use App\Enums\PlanFeatureKey;
use App\Models\Coupon;
use App\Support\Billing\SubscriptionFeatureGate;
use Illuminate\Validation\ValidationException;

class CalculateCouponDiscount
{
    public function __construct(private readonly SubscriptionFeatureGate $featureGate) {}

    public function handle(string $tenantId, ?string $couponCode, int $subtotalMinor): ?CouponDiscount
    {
        $couponCode = $this->normalizeCode($couponCode);

        if ($couponCode === null) {
            return null;
        }

        if (! $this->featureGate->enabled($tenantId, PlanFeatureKey::Coupons)) {
            throw ValidationException::withMessages([
                'coupon_code' => 'Coupons are not available on the current subscription plan.',
            ]);
        }

        $coupon = Coupon::query()
            ->withoutGlobalScope('current_tenant')
            ->where('tenant_id', $tenantId)
            ->where('code', $couponCode)
            ->lockForUpdate()
            ->first();

        if ($coupon === null || ! $coupon->isCurrentlyActive()) {
            throw ValidationException::withMessages([
                'coupon_code' => 'The selected coupon is invalid or inactive.',
            ]);
        }

        if (! $coupon->hasAvailableUsage()) {
            throw ValidationException::withMessages([
                'coupon_code' => 'The selected coupon usage limit has been reached.',
            ]);
        }

        if ($subtotalMinor < $coupon->minimum_subtotal_minor) {
            throw ValidationException::withMessages([
                'coupon_code' => 'The order subtotal is below the selected coupon minimum.',
            ]);
        }

        $discount = $coupon->calculateDiscount($subtotalMinor);

        if ($discount <= 0) {
            throw ValidationException::withMessages([
                'coupon_code' => 'The selected coupon does not apply to this order.',
            ]);
        }

        return new CouponDiscount($coupon, $discount);
    }

    private function normalizeCode(?string $couponCode): ?string
    {
        if ($couponCode === null) {
            return null;
        }

        $couponCode = trim($couponCode);

        return $couponCode === '' ? null : str($couponCode)->upper()->toString();
    }
}
