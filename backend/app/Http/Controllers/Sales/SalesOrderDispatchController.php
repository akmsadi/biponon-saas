<?php

namespace App\Http\Controllers\Sales;

use App\Actions\Inventory\RecordStockMovement;
use App\Concerns\ScopesToOrganization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\DispatchSalesOrderRequest;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Models\StockBalance;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Dispatching stock against a confirmed sales order. Everyone may dispatch; each line can be split across
 * the warehouses that hold it.
 */
class SalesOrderDispatchController extends Controller
{
    use ScopesToOrganization;

    /**
     * Show the dispatch form with the lines still to send and where their stock is.
     */
    public function create(Request $request, SalesOrder $salesOrder): Response|RedirectResponse
    {
        $this->ensureBelongsToOrganization($request, $salesOrder);

        if (! $salesOrder->canDispatch()) {
            return $this->cannotDispatch($salesOrder);
        }

        $salesOrder->load([
            'customer:id,name',
            'warehouse:id,name,code',
            'lines.productVariant' => fn ($query) => $query->with(['product:id,name', 'size:id,name', 'color:id,name,hex_code']),
        ]);
        $lines = $salesOrder->lines
            ->filter(fn (SalesOrderLine $line): bool => $line->quantityRemaining() > 0)
            ->sortBy('id')
            ->values();
        $stock = $this->organization($request)->stockBalances()
            ->whereIn('product_variant_id', $lines->pluck('product_variant_id'))
            ->where('quantity', '>', 0)
            ->get()
            ->groupBy('product_variant_id');

        return Inertia::render('sales/orders/dispatch', [
            'order' => [
                'id' => $salesOrder->id,
                'number' => $salesOrder->number,
                'customer' => $salesOrder->customer->name,
                'warehouse' => $salesOrder->warehouse->only(['id', 'name', 'code']),
                'lines' => $lines->map(fn (SalesOrderLine $line): array => [
                    'id' => $line->id,
                    'product' => $line->productVariant->product->name,
                    'sku' => $line->productVariant->sku,
                    'size' => $line->productVariant->size->name,
                    'color' => $line->productVariant->color->name,
                    'hex_code' => $line->productVariant->color->hex_code,
                    'quantity_ordered' => $line->quantity_ordered,
                    'quantity_dispatched' => $line->quantity_dispatched,
                    'quantity_remaining' => $line->quantityRemaining(),
                    'stock' => ($stock[$line->product_variant_id] ?? collect())
                        ->mapWithKeys(fn (StockBalance $balance): array => [$balance->warehouse_id => $balance->quantity]),
                ]),
            ],
            'warehouses' => $this->organization($request)->warehouses()
                ->orderBy('name')
                ->get(['id', 'name', 'code', 'is_active']),
            'maxSplits' => DispatchSalesOrderRequest::MAX_SPLITS_PER_LINE,
        ]);
    }

    /**
     * Dispatch the entered quantities in one transaction: a dispatch movement per line and warehouse, named
     * serials marked as dispatched, the lines' dispatched quantities raised and the order's status moved on.
     */
    public function store(DispatchSalesOrderRequest $request, SalesOrder $salesOrder, RecordStockMovement $recordStockMovement): RedirectResponse
    {
        $organization = $this->organization($request);
        $dispatchedAt = Carbon::parse($request->validated('dispatched_at'));
        $notes = $request->validated('notes');
        $lines = $request->validated('lines');

        $warehouses = $organization->warehouses()
            ->whereIn('id', collect($lines)->pluck('allocations')->flatten(1)->pluck('warehouse_id')->filter()->unique())
            ->get()
            ->keyBy('id');

        $units = DB::transaction(function () use ($organization, $salesOrder, $lines, $warehouses, $dispatchedAt, $notes, $request, $recordStockMovement): int {
            // Lock the order so two people dispatching at once cannot both send the same remaining units.
            $order = SalesOrder::whereKey($salesOrder->id)->lockForUpdate()->firstOrFail();

            if (! $order->canDispatch()) {
                throw ValidationException::withMessages(['lines' => __(':number can no longer be dispatched.', ['number' => $order->number])]);
            }

            $orderLines = $order->lines()->with('productVariant')->get()->keyBy('id');
            $units = 0;

            foreach ($lines as $index => $line) {
                $orderLine = $orderLines[(int) $line['sales_order_line_id']];
                $lineUnits = 0;

                foreach ($line['allocations'] as $split => $allocation) {
                    $quantity = (int) $allocation['quantity'];

                    if ($quantity === 0) {
                        continue;
                    }

                    $lineUnits += $quantity;
                    $warehouse = $warehouses[(int) $allocation['warehouse_id']];

                    try {
                        $movement = $recordStockMovement->handle(
                            variant: $orderLine->productVariant,
                            movementType: 'dispatch',
                            quantity: $quantity,
                            fromWarehouse: $warehouse,
                            toWarehouse: null,
                            performedBy: $request->user(),
                            occurredAt: $dispatchedAt,
                            notes: $notes,
                            salesOrderLine: $orderLine,
                        );
                    } catch (DomainException $exception) {
                        throw ValidationException::withMessages(["lines.{$index}.allocations.{$split}.quantity" => $exception->getMessage()]);
                    }

                    $serials = $allocation['serials'] ?? [];

                    if ($serials === []) {
                        continue;
                    }

                    $serialUnits = $organization->serialNumbers()
                        ->whereIn('serial_number', $serials)
                        ->where('product_variant_id', $orderLine->product_variant_id)
                        ->where('current_warehouse_id', $warehouse->id)
                        ->where('status', 'in_stock')
                        ->lockForUpdate()
                        ->get();

                    if ($serialUnits->count() !== count($serials)) {
                        throw ValidationException::withMessages([
                            "lines.{$index}.allocations.{$split}.serials" => __('Some of these serials just left :warehouse. Check them and try again.', ['warehouse' => $warehouse->code]),
                        ]);
                    }

                    $organization->serialNumbers()
                        ->whereKey($serialUnits->modelKeys())
                        ->update(['status' => 'dispatched', 'current_warehouse_id' => null]);

                    $movement->serialNumbers()->attach($serialUnits->modelKeys());
                }

                if ($lineUnits > $orderLine->quantityRemaining()) {
                    throw ValidationException::withMessages(["lines.{$index}.allocations" => trans_choice(
                        'Only :count unit is still to send on this line.|Only :count units are still to send on this line.',
                        $orderLine->quantityRemaining(),
                    )]);
                }

                if ($lineUnits > 0) {
                    $orderLine->increment('quantity_dispatched', $lineUnits);
                }

                $units += $lineUnits;
            }

            $order->update([
                'status' => $orderLines->every(fn (SalesOrderLine $line): bool => $line->quantityRemaining() === 0)
                    ? 'dispatched'
                    : 'partially_dispatched',
            ]);

            return $units;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => trans_choice(
            'Dispatched :count unit against :number.|Dispatched :count units against :number.',
            $units,
            ['number' => $salesOrder->number],
        )]);

        return to_route('sales.orders.show', $salesOrder);
    }

    private function cannotDispatch(SalesOrder $order): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'error', 'message' => __(':number cannot be dispatched.', ['number' => $order->number])]);

        return to_route('sales.orders.show', $order);
    }
}
