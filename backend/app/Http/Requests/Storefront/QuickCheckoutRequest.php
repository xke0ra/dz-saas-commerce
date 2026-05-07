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
            'quantity' => ['required_with:product_id', 'integer', 'min:1', 'max:99'],
            'items' => ['required_without:product_id', 'array', 'min:1', 'max:50'],
            'items.*.product_id' => ['required_with:items', 'string', 'exists:products,id'],
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
            },
        ];
    }
}
