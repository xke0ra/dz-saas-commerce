<?php

namespace App\Http\Controllers\Api\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Resources\Storefront\CommuneResource;
use App\Http\Resources\Storefront\WilayaResource;
use App\Models\Commune;
use App\Models\Wilaya;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class GeographyController extends Controller
{
    public function wilayas(): AnonymousResourceCollection
    {
        $wilayas = Wilaya::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->get();

        return WilayaResource::collection($wilayas);
    }

    public function communes(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'wilaya_id' => ['required', 'integer', 'exists:wilayas,id'],
        ]);

        $communes = Commune::query()
            ->where('wilaya_id', $validated['wilaya_id'])
            ->where('is_active', true)
            ->orderBy('name_fr')
            ->get();

        return CommuneResource::collection($communes);
    }
}
