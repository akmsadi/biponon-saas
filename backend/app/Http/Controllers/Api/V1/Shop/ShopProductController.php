<?php

namespace App\Http\Controllers\Api\V1\Shop;

use App\Http\Controllers\Controller;
use App\Http\Resources\Shop\ShopProductCardResource;
use App\Http\Resources\Shop\ShopProductResource;
use App\Models\Organization;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The products an open shop lists. A closed shop lists nothing (404).
 */
class ShopProductController extends Controller
{
    public const PER_PAGE = 24;

    /**
     * The product grid, alphabetically, with an optional name search.
     */
    public function index(Request $request, Organization $shop): JsonResponse
    {
        abort_unless($shop->shopIsOpen(), 404, __('This shop is closed.'));

        $search = trim((string) $request->query('search'));

        $products = $shop->products()
            ->listedInShop()
            ->when($search !== '', fn (Builder $query) => $query->where('name', 'like', '%'.addcslashes($search, '%_\\').'%'))
            ->with([
                'images',
                'productVariants' => fn ($variants) => $variants->orderableInShop()->withShopStock(),
            ])
            ->orderBy('name')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return ShopProductCardResource::collection($products)
            ->response()
            ->header('Cache-Control', 'public, max-age=60');
    }

    /**
     * One product page, found by its slug.
     */
    public function show(Organization $shop, string $productSlug): JsonResponse
    {
        abort_unless($shop->shopIsOpen(), 404, __('This shop is closed.'));

        $product = $shop->products()
            ->listedInShop()
            ->where('slug', $productSlug)
            ->with([
                'images',
                'productVariants' => fn ($variants) => $variants->orderableInShop()->withShopStock()->with([
                    'size:id,name,sort_order',
                    'color:id,name,hex_code,sort_order',
                ]),
            ])
            ->firstOrFail();

        $product->setRelation('productVariants', $product->productVariants->sortBy([
            fn (ProductVariant $a, ProductVariant $b) => [$a->size->sort_order, $a->size->name, $a->color->sort_order, $a->color->name]
                <=> [$b->size->sort_order, $b->size->name, $b->color->sort_order, $b->color->name],
        ])->values());

        return (new ShopProductResource($product))
            ->response()
            ->header('Cache-Control', 'public, max-age=60');
    }
}
