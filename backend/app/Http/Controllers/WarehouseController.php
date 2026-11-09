<?php

namespace App\Http\Controllers;

use App\Concerns\ScopesToOrganization;
use App\Http\Requests\WarehouseRequest;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WarehouseController extends Controller
{
    use ScopesToOrganization;

    /**
     * List the current organization's warehouses with their in-stock unit counts.
     */
    public function index(Request $request): Response
    {
        $warehouses = $this->organization($request)->warehouses()
            ->withCount(['serialNumbers as in_stock_count' => fn (Builder $query) => $query->where('status', 'in_stock')])
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'address_line', 'city', 'country_code', 'is_active']);

        return Inertia::render('warehouses/index', [
            'warehouses' => $warehouses,
        ]);
    }

    /**
     * Create a warehouse in the current organization.
     */
    public function store(WarehouseRequest $request): RedirectResponse
    {
        $this->organization($request)->warehouses()->create([
            ...$request->validated(),
            'is_active' => $request->boolean('is_active'),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Warehouse created.')]);

        return back();
    }

    /**
     * Update a warehouse of the current organization. Deactivating one that still holds stock is allowed.
     */
    public function update(WarehouseRequest $request, Warehouse $warehouse): RedirectResponse
    {
        $this->ensureBelongsToOrganization($request, $warehouse);

        $warehouse->update([
            ...$request->validated(),
            'is_active' => $request->boolean('is_active'),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Warehouse updated.')]);

        return back();
    }

    /**
     * Delete a warehouse that has never held stock.
     */
    public function destroy(Request $request, Warehouse $warehouse): RedirectResponse
    {
        $this->ensureBelongsToOrganization($request, $warehouse);

        if ($this->hasStockHistory($warehouse)) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('This warehouse has stock or movement history. Deactivate it instead.')]);

            return back();
        }

        $warehouse->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Warehouse deleted.')]);

        return back();
    }

    private function hasStockHistory(Warehouse $warehouse): bool
    {
        return $warehouse->serialNumbers()->exists()
            || $warehouse->fromStockMovements()->exists()
            || $warehouse->toStockMovements()->exists();
    }
}
