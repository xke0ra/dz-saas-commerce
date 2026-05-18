<?php

namespace App\Http\Requests\Storefront;

use App\Enums\DeliveryType;
use App\Models\Commune;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class QuickCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $phone = $this->input('phone');

        if (is_string($phone)) {
            $this->merge([
                'phone' => preg_replace('/[\s.\-]/', '', $phone),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'regex:/^(\\+213|0)(5|6|7)[0-9]{8}$/'],
            'wilaya_id' => ['required', 'integer', 'exists:wilayas,id'],
            'commune_id' => ['required', 'integer', 'exists:communes,id'],
            'address' => ['required', 'string', 'max:1000'],
            'delivery_type' => ['required', Rule::enum(DeliveryType::class)],
            'coupon_code' => ['nullable', 'string', 'max:64'],
            'note' => ['nullable', 'string', 'max:1000'],
            'product_id' => ['required_without:items', 'string', 'exists:products,id'],
            'product_variant_id' => ['nullable', 'string', 'ulid', 'exists:product_variants,id'],
            'quantity' => ['required_with:product_id', 'integer', 'min:1', 'max:99'],
            'items' => ['required_without:product_id', 'array', 'min:1', 'max:50'],
            'items.*.product_id' => ['required_with:items', 'string', 'exists:products,id'],
            'items.*.product_variant_id' => ['nullable', 'string', 'ulid', 'exists:product_variants,id'],
            'items.*.quantity' => ['required_with:items', 'integer', 'min:1', 'max:99'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $commune = Commune::query()->find($this->integer('commune_id'));

                if ($commune !== null && $commune->wilaya_id !== $this->integer('wilaya_id')) {
                    $validator->errors()->add('commune_id', 'The selected commune does not belong to the selected wilaya.');
                }

                $this->validateSellableUnitDuplicates($validator);
            },
        ];
    }

    private function validateSellableUnitDuplicates(Validator $validator): void
    {
        $items = $this->input('items');

        if (! is_array($items)) {
            return;
        }

        $parentProducts = [];
        $variantProducts = [];
        $variants = [];

        foreach ($items as $index => $item) {
            if (! is_array($item)) {
                continue;
            }

            $productId = $item['product_id'] ?? null;

            if (! is_string($productId) || $productId === '') {
                continue;
            }

            $variantId = $item['product_variant_id'] ?? null;
            $variantId = is_string($variantId) && $variantId !== '' ? $variantId : null;

            if ($variantId === null) {
                if (isset($variantProducts[$productId])) {
                    $validator->errors()->add('items', 'Cart cannot mix parent product and variants for the same product.');

                    continue;
                }

                if (isset($parentProducts[$productId])) {
                    $validator->errors()->add("items.{$index}.product_id", 'Duplicate products are not allowed in the same checkout.');

                    continue;
                }

                $parentProducts[$productId] = true;

                continue;
            }

            if (isset($parentProducts[$productId])) {
                $validator->errors()->add('items', 'Cart cannot mix parent product and variants for the same product.');

                continue;
            }

            if (isset($variants[$variantId])) {
                $validator->errors()->add("items.{$index}.product_variant_id", 'Duplicate product variants are not allowed in the same checkout.');

                continue;
            }

            $variantProducts[$productId] = true;
            $variants[$variantId] = true;
        }
    }
}
