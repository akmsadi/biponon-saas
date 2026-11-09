<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\SizeRequest;
use App\Models\Organization;
use App\Models\Size;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SizeController extends Controller
{
    /**
     * List the current organization's sizes.
     */
    public function index(Request $request): Response
    {
        $sizes = $this->organization($request)->sizes()
            ->withCount('productVariants')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'sort_order', 'is_active']);

        return Inertia::render('catalog/sizes/index', [
            'sizes' => $sizes,
        ]);
    }

    /**
     * Create a size in the current organization.
     */
    public function store(SizeRequest $request): RedirectResponse
    {
        $this->organization($request)->sizes()->create([
            ...$request->validated(),
            'sort_order' => $request->integer('sort_order'),
            'is_active' => $request->boolean('is_active'),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Size created.')]);

        return back();
    }

    /**
     * Update a size of the current organization.
     */
    public function update(SizeRequest $request, Size $size): RedirectResponse
    {
        $this->ensureBelongsToOrganization($request, $size);

        $size->update([
            ...$request->validated(),
            'sort_order' => $request->integer('sort_order'),
            'is_active' => $request->boolean('is_active'),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Size updated.')]);

        return back();
    }

    /**
     * Delete a size that no product variant uses.
     */
    public function destroy(Request $request, Size $size): RedirectResponse
    {
        $this->ensureBelongsToOrganization($request, $size);

        if ($size->productVariants()->exists()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('This size is used by product variants. Deactivate it instead.')]);

            return back();
        }

        $size->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Size deleted.')]);

        return back();
    }

    private function organization(Request $request): Organization
    {
        return $request->attributes->get('organization');
    }

    private function ensureBelongsToOrganization(Request $request, Size $size): void
    {
        abort_unless($size->organization_id === $this->organization($request)->id, 404);
    }
}
