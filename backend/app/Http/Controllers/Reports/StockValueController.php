<?php

namespace App\Http\Controllers\Reports;

use App\Concerns\ScopesToOrganization;
use App\Concerns\StreamsCsv;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\StockBalance;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * What the stock on hand is worth, per variant, for managers and admins.
 *
 * A variant's unit cost is the weighted average of every costed receipt against a purchase order:
 * Σ(quantity × unit cost) ÷ Σ(quantity). Stock is not tracked in lots, so all of a variant's units are valued at
 * that average, including units that arrived through plain Receive stock. Variants that were never received with a
 * cost have no value; they are counted separately instead of as zero.
 */
class StockValueController extends Controller
{
    use ScopesToOrganization;
    use StreamsCsv;

    private const PER_PAGE = 25;

    /**
     * List variants in stock with their average cost and value, highest value first, plus totals for the filters.
     */
    public function index(Request $request): Response
    {
        $organization = $this->organization($request);
        $filters = $this->filters($request);

        $rows = $this->rows($organization, $filters)
            ->when($filters['uncosted'], fn (Builder $query) => $query->whereNull('costs.product_variant_id'))
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (StockBalance $row): array => $this->present($row));

        return Inertia::render('reports/stock-value', [
            'rows' => $rows,
            'summary' => $this->summary($organization, $filters),
            'warehouses' => $organization->warehouses()->orderBy('name')->get(['id', 'name', 'code']),
            'filters' => $filters,
        ]);
    }

    /**
     * Download every row matching the current filters as CSV.
     */
    public function export(Request $request): StreamedResponse
    {
        $organization = $this->organization($request);
        $filters = $this->filters($request);
        $currency = $organization->setting('currency');
        $query = $this->rows($organization, $filters)
            ->when($filters['uncosted'], fn (Builder $query) => $query->whereNull('costs.product_variant_id'));
        $filename = 'stock-value-'.now($organization->setting('timezone'))->format('Ymd-His').'.csv';

        $header = ['Product', 'Size', 'Color', 'SKU', 'Units on hand', "Average unit cost ({$currency})", "Value ({$currency})"];

        return $this->streamCsv($filename, $header, function () use ($query): iterable {
            foreach ($query->cursor() as $row) {
                $values = $this->present($row);

                yield [
                    $values['product']['name'],
                    $values['size'],
                    $values['color'],
                    $values['sku'],
                    $values['on_hand'],
                    $values['average_cost'] ?? '',
                    $values['value'] ?? 'no cost',
                ];
            }
        });
    }

    /**
     * @return array{search: string, warehouse: ?int, uncosted: bool}
     */
    private function filters(Request $request): array
    {
        return [
            'search' => mb_substr(trim($request->string('search')->toString()), 0, 100),
            'warehouse' => $request->integer('warehouse') ?: null,
            'uncosted' => $request->boolean('uncosted'),
        ];
    }

    /**
     * One row per variant with stock on hand (in the filtered warehouse, if any), joined to its receipt costs.
     *
     * @param  array{search: string, warehouse: ?int, uncosted: bool}  $filters
     * @return Builder<StockBalance>
     */
    private function rows(Organization $organization, array $filters): Builder
    {
        return StockBalance::query()
            ->where('stock_balances.organization_id', $organization->id)
            ->where('stock_balances.quantity', '>', 0)
            ->when($filters['warehouse'], fn (Builder $query, int $warehouseId) => $query->where('stock_balances.warehouse_id', $warehouseId))
            ->join('product_variants', 'product_variants.id', '=', 'stock_balances.product_variant_id')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->join('sizes', 'sizes.id', '=', 'product_variants.size_id')
            ->join('colors', 'colors.id', '=', 'product_variants.color_id')
            ->leftJoinSub($this->receiptCosts($organization), 'costs', 'costs.product_variant_id', '=', 'stock_balances.product_variant_id')
            ->when($filters['search'] !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('products.name', 'like', '%'.$filters['search'].'%')
                ->orWhere('product_variants.sku', 'like', '%'.$filters['search'].'%')))
            ->groupBy([
                'product_variants.id', 'product_variants.sku', 'products.id', 'products.name',
                'sizes.name', 'sizes.sort_order', 'colors.name', 'colors.sort_order', 'colors.hex_code',
                'costs.product_variant_id', 'costs.costed_quantity', 'costs.cost_cents',
            ])
            ->select([
                'product_variants.id as variant_id',
                'product_variants.sku',
                'products.id as product_id',
                'products.name as product_name',
                'sizes.name as size',
                'colors.name as color',
                'colors.hex_code',
                'costs.costed_quantity',
                'costs.cost_cents',
            ])
            ->selectRaw('SUM(stock_balances.quantity) as on_hand')
            ->selectRaw('ROUND(SUM(stock_balances.quantity) * costs.cost_cents * 1.0 / costs.costed_quantity) as value_cents')
            ->orderByDesc('value_cents')
            ->orderBy('products.name')
            ->orderBy('sizes.sort_order')
            ->orderBy('sizes.name')
            ->orderBy('colors.sort_order')
            ->orderBy('colors.name');
    }

    /**
     * Per variant, the units received against purchase orders with a cost and what they cost in total, in cents.
     *
     * @return Builder<StockMovement>
     */
    private function receiptCosts(Organization $organization): Builder
    {
        return StockMovement::query()
            ->where('organization_id', $organization->id)
            ->where('movement_type', 'receive')
            ->whereNotNull('unit_cost')
            ->where('quantity', '>', 0)
            ->groupBy('product_variant_id')
            ->select('product_variant_id')
            ->selectRaw('SUM(quantity) as costed_quantity')
            ->selectRaw('SUM(quantity * ROUND(unit_cost * 100)) as cost_cents');
    }

    /**
     * Totals over every row matching the search and warehouse filters (the "without cost only" filter is ignored, so
     * the tiles always show the whole picture).
     *
     * @param  array{search: string, warehouse: ?int, uncosted: bool}  $filters
     * @return array{total_value: string, costed_units: int, uncosted_units: int, uncosted_variants: int}
     */
    private function summary(Organization $organization, array $filters): array
    {
        $totals = DB::query()
            ->fromSub($this->rows($organization, $filters)->reorder(), 'report_rows')
            ->selectRaw('COALESCE(SUM(value_cents), 0) as total_cents')
            ->selectRaw('COALESCE(SUM(CASE WHEN cost_cents IS NULL THEN 0 ELSE on_hand END), 0) as costed_units')
            ->selectRaw('COALESCE(SUM(CASE WHEN cost_cents IS NULL THEN on_hand ELSE 0 END), 0) as uncosted_units')
            ->selectRaw('COALESCE(SUM(CASE WHEN cost_cents IS NULL THEN 1 ELSE 0 END), 0) as uncosted_variants')
            ->first();

        return [
            'total_value' => $this->money((float) $totals->total_cents),
            'costed_units' => (int) $totals->costed_units,
            'uncosted_units' => (int) $totals->uncosted_units,
            'uncosted_variants' => (int) $totals->uncosted_variants,
        ];
    }

    /**
     * @return array{variant_id: int, product: array{id: int, name: string}, sku: string, size: string, color: string, hex_code: ?string, on_hand: int, average_cost: ?string, value: ?string}
     */
    private function present(StockBalance $row): array
    {
        $isCosted = $row->cost_cents !== null && (int) $row->costed_quantity > 0;

        return [
            'variant_id' => (int) $row->variant_id,
            'product' => ['id' => (int) $row->product_id, 'name' => $row->product_name],
            'sku' => $row->sku,
            'size' => $row->size,
            'color' => $row->color,
            'hex_code' => $row->hex_code,
            'on_hand' => (int) $row->on_hand,
            'average_cost' => $isCosted ? $this->money((float) $row->cost_cents / (int) $row->costed_quantity) : null,
            'value' => $isCosted ? $this->money((float) $row->value_cents) : null,
        ];
    }

    /**
     * Cents as a decimal string with two places, the format the pages and money helpers expect.
     */
    private function money(float $cents): string
    {
        return number_format(round($cents) / 100, 2, '.', '');
    }
}
