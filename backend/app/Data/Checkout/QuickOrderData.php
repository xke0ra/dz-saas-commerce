<?php

namespace App\Data\Checkout;

use App\Enums\DeliveryType;

class QuickOrderData
{
    /**
     * @param  array<int, array{product_id: string, quantity: int}>  $items
     */
    public function __construct(
        public readonly string $fullName,
        public readonly string $phone,
        public readonly int $wilayaId,
        public readonly int $communeId,
        public readonly string $address,
        public readonly DeliveryType $deliveryType,
        public readonly array $items,
        public readonly ?string $couponCode = null,
        public readonly ?string $note = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $items = $data['items'] ?? [[
            'product_id' => $data['product_id'],
            'quantity' => $data['quantity'],
        ]];

        return new self(
            fullName: $data['full_name'],
            phone: $data['phone'],
            wilayaId: (int) $data['wilaya_id'],
            communeId: (int) $data['commune_id'],
            address: $data['address'],
            deliveryType: DeliveryType::from($data['delivery_type']),
            items: array_map(fn (array $item): array => [
                'product_id' => $item['product_id'],
                'quantity' => (int) $item['quantity'],
            ], $items),
            couponCode: $data['coupon_code'] ?? null,
            note: $data['note'] ?? null,
        );
    }
}
