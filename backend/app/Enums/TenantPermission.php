<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum TenantPermission: string implements HasLabel
{
    case StoresView = 'stores.view';
    case StoresCreate = 'stores.create';
    case StoresUpdate = 'stores.update';
    case StoresDelete = 'stores.delete';

    case CategoriesView = 'categories.view';
    case CategoriesCreate = 'categories.create';
    case CategoriesUpdate = 'categories.update';
    case CategoriesDelete = 'categories.delete';

    case ProductsView = 'products.view';
    case ProductsCreate = 'products.create';
    case ProductsUpdate = 'products.update';
    case ProductsDelete = 'products.delete';

    case ProductImagesView = 'product_images.view';
    case ProductImagesCreate = 'product_images.create';
    case ProductImagesUpdate = 'product_images.update';
    case ProductImagesDelete = 'product_images.delete';

    case InventoryView = 'inventory.view';
    case InventoryCreate = 'inventory.create';
    case InventoryUpdate = 'inventory.update';
    case InventoryDelete = 'inventory.delete';

    case CustomersView = 'customers.view';
    case CustomersCreate = 'customers.create';
    case CustomersUpdate = 'customers.update';
    case CustomersDelete = 'customers.delete';

    case OrdersView = 'orders.view';
    case OrdersUpdate = 'orders.update';
    case OrdersConfirm = 'orders.confirm';
    case OrdersCancel = 'orders.cancel';
    case OrdersShip = 'orders.ship';
    case PaymentsManage = 'payments.manage';

    case PaymentMethodsView = 'payment_methods.view';
    case PaymentMethodsManage = 'payment_methods.manage';

    case CouponsView = 'coupons.view';
    case CouponsManage = 'coupons.manage';

    case DomainsView = 'domains.view';
    case DomainsManage = 'domains.manage';

    case ShippingCompaniesView = 'shipping_companies.view';
    case ShippingCompaniesManage = 'shipping_companies.manage';
    case ShippingRatesView = 'shipping_rates.view';
    case ShippingRatesManage = 'shipping_rates.manage';
    case FailedDeliveryReasonsView = 'failed_delivery_reasons.view';
    case FailedDeliveryReasonsManage = 'failed_delivery_reasons.manage';
    case ShipmentsView = 'shipments.view';
    case ShipmentsCreate = 'shipments.create';
    case ShipmentsUpdate = 'shipments.update';

    case ReturnsView = 'returns.view';
    case ReturnsCreate = 'returns.create';
    case ReturnsUpdate = 'returns.update';

    case BillingManage = 'billing.manage';
    case AnalyticsView = 'analytics.view';
    case StaffManage = 'staff.manage';
    case SupportTicketsView = 'support_tickets.view';
    case SupportTicketsCreate = 'support_tickets.create';
    case SupportTicketsUpdate = 'support_tickets.update';

    public function getLabel(): ?string
    {
        return str($this->value)
            ->replace(['_', '.'], [' ', ': '])
            ->headline()
            ->toString();
    }

    /**
     * @return array<int, self>
     */
    public static function defaultsForRole(TenantRole $role): array
    {
        return match ($role) {
            TenantRole::Owner => self::cases(),
            TenantRole::StoreAdmin => [
                self::StoresView,
                self::StoresUpdate,
                self::CategoriesView,
                self::CategoriesCreate,
                self::CategoriesUpdate,
                self::CategoriesDelete,
                self::ProductsView,
                self::ProductsCreate,
                self::ProductsUpdate,
                self::ProductsDelete,
                self::ProductImagesView,
                self::ProductImagesCreate,
                self::ProductImagesUpdate,
                self::ProductImagesDelete,
                self::InventoryView,
                self::InventoryCreate,
                self::InventoryUpdate,
                self::InventoryDelete,
                self::CustomersView,
                self::CustomersCreate,
                self::CustomersUpdate,
                self::CustomersDelete,
                self::OrdersView,
                self::OrdersUpdate,
                self::OrdersConfirm,
                self::OrdersCancel,
                self::OrdersShip,
                self::PaymentsManage,
                self::PaymentMethodsView,
                self::PaymentMethodsManage,
                self::CouponsView,
                self::CouponsManage,
                self::DomainsView,
                self::DomainsManage,
                self::ShippingCompaniesView,
                self::ShippingCompaniesManage,
                self::ShippingRatesView,
                self::ShippingRatesManage,
                self::FailedDeliveryReasonsView,
                self::FailedDeliveryReasonsManage,
                self::ShipmentsView,
                self::ShipmentsCreate,
                self::ShipmentsUpdate,
                self::ReturnsView,
                self::ReturnsCreate,
                self::ReturnsUpdate,
                self::AnalyticsView,
                self::SupportTicketsView,
                self::SupportTicketsCreate,
                self::SupportTicketsUpdate,
            ],
            TenantRole::StoreStaff => [
                self::StoresView,
                self::CategoriesView,
                self::ProductsView,
                self::ProductImagesView,
                self::InventoryView,
                self::CustomersView,
                self::CustomersCreate,
                self::CustomersUpdate,
                self::OrdersView,
                self::OrdersUpdate,
                self::OrdersConfirm,
                self::OrdersCancel,
                self::OrdersShip,
                self::PaymentsManage,
                self::ShippingCompaniesView,
                self::ShippingRatesView,
                self::FailedDeliveryReasonsView,
                self::ShipmentsView,
                self::ShipmentsCreate,
                self::ShipmentsUpdate,
                self::ReturnsView,
                self::ReturnsCreate,
                self::ReturnsUpdate,
                self::SupportTicketsView,
                self::SupportTicketsCreate,
            ],
        };
    }

    public static function defaultAllows(TenantRole $role, self $permission): bool
    {
        return in_array($permission, self::defaultsForRole($role), true);
    }
}
