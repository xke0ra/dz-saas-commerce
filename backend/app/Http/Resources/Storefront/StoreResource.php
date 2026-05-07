<?php

namespace App\Http\Resources\Storefront;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoreResource extends JsonResource
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
            'tenant_id' => $this->tenant_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'domain' => $this->domain,
            'subdomain' => $this->subdomain,
            'status' => $this->status?->value,
            'locale' => $this->locale,
            'currency' => $this->currency,
            'settings' => $this->settings ?? [],
            'store_setting' => StoreSettingResource::make($this->whenLoaded('storeSetting')),
            'theme_setting' => ThemeSettingResource::make($this->whenLoaded('themeSetting')),
        ];
    }
}
