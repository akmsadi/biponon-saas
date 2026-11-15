<?php

namespace App\Http\Controllers\Operations;

use App\Actions\Inventory\RecordStockMovement;
use App\Concerns\ScopesToOrganization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Operations\DispatchStockRequest;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockBalance;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class DispatchStockController extends Controller
{
    use ScopesToOrganization;

    /**
     * Show the dispatch form with every warehouse (inactive ones may still hold stock) and every variant that
     * is in stock somewhere, together with its on-hand quantity per warehouse.
     */
    public function create(Request $request): Response
    {
        $organization = $this->organization($request);

        $inStock = fn (Builder|HasMany $query) => $query->where('quantity', '>', 0);

        $products = $organization->products()
            ->whereHas('productVariants.stockBalances', $inStock)
            ->with(['productVariants' => fn ($query) => $query
                ->whereHas('stockBalances', $inStock)
                ->with([
                    'size:id,name,sort_order',
                    'color:id,name,hex_code,sort_order',
                    'stockBalances' => $inStock,
                ])
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
                        'stock' => $variant->stockBalances
                            ->mapWithKeys(fn (StockBalance $balance): array => [$balance->warehouse_id => $balance->quantity]),
                    ])
                    ->values(),
            ]);

        return Inertia::render('operations/dispatch', [
            'warehouses' => $organization->warehouses()
                ->orderBy('name')
                ->get(['id', 'name', 'code', 'is_active']),
            'products' => $products,
            'defaultWarehouseId' => $organization->defaultWarehouseId(),
        ]);
    }

    /**
     * Dispatch every line out of the warehouse in one transaction: a dispatch movement per line, the balance
     * reduced, and any named serials marked dispatched (no longer in a warehouse) and linked to their movement.
     *
     * @throws ValidationException when stock or serials changed between validation and saving
     */
    public function store(DispatchStockRequest $request, RecordStockMovement $recordStockMovement): RedirectResponse
    {
        $organization = $this->organization($request);
        $warehouse = $organization->warehouses()->findOrFail($request->validated('warehouse_id'));
        $lines = $request->validated('lines');
        $dispatchedAt = Carbon::parse($request->validated('dispatched_at'));
        $notes = $request->validated('notes');

        $variants = $organization->productVariants()
            ->whereIn('id', array_column($lines, 'product_variant_id'))
            ->get()
            ->keyBy('id');

        DB::transaction(function () use ($organization, $lines, $variants, $warehouse, $dispatchedAt, $notes, $request, $recordStockMovement): void {
            foreach ($lines as $index => $line) {
                $variant = $variants[$line['product_variant_id']];

                try {
                    $movement = $recordStockMovement->handle(
                        variant: $variant,
                        movementType: 'dispatch',
                        quantity: (int) $line['quantity'],
                        fromWarehouse: $warehouse,
                        toWarehouse: null,
                        performedBy: $request->user(),
                        occurredAt: $dispatchedAt,
                        notes: $notes,
                    );
                } catch (DomainException $exception) {
                    throw ValidationException::withMessages(["lines.{$index}.quantity" => $exception->getMessage()]);
                }

                $serials = $line['serials'] ?? [];

                if ($serials === []) {
                    continue;
                }

                $units = $organization->serialNumbers()
                    ->whereIn('serial_number', $serials)
                    ->where('product_variant_id', $variant->id)
                    ->where('current_warehouse_id', $warehouse->id)
                    ->where('status', 'in_stock')
                    ->lockForUpdate()
                    ->get();

                if ($units->count() !== count($serials)) {
                    throw ValidationException::withMessages([
                        "lines.{$index}.serials" => __('Some of these serials just left :warehouse. Check them and try again.', ['warehouse' => $warehouse->code]),
                    ]);
                }

                $organization->serialNumbers()
                    ->whereKey($units->modelKeys())
                    ->update(['status' => 'dispatched', 'current_warehouse_id' => null]);

                $movement->serialNumbers()->attach($units->modelKeys());
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => trans_choice(
            'Dispatched :units units of :count variant from :warehouse.|Dispatched :units units across :count variants from :warehouse.',
            count($lines),
            ['units' => array_sum(array_column($lines, 'quantity')), 'warehouse' => $warehouse->code],
        )]);

        return to_route('operations.dispatch.create');
    }
}
