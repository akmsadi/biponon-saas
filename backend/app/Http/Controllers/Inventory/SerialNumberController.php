<?php

namespace App\Http\Controllers\Inventory;

use App\Concerns\ScopesToOrganization;
use App\Http\Controllers\Controller;
use App\Models\SerialNumber;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Read-only lookup of serial-tracked units. Their status and location only change through stock operations.
 */
class SerialNumberController extends Controller
{
    use ScopesToOrganization;

    private const PER_PAGE = 25;

    /**
     * List the organization's serial numbers, filtered by search (serial, SKU or product), warehouse and status.
     */
    public function index(Request $request): Response
    {
        $organization = $this->organization($request);

        $filters = [
            'search' => mb_substr(trim($request->string('search')->toString()), 0, 150),
            'warehouse' => $request->integer('warehouse') ?: null,
            'status' => mb_substr($request->string('status')->toString(), 0, 30) ?: null,
        ];

        $serials = SerialNumber::query()
            ->where('serial_numbers.organization_id', $organization->id)
            ->join('product_variants', 'product_variants.id', '=', 'serial_numbers.product_variant_id')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->join('sizes', 'sizes.id', '=', 'product_variants.size_id')
            ->join('colors', 'colors.id', '=', 'product_variants.color_id')
            ->leftJoin('warehouses', 'warehouses.id', '=', 'serial_numbers.current_warehouse_id')
            ->when($filters['warehouse'], fn (Builder $query, int $warehouseId) => $query->where('serial_numbers.current_warehouse_id', $warehouseId))
            ->when($filters['status'], fn (Builder $query, string $status) => $query->where('serial_numbers.status', $status))
            ->when($filters['search'] !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('serial_numbers.serial_number', 'like', '%'.$filters['search'].'%')
                ->orWhere('product_variants.sku', 'like', '%'.$filters['search'].'%')
                ->orWhere('products.name', 'like', '%'.$filters['search'].'%')))
            ->orderByDesc('serial_numbers.received_at')
            ->orderBy('serial_numbers.serial_number')
            ->select([
                'serial_numbers.id',
                'serial_numbers.serial_number',
                'serial_numbers.status',
                'serial_numbers.received_at',
                'products.id as product_id',
                'products.name as product_name',
                'product_variants.sku',
                'sizes.name as size',
                'colors.name as color',
                'colors.hex_code',
                'warehouses.name as warehouse_name',
                'warehouses.code as warehouse_code',
            ])
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (SerialNumber $serial): array => [
                'id' => $serial->id,
                'serial_number' => $serial->serial_number,
                'status' => $serial->status,
                'received_at' => $serial->received_at?->toIso8601String(),
                'product' => ['id' => $serial->product_id, 'name' => $serial->product_name],
                'sku' => $serial->sku,
                'size' => $serial->size,
                'color' => $serial->color,
                'hex_code' => $serial->hex_code,
                'warehouse' => $serial->warehouse_code === null
                    ? null
                    : ['name' => $serial->warehouse_name, 'code' => $serial->warehouse_code],
            ]);

        return Inertia::render('inventory/serials', [
            'serials' => $serials,
            'warehouses' => $organization->warehouses()->orderBy('name')->get(['id', 'name', 'code']),
            'statuses' => $organization->serialNumbers()->distinct()->orderBy('status')->pluck('status'),
            'filters' => $filters,
        ]);
    }

    /**
     * The movements a serial took part in, newest first, as JSON for the history dialog.
     */
    public function history(Request $request, SerialNumber $serialNumber): JsonResponse
    {
        $this->ensureBelongsToOrganization($request, $serialNumber);

        $movements = $serialNumber->stockMovements()
            ->with(['fromWarehouse:id,name,code', 'toWarehouse:id,name,code', 'performedBy:id,name'])
            ->orderByDesc('occurred_at')
            ->orderByDesc('stock_movements.id')
            ->get()
            ->map(fn (StockMovement $movement): array => [
                'id' => $movement->id,
                'movement_type' => $movement->movement_type,
                'quantity' => $movement->quantity,
                'from' => $movement->fromWarehouse?->only(['name', 'code']),
                'to' => $movement->toWarehouse?->only(['name', 'code']),
                'performed_by' => $movement->performedBy?->name,
                'notes' => $movement->notes,
                'occurred_at' => $movement->occurred_at->toIso8601String(),
            ]);

        return response()->json(['movements' => $movements]);
    }
}
