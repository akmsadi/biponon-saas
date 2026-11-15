<?php

namespace App\Http\Controllers;

use App\Actions\Inventory\SummarizeStock;
use App\Concerns\ScopesToOrganization;
use App\Models\Organization;
use App\Models\StockBalance;
use App\Models\StockMovement;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The organization's at-a-glance view: stock totals, recent flow in and out, low stock, recent movements and
 * stock per warehouse. "In" is stock entering the organization (no source warehouse: receipts, returns) and
 * "out" is stock leaving it (no destination: dispatches); transfers move stock inside and count as neither.
 */
class DashboardController extends Controller
{
    use ScopesToOrganization;

    /**
     * Days of hourly flow sent for the chart. One more than it shows, so every browser time zone can still
     * fill its 30 local days.
     */
    private const FLOW_DAYS = 31;

    private const LIST_SIZE = 10;

    public function __invoke(Request $request, SummarizeStock $summarizeStock): Response
    {
        $organization = $this->organization($request);

        return Inertia::render('dashboard', [
            'summary' => $summarizeStock->handle($organization),
            'lastSevenDays' => $this->flowTotals($organization),
            'hourlyFlow' => $this->hourlyFlow($organization),
            'lowStock' => $this->lowStock($organization),
            'recentMovements' => $this->recentMovements($organization),
            'warehouses' => $this->unitsPerWarehouse($organization),
        ]);
    }

    /**
     * Units in and out over the last 7 Ã— 24 hours.
     *
     * @return array{units_in: int, units_out: int}
     */
    private function flowTotals(Organization $organization): array
    {
        $totals = $this->flowQuery($organization, now()->subDays(7))->first();

        return [
            'units_in' => (int) $totals?->units_in,
            'units_out' => (int) $totals?->units_out,
        ];
    }

    /**
     * Units in and out per UTC hour ("YYYY-MM-DD HH"), so the browser can group them into its own local days.
     * Hours without flow are left out.
     *
     * @return list<array{hour: string, units_in: int, units_out: int}>
     */
    private function hourlyFlow(Organization $organization): array
    {
        // Timestamps are stored in UTC as "YYYY-MM-DD HH:MM:SS"; the first 13 characters are the hour.
        $hour = DB::raw('substr(occurred_at, 1, 13)');

        return $this->flowQuery($organization, now()->subDays(self::FLOW_DAYS)->startOfHour())
            ->addSelect(DB::raw('substr(occurred_at, 1, 13) as hour'))
            ->groupBy($hour)
            ->orderBy($hour)
            ->get()
            ->map(fn (StockMovement $row): array => [
                'hour' => $row->hour,
                'units_in' => (int) $row->units_in,
                'units_out' => (int) $row->units_out,
            ])
            ->all();
    }

    /**
     * @return Builder<StockMovement>
     */
    private function flowQuery(Organization $organization, CarbonInterface $since): Builder
    {
        return StockMovement::query()
            ->where('organization_id', $organization->id)
            ->where('occurred_at', '>=', $since)
            ->select([
                DB::raw('coalesce(sum(case when from_warehouse_id is null then quantity else 0 end), 0) as units_in'),
                DB::raw('coalesce(sum(case when to_warehouse_id is null then quantity else 0 end), 0) as units_out'),
            ]);
    }

    /**
     * The low-stock rows furthest below their reorder level first.
     *
     * @return list<array<string, mixed>>
     */
    private function lowStock(Organization $organization): array
    {
        return SummarizeStock::whereLowStock(
            StockBalance::query()
                ->where('stock_balances.organization_id', $organization->id)
                ->join('product_variants', 'product_variants.id', '=', 'stock_balances.product_variant_id')
        )
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->join('sizes', 'sizes.id', '=', 'product_variants.size_id')
            ->join('colors', 'colors.id', '=', 'product_variants.color_id')
            ->join('warehouses', 'warehouses.id', '=', 'stock_balances.warehouse_id')
            ->orderByRaw('stock_balances.quantity - product_variants.reorder_level')
            ->orderBy('products.name')
            ->limit(self::LIST_SIZE)
            ->get([
                'stock_balances.id',
                'stock_balances.quantity',
                'product_variants.reorder_level',
                'product_variants.sku',
                'products.id as product_id',
                'products.name as product_name',
                'sizes.name as size',
                'colors.name as color',
                'colors.hex_code',
                'warehouses.code as warehouse_code',
            ])
            ->map(fn (StockBalance $balance): array => [
                'id' => $balance->id,
                'quantity' => $balance->quantity,
                'reorder_level' => (int) $balance->reorder_level,
                'sku' => $balance->sku,
                'product' => ['id' => $balance->product_id, 'name' => $balance->product_name],
                'size' => $balance->size,
                'color' => $balance->color,
                'hex_code' => $balance->hex_code,
                'warehouse' => $balance->warehouse_code,
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recentMovements(Organization $organization): array
    {
        return $organization->stockMovements()
            ->with([
                'productVariant:id,product_id,sku',
                'productVariant.product:id,name',
                'fromWarehouse:id,code',
                'toWarehouse:id,code',
                'performedBy:id,name',
            ])
            ->latest('occurred_at')
            ->latest('id')
            ->limit(self::LIST_SIZE)
            ->get()
            ->map(fn (StockMovement $movement): array => [
                'id' => $movement->id,
                'type' => $movement->movement_type,
                'quantity' => $movement->quantity,
                'occurred_at' => $movement->occurred_at->toIso8601String(),
                'sku' => $movement->productVariant->sku,
                'product' => $movement->productVariant->product->name,
                'from' => $movement->fromWarehouse?->code,
                'to' => $movement->toWarehouse?->code,
                'performed_by' => $movement->performedBy?->name,
            ])
            ->all();
    }

    /**
     * Warehouses holding stock, most units first.
     *
     * @return list<array{id: int, name: string, code: string, units: int}>
     */
    private function unitsPerWarehouse(Organization $organization): array
    {
        return $organization->warehouses()
            ->withSum('stockBalances as units', 'quantity')
            ->get(['id', 'name', 'code'])
            ->map(fn ($warehouse): array => [
                'id' => $warehouse->id,
                'name' => $warehouse->name,
                'code' => $warehouse->code,
                'units' => (int) $warehouse->units,
            ])
            ->filter(fn (array $warehouse): bool => $warehouse['units'] > 0)
            ->sortByDesc('units')
            ->values()
            ->all();
    }
}
