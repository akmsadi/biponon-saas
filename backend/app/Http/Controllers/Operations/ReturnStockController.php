<?php

namespace App\Http\Controllers\Operations;

use App\Actions\Inventory\RecordStockMovement;
use App\Concerns\ScopesToOrganization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Operations\ReturnStockRequest;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ReturnStockController extends Controller
{
    use ScopesToOrganization;

    /**
     * Show the return form with the active warehouses and every variant, including inactive ones, since units
     * sold before a product was retired can still come back.
     */
    public function create(Request $request): Response
    {
        $organization = $this->organization($request);

        $products = $organization->products()
            ->has('productVariants')
            ->with(['productVariants' => fn ($query) => $query
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

        return Inertia::render('operations/return', [
            'warehouses' => $organization->warehouses()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'code']),
            'products' => $products,
            'defaultWarehouseId' => $organization->defaultWarehouseId(),
        ]);
    }

    /**
     * Take every line back into the warehouse in one transaction: a return movement per line, the balance
     * increased, and any named serials put back in stock there and linked to their movement.
     *
     * @throws ValidationException when a named serial changed between validation and saving
     */
    public function store(ReturnStockRequest $request, RecordStockMovement $recordStockMovement): RedirectResponse
    {
        $organization = $this->organization($request);
        $warehouse = $organization->warehouses()->findOrFail($request->validated('warehouse_id'));
        $lines = $request->validated('lines');
        $returnedAt = Carbon::parse($request->validated('returned_at'));
        $notes = $request->validated('notes');

        $variants = $organization->productVariants()
            ->whereIn('id', array_column($lines, 'product_variant_id'))
            ->get()
            ->keyBy('id');

        DB::transaction(function () use ($organization, $lines, $variants, $warehouse, $returnedAt, $notes, $request, $recordStockMovement): void {
            foreach ($lines as $index => $line) {
                $variant = $variants[$line['product_variant_id']];

                $movement = $recordStockMovement->handle(
                    variant: $variant,
                    movementType: 'return',
                    quantity: (int) $line['quantity'],
                    fromWarehouse: null,
                    toWarehouse: $warehouse,
                    performedBy: $request->user(),
                    occurredAt: $returnedAt,
                    notes: $notes,
                );

                $serials = $line['serials'] ?? [];

                if ($serials === []) {
                    continue;
                }

                $units = $organization->serialNumbers()
                    ->whereIn('serial_number', $serials)
                    ->where('product_variant_id', $variant->id)
                    ->where('status', 'dispatched')
                    ->lockForUpdate()
                    ->get();

                if ($units->count() !== count($serials)) {
                    throw ValidationException::withMessages([
                        "lines.{$index}.serials" => __('Some of these serials were just returned. Check them and try again.'),
                    ]);
                }

                $organization->serialNumbers()
                    ->whereKey($units->modelKeys())
                    ->update(['status' => 'in_stock', 'current_warehouse_id' => $warehouse->id]);

                $movement->serialNumbers()->attach($units->modelKeys());
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => trans_choice(
            'Returned :units units of :count variant into :warehouse.|Returned :units units across :count variants into :warehouse.',
            count($lines),
            ['units' => array_sum(array_column($lines, 'quantity')), 'warehouse' => $warehouse->code],
        )]);

        return to_route('operations.return.create');
    }
}
