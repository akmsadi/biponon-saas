<?php

namespace App\Http\Controllers\Catalog;

use App\Concerns\ScopesToOrganization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\StoreProductVariantsRequest;
use App\Http\Requests\Catalog\UpdateProductVariantRequest;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ProductVariantController extends Controller
{
    use ScopesToOrganization;

    /**
     * Create a batch of variants for a product in one transaction.
     */
    public function store(StoreProductVariantsRequest $request, Product $product): RedirectResponse
    {
        $this->ensureBelongsToOrganization($request, $product);

        $variants = $request->validated('variants');

        DB::transaction(function () use ($product, $variants, $request): void {
            foreach ($variants as $variant) {
                $product->productVariants()->create([
                    'organization_id' => $product->organization_id,
                    'size_id' => $variant['size_id'],
                    'color_id' => $variant['color_id'],
                    'sku' => $variant['sku'],
                    'barcode' => $variant['barcode'] ?? null,
                    'price' => $request->validated('price'),
                    'is_active' => true,
                ]);
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => trans_choice(':count variant added.|:count variants added.', count($variants))]);

        return back();
    }

    /**
     * Update a variant's SKU, barcode, price and status. Its size and color never change.
     */
    public function update(UpdateProductVariantRequest $request, ProductVariant $variant): RedirectResponse
    {
        $this->ensureBelongsToOrganization($request, $variant);

        $variant->update([
            ...$request->validated(),
            'is_active' => $request->boolean('is_active'),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Variant updated.')]);

        return back();
    }

    /**
     * Delete a variant that has never held stock.
     */
    public function destroy(Request $request, ProductVariant $variant): RedirectResponse
    {
        $this->ensureBelongsToOrganization($request, $variant);

        if ($variant->hasStockHistory()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('This variant has stock history. Deactivate it instead.')]);

            return back();
        }

        $variant->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Variant deleted.')]);

        return back();
    }
}
