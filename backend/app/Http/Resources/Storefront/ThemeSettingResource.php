<?php

namespace App\Http\Resources\Storefront;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ThemeSettingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'theme_name' => $this->theme_name,
            'primary_color' => $this->primary_color,
            'accent_color' => $this->accent_color,
            'background_color' => $this->background_color,
            'foreground_color' => $this->foreground_color,
            'heading_font' => $this->heading_font,
            'body_font' => $this->body_font,
            'logo_path' => $this->logo_path,
            'favicon_path' => $this->favicon_path,
            'hero_image_path' => $this->hero_image_path,
            'hero_title' => $this->hero_title,
            'hero_subtitle' => $this->hero_subtitle,
            'product_card_style' => $this->product_card_style,
            'layout_settings' => $this->layout_settings ?? [],
        ];
    }
}
