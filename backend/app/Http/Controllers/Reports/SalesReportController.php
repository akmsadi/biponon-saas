<?php

namespace App\Http\Controllers\Reports;

use App\Concerns\ParsesDateFilters;
use App\Concerns\ScopesToOrganization;
use App\Concerns\StreamsCsv;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\SalesOrderLine;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Sales by product, variant or customer, for everyone in the organization.
 *
 * A sale counts when its order is confirmed: confirmed, partly dispatched, dispatched and closed orders count,
 * dated by their confirmed date in the organization's timezone; drafts and cancelled orders do not. A line counts
 * its ordered units, except on orders closed short, where only the dispatched units count because the rest will
 * never ship. Returned units are subtracted, and revenue is the net units at the line's price.
 */
class SalesReportController extends Controller
{
    use ParsesDateFilters;
    use ScopesToOrganization;
    use StreamsCsv;

    private const PER_PAGE = 25;

    private const DEFAULT_DAYS = 30;

    /**
     * @var list<string>
     */
    private const COUNTED_STATUSES = ['confirmed', 'partially_dispatched', 'dispatched', 'closed'];

    /**
     * @var list<string>
     */
    private const GROUPS = ['product', 'variant', 'customer'];

    /**
     * Show the grouped rows, highest net revenue first, with totals for the filters.
     */
    public function index(Request $request): Response
    {
        $organization = $this->organization($request);
        $filters = $this->filters($request, $organization);

        $shownFilters = [
            ...$filters,
            'date_from' => $filters['date_from']?->toDateString(),
            'date_to' => $filters['date_to']?->toDateString(),
        ];

        // Dates go into the page links even when empty: an empty date means open-ended, a missing one the default.
        $rows = $this->groupedRows($organization, $filters)
            ->paginate(self::PER_PAGE)
            ->appends(array_filter([
                ...$shownFilters,
                'date_from' => $shownFilters['date_from'] ?? '',
                'date_to' => $shownFilters['date_to'] ?? '',
            ], fn (mixed $value): bool => $value !== null))
            ->through(fn (SalesOrderLine $row): array => $this->present($row, $filters['group']));

        return Inertia::render('reports/sales', [
            'rows' => $rows,
            'summary' => $this->summary($organization, $filters),
            'customers' => $organization->customers()->orderBy('name')->get(['id', 'name']),
            'filters' => $shownFilters,
        ]);
    }

    /**
     * Download every grouped row matching the current filters as CSV.
     */
    public function export(Request $request): StreamedResponse
    {
        $organization = $this->organization($request);
        $filters = $this->filters($request, $organization);
        $currency = $organization->setting('currency');
        $query = $this->groupedRows($organization, $filters);
        $filename = 'sales-by-'.$filters['group'].'-'.now($organization->setting('timezone'))->format('Ymd-His').'.csv';

        $header = [
            ...match ($filters['group']) {
                'product' => ['Product'],
                'variant' => ['Product', 'Size', 'Color', 'SKU'],
                'customer' => ['Customer'],
            },
            'Orders', 'Units', 'Dispatched', 'Returned', 'Net units', "Net revenue ({$currency})",
        ];

        return $this->streamCsv($filename, $header, function () use ($query, $filters): iterable {
            foreach ($query->cursor() as $row) {
                $values = $this->present($row, $filters['group']);

                yield [
                    ...match ($filters['group']) {
                        'product' => [$values['product']['name']],
                        'variant' => [$values['product']['name'], $values['size'], $values['color'], $values['sku']],
                        'customer' => [$values['customer']['name']],
                    },
                    $values['orders'],
                    $values['units'],
                    $values['dispatched'],
                    $values['returned'],
                    $values['net_units'],
                    $values['net_revenue'],
                ];
            }
        });
    }

    /**
     * Read the filters from the query string. Without any date in the query the period defaults to the last 30 days
     * including today; an empty date means that side is open.
     *
     * @return array{group: string, search: string, customer: ?int, date_from: ?CarbonInterface, date_to: ?CarbonInterface}
     */
    private function filters(Request $request, Organization $organization): array
    {
        $timezone = $organization->setting('timezone');
        $group = $request->string('group')->toString();
        $hasDates = $request->has('date_from') || $request->has('date_to');

        return [
            'group' => in_array($group, self::GROUPS, true) ? $group : 'product',
            'search' => mb_substr(trim($request->string('search')->toString()), 0, 100),
            'customer' => $request->integer('customer') ?: null,
            'date_from' => $hasDates
                ? $this->parseDate($request->string('date_from')->toString(), $timezone)?->startOfDay()
                : now($timezone)->subDays(self::DEFAULT_DAYS - 1)->startOfDay(),
            'date_to' => $hasDates
                ? $this->parseDate($request->string('date_to')->toString(), $timezone)?->endOfDay()
                : now($timezone)->endOfDay(),
        ];
    }

    /**
     * Every counted sales order line matching the filters, joined to its order, customer and variant.
     *
     * @param  array{group: string, search: string, customer: ?int, date_from: ?CarbonInterface, date_to: ?CarbonInterface}  $filters
     * @return Builder<SalesOrderLine>
     */
    private function countedLines(Organization $organization, array $filters): Builder
    {
        return SalesOrderLine::query()
            ->join('sales_orders', 'sales_orders.id', '=', 'sales_order_lines.sales_order_id')
            ->join('customers', 'customers.id', '=', 'sales_orders.customer_id')
            ->join('product_variants', 'product_variants.id', '=', 'sales_order_lines.product_variant_id')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->join('sizes', 'sizes.id', '=', 'product_variants.size_id')
            ->join('colors', 'colors.id', '=', 'product_variants.color_id')
            ->where('sales_orders.organization_id', $organization->id)
            ->whereIn('sales_orders.status', self::COUNTED_STATUSES)
            ->when($filters['customer'], fn (Builder $query, int $customerId) => $query->where('sales_orders.customer_id', $customerId))
            ->when($filters['date_from'], fn (Builder $query, CarbonInterface $from) => $query->where('sales_orders.confirmed_at', '>=', $from->copy()->utc()))
            ->when($filters['date_to'], fn (Builder $query, CarbonInterface $to) => $query->where('sales_orders.confirmed_at', '<=', $to->copy()->utc()))
            ->when($filters['search'] !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('products.name', 'like', '%'.$filters['search'].'%')
                ->orWhere('product_variants.sku', 'like', '%'.$filters['search'].'%')
                ->orWhere('customers.name', 'like', '%'.$filters['search'].'%')));
    }

    /**
     * The counted lines rolled up by the chosen group, highest net revenue first.
     *
     * @param  array{group: string, search: string, customer: ?int, date_from: ?CarbonInterface, date_to: ?CarbonInterface}  $filters
     * @return Builder<SalesOrderLine>
     */
    private function groupedRows(Organization $organization, array $filters): Builder
    {
        $columns = match ($filters['group']) {
            'product' => ['products.id as product_id', 'products.name as product_name'],
            'variant' => [
                'product_variants.id as variant_id', 'product_variants.sku', 'products.id as product_id',
                'products.name as product_name', 'sizes.name as size', 'colors.name as color', 'colors.hex_code',
            ],
            'customer' => ['customers.id as customer_id', 'customers.name as customer_name'],
        };

        $query = $this->countedLines($organization, $filters)
            ->groupBy(array_map(fn (string $column): string => explode(' as ', $column)[0], $columns))
            ->select($columns);

        return $this->selectTotals($query)
            ->orderByDesc('net_revenue_cents')
            ->orderBy(match ($filters['group']) {
                'customer' => 'customers.name',
                default => 'products.name',
            })
            ->when($filters['group'] === 'variant', fn (Builder $query) => $query->orderBy('product_variants.sku'));
    }

    /**
     * Add the aggregate columns shared by the grouped rows and the summary.
     *
     * @param  Builder<SalesOrderLine>  $query
     * @return Builder<SalesOrderLine>
     */
    private function selectTotals(Builder $query): Builder
    {
        $counted = "CASE WHEN sales_orders.status = 'closed' THEN sales_order_lines.quantity_dispatched ELSE sales_order_lines.quantity_ordered END";

        return $query
            ->selectRaw('COUNT(DISTINCT sales_orders.id) as orders')
            ->selectRaw("SUM({$counted}) as units")
            ->selectRaw('SUM(sales_order_lines.quantity_dispatched) as dispatched')
            ->selectRaw('SUM(sales_order_lines.quantity_returned) as returned')
            ->selectRaw("SUM({$counted} - sales_order_lines.quantity_returned) as net_units")
            ->selectRaw("SUM(({$counted} - sales_order_lines.quantity_returned) * ROUND(sales_order_lines.unit_price * 100)) as net_revenue_cents");
    }

    /**
     * Totals over every counted line matching the filters.
     *
     * @param  array{group: string, search: string, customer: ?int, date_from: ?CarbonInterface, date_to: ?CarbonInterface}  $filters
     * @return array{net_revenue: string, net_units: int, orders: int, average_order_value: ?string}
     */
    private function summary(Organization $organization, array $filters): array
    {
        $totals = $this->selectTotals($this->countedLines($organization, $filters)->select([]))->toBase()->first();
        $orders = (int) $totals->orders;
        $revenueCents = (float) ($totals->net_revenue_cents ?? 0);

        return [
            'net_revenue' => $this->money($revenueCents),
            'net_units' => (int) ($totals->net_units ?? 0),
            'orders' => $orders,
            'average_order_value' => $orders > 0 ? $this->money($revenueCents / $orders) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function present(SalesOrderLine $row, string $group): array
    {
        return [
            ...match ($group) {
                'product' => [
                    'key' => 'product-'.$row->product_id,
                    'product' => ['id' => (int) $row->product_id, 'name' => $row->product_name],
                ],
                'variant' => [
                    'key' => 'variant-'.$row->variant_id,
                    'product' => ['id' => (int) $row->product_id, 'name' => $row->product_name],
                    'sku' => $row->sku,
                    'size' => $row->size,
                    'color' => $row->color,
                    'hex_code' => $row->hex_code,
                ],
                'customer' => [
                    'key' => 'customer-'.$row->customer_id,
                    'customer' => ['id' => (int) $row->customer_id, 'name' => $row->customer_name],
                ],
            },
            'orders' => (int) $row->orders,
            'units' => (int) $row->units,
            'dispatched' => (int) $row->dispatched,
            'returned' => (int) $row->returned,
            'net_units' => (int) $row->net_units,
            'net_revenue' => $this->money((float) $row->net_revenue_cents),
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
