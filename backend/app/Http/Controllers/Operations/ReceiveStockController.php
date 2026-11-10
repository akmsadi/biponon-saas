<?php

namespace App\Http\Controllers\Operations;

use App\Actions\Inventory\RecordStockMovement;
use App\Concerns\ScopesToOrganization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Operations\ReceiveStockRequest;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SerialNumber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ReceiveStockController extends Controller
{
    use ScopesToOrganization;

    /**
     * Show the receive form with the active warehouses and the active variants of active products.
     */
    public function create(Request $request): Response
    {
        $organization = $this->organization($request);

        $products = $organization->products()
            ->where('is_active', true)
            ->whereHas('productVariants', fn (Builder $query) => $query->where('is_active', true))
            ->with(['productVariants' => fn ($query) => $query
                ->where('is_active', true)
                ->with(['size:id,name,sort_order', 'color:id,name,hex_code,sort_order'])
                ->select(['id', 'product_id', 'size_id', 'color_id', 'sku']),
            ])
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Product $product): array => [
                'id' => $product->id,
                'name' => $product->name,
                'variants' => $product->productVariants
                    ->sortBy([
                        ['size.sort_order', 'asc'],
                        ['size.name', 'asc'],
                        ['color.sort_order', 'asc'],
                        ['color.name', 'asc'],
                    ])
                    ->map(fn (ProductVariant $variant): array => [
                        'id' => $variant->id,
                        'sku' => $variant->sku,
                        'size' => $variant->size->name,
                        'color' => $variant->color->name,
                        'hex_code' => $variant->color->hex_code,
                    ])
                    ->values(),
            ]);

        return Inertia::render('operations/receive', [
            'warehouses' => $organization->warehouses()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'code']),
            'products' => $products,
        ]);
    }

    /**
     * Receive every line into the warehouse in one transaction: a movement per line, the balance increased,
     * and any serial numbers created in stock and linked to their movement.
     */
    public function store(ReceiveStockRequest $request, RecordStockMovement $recordStockMovement): RedirectResponse
    {
        $organization = $this->organization($request);
        $warehouse = $organization->warehouses()->findOrFail($request->validated('warehouse_id'));
        $lines = $request->validated('lines');
        $receivedAt = Carbon::parse($request->validated('received_at'));
        $notes = $request->validated('notes');

        $variants = $organization->productVariants()
            ->whereIn('id', array_column($lines, 'product_variant_id'))
            ->get()
            ->keyBy('id');

        DB::transaction(function () use ($lines, $variants, $warehouse, $receivedAt, $notes, $request, $recordStockMovement): void {
            foreach ($lines as $line) {
                $variant = $variants[$line['product_variant_id']];

                $movement = $recordStockMovement->handle(
                    variant: $variant,
                    movementType: 'receive',
                    quantity: (int) $line['quantity'],
                    fromWarehouse: null,
                    toWarehouse: $warehouse,
                    performedBy: $request->user(),
                    occurredAt: $receivedAt,
                    notes: $notes,
                );

                foreach ($line['serials'] ?? [] as $serial) {
                    $movement->serialNumbers()->attach(SerialNumber::create([
                        'organization_id' => $variant->organization_id,
                        'product_variant_id' => $variant->id,
                        'current_warehouse_id' => $warehouse->id,
                        'serial_number' => $serial,
                        'status' => 'in_stock',
                        'received_at' => $receivedAt,
                    ]));
                }
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => trans_choice(
            'Received :units units of :count variant into :warehouse.|Received :units units across :count variants into :warehouse.',
            count($lines),
            ['units' => array_sum(array_column($lines, 'quantity')), 'warehouse' => $warehouse->code],
        )]);

        return to_route('operations.receive.create');
    }
}
