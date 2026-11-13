<?php

namespace App\Http\Controllers\Inventory;

use App\Concerns\ScopesToOrganization;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\SerialNumber;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * The read-only stock ledger: every movement into, out of, or between warehouses, newest first.
 */
class StockMovementController extends Controller
{
    use ScopesToOrganization;

    private const PER_PAGE = 25;

    /**
     * List the organization's movements, filtered by search, type, warehouse, product and date range.
     */
    public function index(Request $request): Response
    {
        $organization = $this->organization($request);
        $filters = $this->filters($request);

        $movements = $this->filteredQuery($organization, $filters)
            ->withCount('serialNumbers')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (StockMovement $movement): array => [
                'id' => $movement->id,
                'movement_type' => $movement->movement_type,
                'quantity' => $movement->quantity,
                'direction' => $this->direction($movement, $filters['warehouse']),
                'product' => ['id' => $movement->product_id, 'name' => $movement->product_name],
                'sku' => $movement->sku,
                'size' => $movement->size,
                'color' => $movement->color,
                'hex_code' => $movement->hex_code,
                'from' => $movement->from_code === null ? null : ['name' => $movement->from_name, 'code' => $movement->from_code],
                'to' => $movement->to_code === null ? null : ['name' => $movement->to_name, 'code' => $movement->to_code],
                'performed_by' => $movement->performer_name,
                'notes' => $movement->notes,
                'serial_count' => $movement->serial_numbers_count,
                'occurred_at' => $movement->occurred_at->toIso8601String(),
            ]);

        return Inertia::render('inventory/movements', [
            'movements' => $movements,
            'warehouses' => $organization->warehouses()->orderBy('name')->get(['id', 'name', 'code']),
            'types' => $organization->stockMovements()->distinct()->orderBy('movement_type')->pluck('movement_type'),
            'product' => $filters['product'] === null
                ? null
                : $organization->products()->whereKey($filters['product'])->first(['id', 'name']),
            'filters' => [
                ...Arr::except($filters, 'tz'),
                'date_from' => $filters['date_from']?->toDateString(),
                'date_to' => $filters['date_to']?->toDateString(),
            ],
        ]);
    }

    /**
     * Download every movement matching the current filters as CSV, with dates in the organization's timezone.
     */
    public function export(Request $request): StreamedResponse
    {
        $organization = $this->organization($request);
        $filters = $this->filters($request);
        $query = $this->filteredQuery($organization, $filters)->with('serialNumbers:id,serial_number');
        $filename = 'stock-movements-'.now($filters['tz'])->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($query, $filters): void {
            $output = fopen('php://output', 'w');
            fwrite($output, "\u{FEFF}"); // UTF-8 BOM so Excel reads non-ASCII names correctly.

            fputcsv($output, ['Date', 'Type', 'Product', 'Size', 'Color', 'SKU', 'From', 'To', 'Quantity', 'Performed by', 'Serials', 'Notes'], escape: '');

            foreach ($query->lazy(500) as $movement) {
                fputcsv($output, array_map($this->csvSafe(...), [
                    $movement->occurred_at->setTimezone($filters['tz'])->format('Y-m-d H:i'),
                    $movement->movement_type,
                    $movement->product_name,
                    $movement->size,
                    $movement->color,
                    $movement->sku,
                    $movement->from_code ?? '',
                    $movement->to_code ?? '',
                    $movement->quantity,
                    $movement->performer_name ?? '',
                    $movement->serialNumbers->pluck('serial_number')->implode(' '),
                    $movement->notes ?? '',
                ]), escape: '');
            }

            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * The serial numbers that took part in one movement, as JSON for the serials dialog.
     */
    public function serials(Request $request, StockMovement $stockMovement): JsonResponse
    {
        $this->ensureBelongsToOrganization($request, $stockMovement);

        return response()->json([
            'serials' => $stockMovement->serialNumbers()
                ->orderBy('serial_number')
                ->get(['serial_numbers.id', 'serial_number', 'status'])
                ->map(fn (SerialNumber $serial): array => $serial->only(['id', 'serial_number', 'status'])),
        ]);
    }

    /**
     * Read the filters from the query string, ignoring anything malformed. Dates are whole days in the
     * organization's timezone.
     *
     * @return array{search: string, type: ?string, warehouse: ?int, product: ?int, date_from: ?Carbon, date_to: ?Carbon, tz: string}
     */
    private function filters(Request $request): array
    {
        $timezone = $this->organization($request)->setting('timezone');

        return [
            'search' => mb_substr(trim($request->string('search')->toString()), 0, 100),
            'type' => mb_substr($request->string('type')->toString(), 0, 30) ?: null,
            'warehouse' => $request->integer('warehouse') ?: null,
            'product' => $request->integer('product') ?: null,
            'date_from' => $this->parseDate($request->string('date_from')->toString(), $timezone)?->startOfDay(),
            'date_to' => $this->parseDate($request->string('date_to')->toString(), $timezone)?->endOfDay(),
            'tz' => $timezone,
        ];
    }

    private function parseDate(string $value, string $timezone): ?Carbon
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        try {
            return Carbon::createFromFormat('!Y-m-d', $value, $timezone) ?: null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * The organization's movements joined to their variant, warehouses and performer, with the filters applied.
     *
     * @param  array{search: string, type: ?string, warehouse: ?int, product: ?int, date_from: ?Carbon, date_to: ?Carbon, tz: string}  $filters
     * @return Builder<StockMovement>
     */
    private function filteredQuery(Organization $organization, array $filters): Builder
    {
        return StockMovement::query()
            ->where('stock_movements.organization_id', $organization->id)
            ->join('product_variants', 'product_variants.id', '=', 'stock_movements.product_variant_id')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->join('sizes', 'sizes.id', '=', 'product_variants.size_id')
            ->join('colors', 'colors.id', '=', 'product_variants.color_id')
            ->leftJoin('warehouses as from_warehouses', 'from_warehouses.id', '=', 'stock_movements.from_warehouse_id')
            ->leftJoin('warehouses as to_warehouses', 'to_warehouses.id', '=', 'stock_movements.to_warehouse_id')
            ->leftJoin('users as performers', 'performers.id', '=', 'stock_movements.performed_by')
            ->when($filters['type'], fn (Builder $query, string $type) => $query->where('stock_movements.movement_type', $type))
            ->when($filters['product'], fn (Builder $query, int $productId) => $query->where('products.id', $productId))
            ->when($filters['warehouse'], fn (Builder $query, int $warehouseId) => $query->where(fn (Builder $query) => $query
                ->where('stock_movements.from_warehouse_id', $warehouseId)
                ->orWhere('stock_movements.to_warehouse_id', $warehouseId)))
            ->when($filters['date_from'], fn (Builder $query, Carbon $from) => $query->where('stock_movements.occurred_at', '>=', $from->copy()->utc()))
            ->when($filters['date_to'], fn (Builder $query, Carbon $to) => $query->where('stock_movements.occurred_at', '<=', $to->copy()->utc()))
            ->when($filters['search'] !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('products.name', 'like', '%'.$filters['search'].'%')
                ->orWhere('product_variants.sku', 'like', '%'.$filters['search'].'%')))
            ->orderByDesc('stock_movements.occurred_at')
            ->orderByDesc('stock_movements.id')
            ->select([
                'stock_movements.id',
                'stock_movements.movement_type',
                'stock_movements.quantity',
                'stock_movements.from_warehouse_id',
                'stock_movements.to_warehouse_id',
                'stock_movements.notes',
                'stock_movements.occurred_at',
                'products.id as product_id',
                'products.name as product_name',
                'product_variants.sku',
                'sizes.name as size',
                'colors.name as color',
                'colors.hex_code',
                'from_warehouses.name as from_name',
                'from_warehouses.code as from_code',
                'to_warehouses.name as to_name',
                'to_warehouses.code as to_code',
                'performers.name as performer_name',
            ]);
    }

    /**
     * Whether the movement brought stock into or out of the filtered warehouse; null without a warehouse filter.
     */
    private function direction(StockMovement $movement, ?int $warehouseId): ?string
    {
        return match (true) {
            $warehouseId === null => null,
            (int) $movement->to_warehouse_id === $warehouseId => 'in',
            (int) $movement->from_warehouse_id === $warehouseId => 'out',
            default => null,
        };
    }

    /**
     * Stop spreadsheet apps from treating user-entered text as a formula.
     */
    private function csvSafe(mixed $value): mixed
    {
        return is_string($value) && preg_match('/^[=+\-@\t\r]/', $value) === 1 ? "'".$value : $value;
    }
}
