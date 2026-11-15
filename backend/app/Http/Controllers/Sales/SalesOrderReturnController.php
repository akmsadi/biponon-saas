<?php

namespace App\Http\Controllers\Sales;

use App\Actions\Inventory\RecordStockMovement;
use App\Concerns\ScopesToOrganization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\ReturnSalesOrderRequest;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Customer returns against a sales order. Everyone may record them; returned units go back into stock as
 * sellable in one active warehouse. A return does not reopen the order or change its prices.
 */
class SalesOrderReturnController extends Controller
{
    use ScopesToOrganization;

    /**
     * Show the return form with the lines that have units out with the customer.
     */
    public function create(Request $request, SalesOrder $salesOrder): Response|RedirectResponse
    {
        $this->ensureBelongsToOrganization($request, $salesOrder);

        $salesOrder->load([
            'customer:id,name',
            'warehouse:id,name,code,is_active',
            'lines.productVariant' => fn ($query) => $query->with(['product:id,name', 'size:id,name', 'color:id,name,hex_code']),
        ]);
        $lines = $salesOrder->lines
            ->filter(fn (SalesOrderLine $line): bool => $line->quantityReturnable() > 0)
            ->sortBy('id')
            ->values();

        if (! $salesOrder->canReturn() || $lines->isEmpty()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __(':number has nothing to return.', ['number' => $salesOrder->number])]);

            return to_route('sales.orders.show', $salesOrder);
        }

        return Inertia::render('sales/orders/return', [
            'order' => [
                'id' => $salesOrder->id,
                'number' => $salesOrder->number,
                'customer' => $salesOrder->customer->name,
                'warehouse' => $salesOrder->warehouse->only(['id', 'name', 'code', 'is_active']),
                'lines' => $lines->map(fn (SalesOrderLine $line): array => [
                    'id' => $line->id,
                    'product' => $line->productVariant->product->name,
                    'sku' => $line->productVariant->sku,
                    'size' => $line->productVariant->size->name,
                    'color' => $line->productVariant->color->name,
                    'hex_code' => $line->productVariant->color->hex_code,
                    'quantity_dispatched' => $line->quantity_dispatched,
                    'quantity_returned' => $line->quantity_returned,
                    'quantity_returnable' => $line->quantityReturnable(),
                ]),
            ],
            'warehouses' => $this->organization($request)->warehouses()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'code']),
        ]);
    }

    /**
     * Take the entered quantities back in one transaction: a return movement per line into the chosen
     * warehouse, named serials back in stock there, and the lines' returned quantities raised.
     */
    public function store(ReturnSalesOrderRequest $request, SalesOrder $salesOrder, RecordStockMovement $recordStockMovement): RedirectResponse
    {
        $organization = $this->organization($request);
        $warehouse = $organization->warehouses()->findOrFail($request->validated('warehouse_id'));
        $returnedAt = Carbon::parse($request->validated('returned_at'));
        $notes = $request->validated('notes');
        $lines = $request->validated('lines');

        $units = DB::transaction(function () use ($organization, $salesOrder, $lines, $warehouse, $returnedAt, $notes, $request, $recordStockMovement): int {
            // Lock the order so two people recording the same return at once cannot both take it back.
            $order = SalesOrder::whereKey($salesOrder->id)->lockForUpdate()->firstOrFail();

            if (! $order->canReturn()) {
                throw ValidationException::withMessages(['lines' => __(':number has nothing to return.', ['number' => $order->number])]);
            }

            $orderLines = $order->lines()->with('productVariant')->get()->keyBy('id');
            $units = 0;

            foreach ($lines as $index => $line) {
                $quantity = (int) $line['quantity'];

                if ($quantity === 0) {
                    continue;
                }

                $orderLine = $orderLines[(int) $line['sales_order_line_id']];

                if ($quantity > $orderLine->quantityReturnable()) {
                    throw ValidationException::withMessages(["lines.{$index}.quantity" => trans_choice(
                        'Only :count unit was sent and not yet returned.|Only :count units were sent and not yet returned.',
                        $orderLine->quantityReturnable(),
                    )]);
                }

                $movement = $recordStockMovement->handle(
                    variant: $orderLine->productVariant,
                    movementType: 'return',
                    quantity: $quantity,
                    fromWarehouse: null,
                    toWarehouse: $warehouse,
                    performedBy: $request->user(),
                    occurredAt: $returnedAt,
                    notes: $notes,
                    salesOrderLine: $orderLine,
                );

                $serials = $line['serials'] ?? [];

                if ($serials !== []) {
                    $serialUnits = $organization->serialNumbers()
                        ->whereIn('serial_number', $serials)
                        ->where('product_variant_id', $orderLine->product_variant_id)
                        ->where('status', 'dispatched')
                        ->whereHas('stockMovements', fn (Builder $movements) => $movements
                            ->where('sales_order_line_id', $orderLine->id)
                            ->where('movement_type', 'dispatch'))
                        ->lockForUpdate()
                        ->get();

                    if ($serialUnits->count() !== count($serials)) {
                        throw ValidationException::withMessages([
                            "lines.{$index}.serials" => __('Some of these serials were just returned. Check them and try again.'),
                        ]);
                    }

                    $organization->serialNumbers()
                        ->whereKey($serialUnits->modelKeys())
                        ->update(['status' => 'in_stock', 'current_warehouse_id' => $warehouse->id]);

                    $movement->serialNumbers()->attach($serialUnits->modelKeys());
                }

                $orderLine->increment('quantity_returned', $quantity);
                $units += $quantity;
            }

            return $units;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => trans_choice(
            'Returned :count unit against :number into :warehouse.|Returned :count units against :number into :warehouse.',
            $units,
            ['number' => $salesOrder->number, 'warehouse' => $warehouse->code],
        )]);

        return to_route('sales.orders.show', $salesOrder);
    }
}
