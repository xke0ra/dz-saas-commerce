<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PlanFeatureKey: string implements HasLabel
{
    case MaxProducts = 'max_products';
    case MaxOrdersPerMonth = 'max_orders_per_month';
    case MaxStaffUsers = 'max_staff_users';
    case MaxImagesPerProduct = 'max_images_per_product';
    case CustomDomain = 'custom_domain';
    case AdvancedAnalytics = 'advanced_analytics';
    case Coupons = 'coupons';
    case AbandonedCart = 'abandoned_cart';
    case ApiAccess = 'api_access';
    case MultiWarehouse = 'multi_warehouse';
    case PremiumThemes = 'premium_themes';

    public function getLabel(): ?string
    {
        return str($this->value)
            ->replace('_', ' ')
            ->headline()
            ->toString();
    }
}
