<?php

namespace App\Http\Resources\Storefront;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoreSettingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'seller_name' => $this->seller_name,
            'seller_address' => $this->seller_address,
            'commercial_registration_number' => $this->commercial_registration_number,
            'tax_identification_number' => $this->tax_identification_number,
            'public_email' => $this->public_email,
            'public_phone' => $this->public_phone,
            'support_phone' => $this->support_phone,
            'whatsapp_phone' => $this->whatsapp_phone,
            'seo_title' => $this->seo_title,
            'seo_description' => $this->seo_description,
            'announcement_text' => $this->announcement_text,
            'legal_pages' => [
                'terms' => filled($this->terms_content),
                'privacy' => filled($this->privacy_content),
                'returns' => filled($this->return_policy_content),
                'shipping' => filled($this->shipping_policy_content),
            ],
            'legal_content' => [
                'terms' => $this->terms_content,
                'privacy' => $this->privacy_content,
                'returns' => $this->return_policy_content,
                'shipping' => $this->shipping_policy_content,
            ],
            'social_links' => $this->social_links ?? [],
        ];
    }
}
