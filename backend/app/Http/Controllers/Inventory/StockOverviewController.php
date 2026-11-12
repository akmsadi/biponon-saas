<?php

namespace App\Http\Controllers\Inventory;

use App\Actions\Inventory\SummarizeStock;
use App\Concerns\ScopesToOrganization;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\StockBalance;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StockOverviewController extends Controller
{
    use ScopesToOrganization;

    private const PER_PAGE = 25;

    /**
     * List on-hand quantities per variant and warehouse, filtered by search, warehouse, zero stock and low stock.
     * A row is low stock when its quantity is at or below the variant's reorder level.
     */
    public function index(Request $request, SummarizeStock $summarizeStock): Response
    {
        $organization = $this->organization($request);

        $filters = [
            'search' => mb_substr(trim($request->string('search')->toString()), 0, 100),
            'warehouse' => $request->integer('warehouse') ?: null,
            'zero' => $request->boolean('zero'),
            'low' => $request->boolean('low'),
        ];

        $balances = $this->balances($organization)
            ->join('product_variants', 'product_variants.id', '=', 'stock_balances.product_variant_id')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->join('sizes', 'sizes.id', '=', 'product_variants.size_id')
            ->join('colors', 'colors.id', '=', 'product_variants.color_id')
            ->join('warehouses', 'warehouses.id', '=', 'stock_balances.warehouse_id')
            ->when($filters['low'], fn (Builder $query) => SummarizeStock::whereLowStock($query))
            ->when(! $filters['low'] && ! $filters['zero'], fn (Builder $query) => $query->where('stock_balances.quantity', '>', 0))
            ->when($filters['warehouse'], fn (Builder $query, int $warehouseId) => $query->where('stock_balances.warehouse_id', $warehouseId))
            ->when($filters['search'] !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('products.name', 'like', '%'.$filters['search'].'%')
                ->orWhere('product_variants.sku', 'like', '%'.$filters['search'].'%')))
            ->orderBy('products.name')
            ->orderBy('sizes.sort_order')
            ->orderBy('sizes.name')
            ->orderBy('colors.sort_order')
            ->orderBy('colors.name')
            ->orderBy('warehouses.name')
            ->select([
                'stock_balances.id',
                'stock_balances.quantity',
                'products.id as product_id',
                'products.name as product_name',
                'product_variants.sku',
                'product_variants.reorder_level',
                'sizes.name as size',
                'colors.name as color',
                'colors.hex_code',
                'warehouses.name as warehouse_name',
                'warehouses.code as warehouse_code',
            ])
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (StockBalance $balance): array => [
                'id' => $balance->id,
                'quantity' => $balance->quantity,
                'reorder_level' => $balance->reorder_level === null ? null : (int) $balance->reorder_level,
                'is_low' => $balance->reorder_level !== null && $balance->quantity <= $balance->reorder_level,
                'product' => ['id' => $balance->product_id, 'name' => $balance->product_name],
                'sku' => $balance->sku,
                'size' => $balance->size,
                'color' => $balance->color,
                'hex_code' => $balance->hex_code,
                'warehouse' => ['name' => $balance->warehouse_name, 'code' => $balance->warehouse_code],
            ]);

        return Inertia::render('inventory/stock', [
            'balances' => $balances,
            'summary' => $summarizeStock->handle($organization),
            'warehouses' => $organization->warehouses()->orderBy('name')->get(['id', 'name', 'code']),
            'filters' => $filters,
        ]);
    }

    /**
     * Start a query over the organization's stock balances with a qualified column, so it is safe to join.
     *
     * @return Builder<StockBalance>
     */
    private function balances(Organization $organization): Builder
    {
        return StockBalance::query()->where('stock_balances.organization_id', $organization->id);
    }
}
