<?php

namespace App\Http\Resources\Storefront;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'status' => $this->status?->value,
            'payment_status' => $this->payment_status?->value,
            'delivery_type' => $this->delivery_type?->value,
            'coupon' => $this->whenLoaded('coupon', fn (): ?array => $this->coupon ? [
                'id' => $this->coupon->id,
                'code' => $this->coupon->code,
                'name' => $this->coupon->name,
            ] : null),
            'subtotal_minor' => $this->subtotal_minor,
            'shipping_fee_minor' => $this->shipping_fee_minor,
            'discount_minor' => $this->discount_minor,
            'total_minor' => $this->total_minor,
            'currency' => $this->currency,
            'customer' => $this->whenLoaded('customer', fn (): array => [
                'full_name' => $this->customer->full_name,
                'phone' => $this->customer->phone,
            ]),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item): array => [
                'product_id' => $item->product_id,
                'product_name' => $item->product_name,
                'product_sku' => $item->product_sku,
                'quantity' => $item->quantity,
                'unit_price_minor' => $item->unit_price_minor,
                'total_minor' => $item->total_minor,
            ])),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
