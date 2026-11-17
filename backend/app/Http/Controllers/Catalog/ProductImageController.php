<?php

namespace App\Http\Controllers\Catalog;

use App\Concerns\ScopesToOrganization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\StoreProductImagesRequest;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * A product's online shop photos: add, choose the main one, delete. Managers and admins only (enforced by the routes).
 */
class ProductImageController extends Controller
{
    use ScopesToOrganization;

    /**
     * Store the uploaded photos after the product's existing ones.
     */
    public function store(StoreProductImagesRequest $request, Product $product): RedirectResponse
    {
        $this->ensureBelongsToOrganization($request, $product);

        $nextOrder = (int) $product->images()->max('sort_order') + 1;

        /** @var array<int, UploadedFile> $files */
        $files = $request->file('images');

        foreach ($files as $file) {
            $product->images()->create([
                'organization_id' => $product->organization_id,
                'path' => $file->store(ProductImage::folderFor($product), ProductImage::DISK),
                'sort_order' => $nextOrder++,
            ]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => trans_choice('{1} Photo added.|[2,*] :count photos added.', count($files))]);

        return back();
    }

    /**
     * Make a photo the product's main photo by moving it to the front; the others keep their order behind it.
     */
    public function makeMain(Request $request, ProductImage $productImage): RedirectResponse
    {
        $this->ensureBelongsToOrganization($request, $productImage);

        DB::transaction(function () use ($productImage): void {
            $others = $productImage->product->images()->whereKeyNot($productImage->id)->get();

            $productImage->update(['sort_order' => 0]);
            $others->values()->each(fn (ProductImage $image, int $index) => $image->update(['sort_order' => $index + 1]));
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Main photo changed.')]);

        return back();
    }

    /**
     * Delete a photo and its file.
     */
    public function destroy(Request $request, ProductImage $productImage): RedirectResponse
    {
        $this->ensureBelongsToOrganization($request, $productImage);

        $productImage->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Photo deleted.')]);

        return back();
    }
}
