<?php

namespace App\Http\Controllers\Catalog;

use App\Concerns\ScopesToOrganization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\ProductRequest;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    use ScopesToOrganization;

    /**
     * List the current organization's products with variant and in-stock unit counts.
     */
    public function index(Request $request): Response
    {
        $products = $this->organization($request)->products()
            ->withCount('productVariants')
            ->withCount(['serialNumbers as in_stock_count' => fn (Builder $query) => $query->where('status', 'in_stock')])
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get(['id', 'name', 'description', 'is_active']);

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
            ->withCount([
                'serialNumbers',
                'serialNumbers as in_stock_count' => fn (Builder $query) => $query->where('status', 'in_stock'),
            ])
            ->get(['id', 'product_id', 'size_id', 'color_id', 'sku', 'barcode', 'price', 'is_active'])
            ->sortBy([
                ['size.sort_order', 'asc'],
                ['size.name', 'asc'],
                ['color.sort_order', 'asc'],
                ['color.name', 'asc'],
            ])
            ->values();

        $organization = $this->organization($request);

        return Inertia::render('catalog/products/show', [
            'product' => $product->only(['id', 'name', 'description', 'is_active']),
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
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Product updated.')]);

        return back();
    }

    /**
     * Delete a product and its variants, unless any variant has ever held stock.
     */
    public function destroy(Request $request, Product $product): RedirectResponse
    {
        $this->ensureBelongsToOrganization($request, $product);

        if ($product->serialNumbers()->exists()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('This product has stock history. Deactivate it instead.')]);

            return back();
        }

        DB::transaction(function () use ($product): void {
            $product->productVariants()->delete();
            $product->delete();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Product deleted.')]);

        return to_route('catalog.products.index');
    }
}
