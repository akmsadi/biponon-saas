<?php

namespace App\Http\Controllers\Api\V1\Shop;

use App\Http\Controllers\Controller;
use App\Http\Resources\Shop\ShopResource;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;

class ShopController extends Controller
{
    /**
     * The shop front's details, including whether it is open. A closed shop still answers, so the
     * Next.js site can show a "shop closed" page instead of a 404.
     */
    public function show(Organization $shop): JsonResponse
    {
        return (new ShopResource($shop))
            ->response()
            ->header('Cache-Control', 'public, max-age=60');
    }
}
