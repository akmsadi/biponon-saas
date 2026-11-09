<?php

namespace App\Http\Controllers\Catalog;

use App\Concerns\ScopesToOrganization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\ColorRequest;
use App\Models\Color;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ColorController extends Controller
{
    use ScopesToOrganization;

    /**
     * List the current organization's colors.
     */
    public function index(Request $request): Response
    {
        $colors = $this->organization($request)->colors()
            ->withCount('productVariants')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'hex_code', 'sort_order', 'is_active']);

        return Inertia::render('catalog/colors/index', [
            'colors' => $colors,
        ]);
    }

    /**
     * Create a color in the current organization.
     */
    public function store(ColorRequest $request): RedirectResponse
    {
        $this->organization($request)->colors()->create([
            ...$request->validated(),
            'sort_order' => $request->integer('sort_order'),
            'is_active' => $request->boolean('is_active'),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Color created.')]);

        return back();
    }

    /**
     * Update a color of the current organization.
     */
    public function update(ColorRequest $request, Color $color): RedirectResponse
    {
        $this->ensureBelongsToOrganization($request, $color);

        $color->update([
            ...$request->validated(),
            'sort_order' => $request->integer('sort_order'),
            'is_active' => $request->boolean('is_active'),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Color updated.')]);

        return back();
    }

    /**
     * Delete a color that no product variant uses.
     */
    public function destroy(Request $request, Color $color): RedirectResponse
    {
        $this->ensureBelongsToOrganization($request, $color);

        if ($color->productVariants()->exists()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('This color is used by product variants. Deactivate it instead.')]);

            return back();
        }

        $color->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Color deleted.')]);

        return back();
    }
}
