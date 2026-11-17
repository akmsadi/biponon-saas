<?php

namespace App\Http\Controllers\Catalog;

use App\Concerns\ScopesToOrganization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\ProductRequest;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    use ScopesToOrganization;

    /**
     * List the current organization's products with their variant count and total quantity in stock.
     */
    public function index(Request $request): Response
    {
        $products = $this->organization($request)->products()
            ->withCount('productVariants')
            ->withSum('stockBalances as in_stock_count', 'quantity')
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get(['id', 'name', 'description', 'is_active', 'show_in_shop'])
            ->each(function (Product $product): void {
                $product->in_stock_count = (int) $product->in_stock_count;
            });

        return Inertia::render('catalog/products/index', [
            'products' => $products,
        ]);
    }

    /**
     * Create a product in the current organization and open its page to add variants.
     */
    public function store(ProductRequest $request): RedirectResponse
    {
        $product = $this->organization($request)->products()->create([
            ...$request->validated(),
            'is_active' => $request->boolean('is_active'),
            'show_in_shop' => $request->boolean('show_in_shop'),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Product created. Now add its variants.')]);

        return to_route('catalog.products.show', $product);
    }

    /**
     * Show a product with its variants, plus the active sizes and colors that new variants can use.
     */
    public function show(Request $request, Product $product): Response
    {
        $this->ensureBelongsToOrganization($request, $product);

        $variants = $product->productVariants()
            ->with(['size:id,name,code,sort_order,is_active', 'color:id,name,code,hex_code,sort_order,is_active'])
            ->withSum('stockBalances as in_stock_count', 'quantity')
            ->get(['id', 'product_id', 'size_id', 'color_id', 'sku', 'barcode', 'price', 'reorder_level', 'is_active'])
            ->each(function (ProductVariant $variant): void {
                $variant->in_stock_count = (int) $variant->in_stock_count;
            })
            ->sortBy([
                ['size.sort_order', 'asc'],
                ['size.name', 'asc'],
                ['color.sort_order', 'asc'],
                ['color.name', 'asc'],
            ])
            ->values();

        $organization = $this->organization($request);

        return Inertia::render('catalog/products/show', [
            'product' => $product->only(['id', 'name', 'slug', 'description', 'is_active', 'show_in_shop']),
            'images' => $product->images->map(fn (ProductImage $image) => ['id' => $image->id, 'url' => $image->url()]),
            'maxImages' => ProductImage::MAX_PER_PRODUCT,
            'shopEnabled' => (bool) $this->organization($request)->setting('shop_enabled'),
            'variants' => $variants,
            'sizes' => $organization->sizes()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'code']),
            'colors' => $organization->colors()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'code', 'hex_code']),
            'defaultReorderLevel' => $organization->setting('default_reorder_level'),
        ]);
    }

    /**
     * Update a product of the current organization.
     */
    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        $this->ensureBelongsToOrganization($request, $product);

        $product->update([
            ...$request->validated(),
            'is_active' => $request->boolean('is_active'),
            'show_in_shop' => $request->boolean('show_in_shop'),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Product updated.')]);

        return back();
    }

    /**
     * Delete a product with its variants and photos, unless any variant has ever held stock.
     */
    public function destroy(Request $request, Product $product): RedirectResponse
    {
        $this->ensureBelongsToOrganization($request, $product);

        if ($product->hasStockHistory()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('This product has stock history. Deactivate it instead.')]);

            return back();
        }

        if ($product->productVariants()->where(fn ($query) => $query->whereHas('purchaseOrderLines')->orWhereHas('salesOrderLines'))->exists()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('This product is on purchase or sales orders. Deactivate it instead.')]);

            return back();
        }

        DB::transaction(function () use ($product): void {
            $product->productVariants()->delete();
            $product->images()->delete();
            $product->delete();
        });

        Storage::disk(ProductImage::DISK)->deleteDirectory(ProductImage::folderFor($product));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Product deleted.')]);

        return to_route('catalog.products.index');
    }
}
