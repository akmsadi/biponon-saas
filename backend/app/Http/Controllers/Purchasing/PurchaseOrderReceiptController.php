<?php

namespace App\Http\Controllers\Purchasing;

use App\Actions\Inventory\RecordStockMovement;
use App\Concerns\ScopesToOrganization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Purchasing\ReceivePurchaseOrderRequest;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\SerialNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Receiving stock against a placed purchase order. Everyone may receive; each line can be split across
 * several active warehouses, and costs from the order are copied onto the receipt movements.
 */
class PurchaseOrderReceiptController extends Controller
{
    use ScopesToOrganization;

    /**
     * Show the receive form with the lines still expected.
     */
    public function create(Request $request, PurchaseOrder $purchaseOrder): Response|RedirectResponse
    {
        $this->ensureBelongsToOrganization($request, $purchaseOrder);

        if (! $purchaseOrder->canReceive()) {
            return $this->cannotReceive($purchaseOrder);
        }

        $purchaseOrder->load([
            'supplier:id,name',
            'warehouse:id,name,code,is_active',
            'lines.productVariant' => fn ($query) => $query->with(['product:id,name', 'size:id,name', 'color:id,name,hex_code']),
        ]);

        return Inertia::render('purchasing/orders/receive', [
            'order' => [
                'id' => $purchaseOrder->id,
                'number' => $purchaseOrder->number,
                'supplier' => $purchaseOrder->supplier->name,
                'warehouse' => $purchaseOrder->warehouse->only(['id', 'name', 'code', 'is_active']),
                'lines' => $purchaseOrder->lines
                    ->filter(fn (PurchaseOrderLine $line): bool => $line->quantityRemaining() > 0)
                    ->sortBy('id')
                    ->values()
                    ->map(fn (PurchaseOrderLine $line): array => [
                        'id' => $line->id,
                        'product' => $line->productVariant->product->name,
                        'sku' => $line->productVariant->sku,
                        'size' => $line->productVariant->size->name,
                        'color' => $line->productVariant->color->name,
                        'hex_code' => $line->productVariant->color->hex_code,
                        'quantity_ordered' => $line->quantity_ordered,
                        'quantity_received' => $line->quantity_received,
                        'quantity_remaining' => $line->quantityRemaining(),
                    ]),
            ],
            'warehouses' => $this->organization($request)->warehouses()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'code']),
            'maxSplits' => ReceivePurchaseOrderRequest::MAX_SPLITS_PER_LINE,
        ]);
    }

    /**
     * Receive the entered quantities in one transaction: a receipt movement per line and warehouse, new
     * serials in stock, the lines' received quantities raised and the order's status moved on.
     */
    public function store(ReceivePurchaseOrderRequest $request, PurchaseOrder $purchaseOrder, RecordStockMovement $recordStockMovement): RedirectResponse
    {
        $organization = $this->organization($request);
        $receivedAt = Carbon::parse($request->validated('received_at'));
        $notes = $request->validated('notes');
        $lines = $request->validated('lines');

        $warehouses = $organization->warehouses()
            ->whereIn('id', collect($lines)->pluck('allocations')->flatten(1)->pluck('warehouse_id')->filter()->unique())
            ->get()
            ->keyBy('id');

        $units = DB::transaction(function () use ($purchaseOrder, $lines, $warehouses, $receivedAt, $notes, $request, $recordStockMovement): int {
            // Lock the order so two people receiving at once cannot both fill the same remaining units.
            $order = PurchaseOrder::whereKey($purchaseOrder->id)->lockForUpdate()->firstOrFail();

            if (! $order->canReceive()) {
                throw ValidationException::withMessages(['lines' => __(':number can no longer be received.', ['number' => $order->number])]);
            }

            $orderLines = $order->lines()->with('productVariant')->get()->keyBy('id');
            $units = 0;

            foreach ($lines as $index => $line) {
                $orderLine = $orderLines[(int) $line['purchase_order_line_id']];
                $allocations = array_filter($line['allocations'], fn (array $allocation): bool => (int) $allocation['quantity'] > 0);
                $lineUnits = array_sum(array_map(fn (array $allocation): int => (int) $allocation['quantity'], $allocations));

                if ($lineUnits > $orderLine->quantityRemaining()) {
                    throw ValidationException::withMessages(["lines.{$index}.allocations" => trans_choice(
                        'Only :count unit is still expected on this line.|Only :count units are still expected on this line.',
                        $orderLine->quantityRemaining(),
                    )]);
                }

                foreach ($allocations as $allocation) {
                    $warehouse = $warehouses[(int) $allocation['warehouse_id']];

                    $movement = $recordStockMovement->handle(
                        variant: $orderLine->productVariant,
                        movementType: 'receive',
                        quantity: (int) $allocation['quantity'],
                        fromWarehouse: null,
                        toWarehouse: $warehouse,
                        performedBy: $request->user(),
                        occurredAt: $receivedAt,
                        notes: $notes,
                        purchaseOrderLine: $orderLine,
                    );

                    foreach ($allocation['serials'] ?? [] as $serial) {
                        $movement->serialNumbers()->attach(SerialNumber::create([
                            'organization_id' => $order->organization_id,
                            'product_variant_id' => $orderLine->product_variant_id,
                            'current_warehouse_id' => $warehouse->id,
                            'serial_number' => $serial,
                            'status' => 'in_stock',
                            'received_at' => $receivedAt,
                        ]));
                    }
                }

                if ($lineUnits > 0) {
                    $orderLine->increment('quantity_received', $lineUnits);
                }

                $units += $lineUnits;
            }

            $order->update([
                'status' => $orderLines->every(fn (PurchaseOrderLine $line): bool => $line->quantityRemaining() === 0)
                    ? 'received'
                    : 'partially_received',
            ]);

            return $units;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => trans_choice(
            'Received :count unit against :number.|Received :count units against :number.',
            $units,
            ['number' => $purchaseOrder->number],
        )]);

        return to_route('purchasing.orders.show', $purchaseOrder);
    }

    private function cannotReceive(PurchaseOrder $order): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'error', 'message' => __(':number cannot be received.', ['number' => $order->number])]);

        return to_route('purchasing.orders.show', $order);
    }
}
