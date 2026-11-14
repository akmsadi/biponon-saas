<?php

namespace App\Http\Controllers\Reports;

use App\Concerns\FormatsMoney;
use App\Concerns\ParsesDateFilters;
use App\Concerns\ScopesToOrganization;
use App\Concerns\StreamsCsv;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\PurchaseOrderLine;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Purchases by supplier, for everyone in the organization; the money columns only for managers and admins, who are
 * the only ones who see purchase costs.
 *
 * A purchase counts once its order is placed: ordered, partly received, received and closed orders count, dated by
 * when they were placed in the organization's timezone; drafts and cancelled orders do not. Units still to come are
 * the unreceived units on open orders only, since orders closed short will receive nothing more. Amounts cover
 * lines with a unit cost only.
 */
class PurchasesReportController extends Controller
{
    use FormatsMoney;
    use ParsesDateFilters;
    use ScopesToOrganization;
    use StreamsCsv;

    private const PER_PAGE = 25;

    private const DEFAULT_DAYS = 30;

    /**
     * Status filter options and the order statuses each one covers.
     *
     * @var array<string, list<string>>
     */
    private const STATUS_GROUPS = [
        'all' => ['ordered', 'partially_received', 'received', 'closed'],
        'open' => ['ordered', 'partially_received'],
        'received' => ['received'],
        'closed' => ['closed'],
    ];

    /**
     * Show one row per supplier, most units ordered first, with totals for the filters.
     */
    public function index(Request $request): Response
    {
        $organization = $this->organization($request);
        $filters = $this->filters($request, $organization);
        $canSeeCosts = $this->roleIncludes($request, 'manager');

        $shownFilters = [
            ...$filters,
            'date_from' => $filters['date_from']?->toDateString(),
            'date_to' => $filters['date_to']?->toDateString(),
        ];

        // Dates go into the page links even when empty: an empty date means open-ended, a missing one the default.
        $rows = $this->supplierRows($organization, $filters)
            ->paginate(self::PER_PAGE)
            ->appends(array_filter([
                ...$shownFilters,
                'date_from' => $shownFilters['date_from'] ?? '',
                'date_to' => $shownFilters['date_to'] ?? '',
            ], fn (mixed $value): bool => $value !== null))
            ->through(fn (PurchaseOrderLine $row): array => $this->present($row, $canSeeCosts));

        return Inertia::render('reports/purchases', [
            'rows' => $rows,
            'summary' => $this->summary($organization, $filters, $canSeeCosts),
            'filters' => $shownFilters,
            'canSeeCosts' => $canSeeCosts,
        ]);
    }

    /**
     * Download every supplier row matching the current filters as CSV; amounts only for managers and admins.
     */
    public function export(Request $request): StreamedResponse
    {
        $organization = $this->organization($request);
        $filters = $this->filters($request, $organization);
        $canSeeCosts = $this->roleIncludes($request, 'manager');
        $currency = $organization->setting('currency');
        $query = $this->supplierRows($organization, $filters);
        $filename = 'purchases-by-supplier-'.now($organization->setting('timezone'))->format('Ymd-His').'.csv';

        $header = [
            'Supplier', 'Orders', 'Open orders', 'Units ordered', 'Units received', 'Still to come',
            ...($canSeeCosts ? ["Amount ordered ({$currency})", "Amount received ({$currency})"] : []),
        ];

        return $this->streamCsv($filename, $header, function () use ($query, $canSeeCosts): iterable {
            foreach ($query->cursor() as $row) {
                $values = $this->present($row, $canSeeCosts);

                yield [
                    $values['supplier']['name'],
                    $values['orders'],
                    $values['open_orders'],
                    $values['units_ordered'],
                    $values['units_received'],
                    $values['units_outstanding'],
                    ...($canSeeCosts ? [$values['amount_ordered'], $values['amount_received']] : []),
                ];
            }
        });
    }

    /**
     * Read the filters from the query string. Without any date in the query the period defaults to the last 30 days
     * including today; an empty date means that side is open.
     *
     * @return array{search: string, status: string, date_from: ?CarbonInterface, date_to: ?CarbonInterface}
     */
    private function filters(Request $request, Organization $organization): array
    {
        $timezone = $organization->setting('timezone');
        $status = $request->string('status')->toString();
        $hasDates = $request->has('date_from') || $request->has('date_to');

        return [
            'search' => mb_substr(trim($request->string('search')->toString()), 0, 100),
            'status' => array_key_exists($status, self::STATUS_GROUPS) ? $status : 'all',
            'date_from' => $hasDates
                ? $this->parseDate($request->string('date_from')->toString(), $timezone)?->startOfDay()
                : now($timezone)->subDays(self::DEFAULT_DAYS - 1)->startOfDay(),
            'date_to' => $hasDates
                ? $this->parseDate($request->string('date_to')->toString(), $timezone)?->endOfDay()
                : now($timezone)->endOfDay(),
        ];
    }

    /**
     * Every line of a placed purchase order matching the filters, joined to its order and supplier.
     *
     * @param  array{search: string, status: string, date_from: ?CarbonInterface, date_to: ?CarbonInterface}  $filters
     * @return Builder<PurchaseOrderLine>
     */
    private function placedLines(Organization $organization, array $filters): Builder
    {
        return PurchaseOrderLine::query()
            ->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_order_lines.purchase_order_id')
            ->join('suppliers', 'suppliers.id', '=', 'purchase_orders.supplier_id')
            ->where('purchase_orders.organization_id', $organization->id)
            ->whereIn('purchase_orders.status', self::STATUS_GROUPS[$filters['status']])
            ->when($filters['date_from'], fn (Builder $query, CarbonInterface $from) => $query->where('purchase_orders.ordered_at', '>=', $from->copy()->utc()))
            ->when($filters['date_to'], fn (Builder $query, CarbonInterface $to) => $query->where('purchase_orders.ordered_at', '<=', $to->copy()->utc()))
            ->when($filters['search'] !== '', fn (Builder $query) => $query->where('suppliers.name', 'like', '%'.$filters['search'].'%'));
    }

    /**
     * The placed lines rolled up per supplier, most units ordered first.
     *
     * @param  array{search: string, status: string, date_from: ?CarbonInterface, date_to: ?CarbonInterface}  $filters
     * @return Builder<PurchaseOrderLine>
     */
    private function supplierRows(Organization $organization, array $filters): Builder
    {
        return $this->selectTotals(
            $this->placedLines($organization, $filters)
                ->groupBy(['suppliers.id', 'suppliers.name'])
                ->select(['suppliers.id as supplier_id', 'suppliers.name as supplier_name'])
        )
            ->orderByDesc('units_ordered')
            ->orderBy('suppliers.name');
    }

    /**
     * Add the aggregate columns shared by the supplier rows and the summary.
     *
     * @param  Builder<PurchaseOrderLine>  $query
     * @return Builder<PurchaseOrderLine>
     */
    private function selectTotals(Builder $query): Builder
    {
        $isOpen = "purchase_orders.status IN ('ordered', 'partially_received')";

        return $query
            ->selectRaw('COUNT(DISTINCT purchase_orders.id) as orders')
            ->selectRaw("COUNT(DISTINCT CASE WHEN {$isOpen} THEN purchase_orders.id END) as open_orders")
            ->selectRaw('SUM(purchase_order_lines.quantity_ordered) as units_ordered')
            ->selectRaw('SUM(purchase_order_lines.quantity_received) as units_received')
            ->selectRaw("SUM(CASE WHEN {$isOpen} AND purchase_order_lines.quantity_ordered > purchase_order_lines.quantity_received THEN purchase_order_lines.quantity_ordered - purchase_order_lines.quantity_received ELSE 0 END) as units_outstanding")
            ->selectRaw('SUM(purchase_order_lines.quantity_ordered * ROUND(purchase_order_lines.unit_cost * 100)) as amount_ordered_cents')
            ->selectRaw('SUM(purchase_order_lines.quantity_received * ROUND(purchase_order_lines.unit_cost * 100)) as amount_received_cents');
    }

    /**
     * Totals over every placed line matching the filters.
     *
     * @param  array{search: string, status: string, date_from: ?CarbonInterface, date_to: ?CarbonInterface}  $filters
     * @return array<string, mixed>
     */
    private function summary(Organization $organization, array $filters, bool $canSeeCosts): array
    {
        $totals = $this->selectTotals($this->placedLines($organization, $filters)->select([]))->toBase()->first();

        return [
            'orders' => (int) $totals->orders,
            'open_orders' => (int) $totals->open_orders,
            'units_ordered' => (int) ($totals->units_ordered ?? 0),
            'units_received' => (int) ($totals->units_received ?? 0),
            'units_outstanding' => (int) ($totals->units_outstanding ?? 0),
            ...($canSeeCosts ? [
                'amount_ordered' => $this->money((float) ($totals->amount_ordered_cents ?? 0)),
                'amount_received' => $this->money((float) ($totals->amount_received_cents ?? 0)),
            ] : []),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function present(PurchaseOrderLine $row, bool $canSeeCosts): array
    {
        return [
            'supplier' => ['id' => (int) $row->supplier_id, 'name' => $row->supplier_name],
            'orders' => (int) $row->orders,
            'open_orders' => (int) $row->open_orders,
            'units_ordered' => (int) $row->units_ordered,
            'units_received' => (int) $row->units_received,
            'units_outstanding' => (int) $row->units_outstanding,
            ...($canSeeCosts ? [
                'amount_ordered' => $this->money((float) ($row->amount_ordered_cents ?? 0)),
                'amount_received' => $this->money((float) ($row->amount_received_cents ?? 0)),
            ] : []),
        ];
    }
}
