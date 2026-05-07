<?php

namespace App\Http\Resources\Storefront;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommuneResource extends JsonResource
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
            'wilaya_id' => $this->wilaya_id,
            'name_ar' => $this->name_ar,
            'name_fr' => $this->name_fr,
            'postal_code' => $this->postal_code,
        ];
    }
}
