<?php

namespace App\Http\Controllers\Reports;

use App\Actions\Inventory\AverageReceiptCosts;
use App\Concerns\FormatsMoney;
use App\Concerns\ScopesToOrganization;
use App\Concerns\StreamsCsv;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\StockBalance;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Stock that is not selling, for everyone in the organization; values only for managers and admins.
 *
 * A variant in stock is idle since its last dispatch or, if it was never dispatched, since its stock first arrived,
 * so stock that has only just come in is not reported. It is slow-moving when it has been idle for at least the
 * chosen number of days, or when there is no movement history at all. Transfers between warehouses are not sales
 * and do not count as dispatches. With a warehouse filter only that warehouse's stock, dispatches and arrivals
 * (of any kind, including transfers in) count.
 */
class SlowMovingStockController extends Controller
{
    use FormatsMoney;
    use ScopesToOrganization;
    use StreamsCsv;

    private const PER_PAGE = 25;

    /**
     * @var list<int>
     */
    public const DAY_OPTIONS = [30, 60, 90, 180];

    private const DEFAULT_DAYS = 90;

    /**
     * List slow-moving variants, longest idle first, with totals for the filters.
     */
    public function index(Request $request): Response
    {
        $organization = $this->organization($request);
        $filters = $this->filters($request);
        $canSeeCosts = $this->roleIncludes($request, 'manager');

        $rows = $this->rows($organization, $filters)
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (StockBalance $row): array => $this->present($row, $canSeeCosts));

        return Inertia::render('reports/slow-moving', [
            'rows' => $rows,
            'summary' => $this->summary($organization, $filters, $canSeeCosts),
            'warehouses' => $organization->warehouses()->orderBy('name')->get(['id', 'name', 'code']),
            'dayOptions' => self::DAY_OPTIONS,
            'filters' => $filters,
            'canSeeCosts' => $canSeeCosts,
        ]);
    }

    /**
     * Download every slow-moving variant matching the current filters as CSV; the value only for managers and admins.
     */
    public function export(Request $request): StreamedResponse
    {
        $organization = $this->organization($request);
        $filters = $this->filters($request);
        $canSeeCosts = $this->roleIncludes($request, 'manager');
        $currency = $organization->setting('currency');
        $timezone = $organization->setting('timezone');
        $query = $this->rows($organization, $filters);
        $filename = 'slow-moving-stock-'.now($timezone)->format('Ymd-His').'.csv';

        $header = [
            'Product', 'Size', 'Color', 'SKU', 'Units on hand', 'Last dispatched', 'Last arrived', 'Idle since', 'Days idle',
            ...($canSeeCosts ? ["Value ({$currency})"] : []),
        ];

        $date = fn (?string $iso): string => $iso === null ? '' : Carbon::parse($iso)->setTimezone($timezone)->format('Y-m-d');

        return $this->streamCsv($filename, $header, function () use ($query, $canSeeCosts, $date): iterable {
            foreach ($query->cursor() as $row) {
                $values = $this->present($row, $canSeeCosts);

                yield [
                    $values['product']['name'],
                    $values['size'],
                    $values['color'],
                    $values['sku'],
                    $values['on_hand'],
                    $values['last_dispatched_at'] === null ? 'never' : $date($values['last_dispatched_at']),
                    $date($values['last_arrived_at']),
                    $date($values['idle_since']),
                    $values['days_idle'] ?? '',
                    ...($canSeeCosts ? [$values['value'] ?? 'no cost'] : []),
                ];
            }
        });
    }

    /**
     * @return array{search: string, warehouse: ?int, days: int}
     */
    private function filters(Request $request): array
    {
        $days = $request->integer('days');

        return [
            'search' => mb_substr(trim($request->string('search')->toString()), 0, 100),
            'warehouse' => $request->integer('warehouse') ?: null,
            'days' => in_array($days, self::DAY_OPTIONS, true) ? $days : self::DEFAULT_DAYS,
        ];
    }

    /**
     * One row per slow-moving variant with stock on hand, joined to its last dispatch, its arrivals and its cost.
     *
     * @param  array{search: string, warehouse: ?int, days: int}  $filters
     * @return Builder<StockBalance>
     */
    private function rows(Organization $organization, array $filters): Builder
    {
        $idleSince = 'COALESCE(outbound.last_dispatched_at, arrivals.first_arrived_at)';
        $cutoff = now()->utc()->subDays($filters['days'])->toDateTimeString();

        return StockBalance::query()
            ->where('stock_balances.organization_id', $organization->id)
            ->where('stock_balances.quantity', '>', 0)
            ->when($filters['warehouse'], fn (Builder $query, int $warehouseId) => $query->where('stock_balances.warehouse_id', $warehouseId))
            ->join('product_variants', 'product_variants.id', '=', 'stock_balances.product_variant_id')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->join('sizes', 'sizes.id', '=', 'product_variants.size_id')
            ->join('colors', 'colors.id', '=', 'product_variants.color_id')
            ->leftJoinSub($this->lastDispatches($organization, $filters['warehouse']), 'outbound', 'outbound.product_variant_id', '=', 'stock_balances.product_variant_id')
            ->leftJoinSub($this->arrivals($organization, $filters['warehouse']), 'arrivals', 'arrivals.product_variant_id', '=', 'stock_balances.product_variant_id')
            ->leftJoinSub(app(AverageReceiptCosts::class)->query($organization), 'costs', 'costs.product_variant_id', '=', 'stock_balances.product_variant_id')
            ->whereRaw("({$idleSince} IS NULL OR {$idleSince} <= ?)", [$cutoff])
            ->when($filters['search'] !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('products.name', 'like', '%'.$filters['search'].'%')
                ->orWhere('product_variants.sku', 'like', '%'.$filters['search'].'%')))
            ->groupBy([
                'product_variants.id', 'product_variants.sku', 'products.id', 'products.name',
                'sizes.name', 'colors.name', 'colors.hex_code',
                'outbound.last_dispatched_at', 'arrivals.first_arrived_at', 'arrivals.last_arrived_at',
                'costs.costed_quantity', 'costs.cost_cents',
            ])
            ->select([
                'product_variants.id as variant_id',
                'product_variants.sku',
                'products.id as product_id',
                'products.name as product_name',
                'sizes.name as size',
                'colors.name as color',
                'colors.hex_code',
                'outbound.last_dispatched_at',
                'arrivals.last_arrived_at',
                'costs.costed_quantity',
                'costs.cost_cents',
            ])
            ->selectRaw("{$idleSince} as idle_since")
            ->selectRaw('SUM(stock_balances.quantity) as on_hand')
            ->selectRaw('ROUND(SUM(stock_balances.quantity) * costs.cost_cents * 1.0 / costs.costed_quantity) as value_cents')
            ->orderBy('idle_since')
            ->orderBy('products.name')
            ->orderBy('product_variants.sku');
    }

    /**
     * Per variant, when it was last dispatched (from the warehouse, when filtered).
     *
     * @return Builder<StockMovement>
     */
    private function lastDispatches(Organization $organization, ?int $warehouseId): Builder
    {
        return StockMovement::query()
            ->where('organization_id', $organization->id)
            ->where('movement_type', 'dispatch')
            ->when($warehouseId, fn (Builder $query, int $warehouseId) => $query->where('from_warehouse_id', $warehouseId))
            ->groupBy('product_variant_id')
            ->select('product_variant_id')
            ->selectRaw('MAX(occurred_at) as last_dispatched_at');
    }

    /**
     * Per variant, when stock first and last arrived: receipts into the organization, or any movement into the
     * warehouse when filtered.
     *
     * @return Builder<StockMovement>
     */
    private function arrivals(Organization $organization, ?int $warehouseId): Builder
    {
        return StockMovement::query()
            ->where('organization_id', $organization->id)
            ->when(
                $warehouseId,
                fn (Builder $query, int $warehouseId) => $query->where('to_warehouse_id', $warehouseId),
                fn (Builder $query) => $query->where('movement_type', 'receive'),
            )
            ->groupBy('product_variant_id')
            ->select('product_variant_id')
            ->selectRaw('MIN(occurred_at) as first_arrived_at')
            ->selectRaw('MAX(occurred_at) as last_arrived_at');
    }

    /**
     * Totals over every slow-moving variant matching the filters.
     *
     * @param  array{search: string, warehouse: ?int, days: int}  $filters
     * @return array<string, mixed>
     */
    private function summary(Organization $organization, array $filters, bool $canSeeCosts): array
    {
        $totals = DB::query()
            ->fromSub($this->rows($organization, $filters)->reorder(), 'report_rows')
            ->selectRaw('COUNT(*) as variants')
            ->selectRaw('COALESCE(SUM(on_hand), 0) as units')
            ->selectRaw('COALESCE(SUM(value_cents), 0) as value_cents')
            ->selectRaw('COALESCE(SUM(CASE WHEN cost_cents IS NULL THEN on_hand ELSE 0 END), 0) as uncosted_units')
            ->first();

        return [
            'variants' => (int) $totals->variants,
            'units' => (int) $totals->units,
            ...($canSeeCosts ? [
                'value' => $this->money((float) $totals->value_cents),
                'uncosted_units' => (int) $totals->uncosted_units,
            ] : []),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function present(StockBalance $row, bool $canSeeCosts): array
    {
        $iso = fn (?string $value): ?string => $value === null ? null : Carbon::parse($value, 'UTC')->toIso8601String();
        $isCosted = $row->cost_cents !== null && (int) $row->costed_quantity > 0;

        return [
            'variant_id' => (int) $row->variant_id,
            'product' => ['id' => (int) $row->product_id, 'name' => $row->product_name],
            'sku' => $row->sku,
            'size' => $row->size,
            'color' => $row->color,
            'hex_code' => $row->hex_code,
            'on_hand' => (int) $row->on_hand,
            'last_dispatched_at' => $iso($row->last_dispatched_at),
            'last_arrived_at' => $iso($row->last_arrived_at),
            'idle_since' => $iso($row->idle_since),
            'days_idle' => $row->idle_since === null ? null : (int) Carbon::parse($row->idle_since, 'UTC')->diffInDays(now()),
            ...($canSeeCosts ? ['value' => $isCosted ? $this->money((float) $row->value_cents) : null] : []),
        ];
    }
}
