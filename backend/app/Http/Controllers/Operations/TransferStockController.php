<?php

namespace App\Http\Controllers\Operations;

use App\Actions\Inventory\RecordStockMovement;
use App\Concerns\ScopesToOrganization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Operations\TransferStockRequest;
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

class TransferStockController extends Controller
{
    use ScopesToOrganization;

    /**
     * Show the transfer form with every warehouse (the source may be inactive) and every variant that is in
     * stock somewhere, together with its on-hand quantity per warehouse.
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

        return Inertia::render('operations/transfer', [
            'warehouses' => $organization->warehouses()
                ->orderBy('name')
                ->get(['id', 'name', 'code', 'is_active']),
            'products' => $products,
            'defaultWarehouseId' => $organization->defaultWarehouseId(),
        ]);
    }

    /**
     * Move every line from the source to the destination in one transaction: a transfer movement per line,
     * both balances adjusted, and any named serials relocated and linked to their movement.
     *
     * @throws ValidationException when stock or serials changed between validation and saving
     */
    public function store(TransferStockRequest $request, RecordStockMovement $recordStockMovement): RedirectResponse
    {
        $organization = $this->organization($request);
        $source = $organization->warehouses()->findOrFail($request->validated('from_warehouse_id'));
        $destination = $organization->warehouses()->findOrFail($request->validated('to_warehouse_id'));
        $lines = $request->validated('lines');
        $transferredAt = Carbon::parse($request->validated('transferred_at'));
        $notes = $request->validated('notes');

        $variants = $organization->productVariants()
            ->whereIn('id', array_column($lines, 'product_variant_id'))
            ->get()
            ->keyBy('id');

        DB::transaction(function () use ($organization, $lines, $variants, $source, $destination, $transferredAt, $notes, $request, $recordStockMovement): void {
            foreach ($lines as $index => $line) {
                $variant = $variants[$line['product_variant_id']];

                try {
                    $movement = $recordStockMovement->handle(
                        variant: $variant,
                        movementType: 'transfer',
                        quantity: (int) $line['quantity'],
                        fromWarehouse: $source,
                        toWarehouse: $destination,
                        performedBy: $request->user(),
                        occurredAt: $transferredAt,
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
                    ->where('current_warehouse_id', $source->id)
                    ->where('status', 'in_stock')
                    ->lockForUpdate()
                    ->get();

                if ($units->count() !== count($serials)) {
                    throw ValidationException::withMessages([
                        "lines.{$index}.serials" => __('Some of these serials just left :warehouse. Check them and try again.', ['warehouse' => $source->code]),
                    ]);
                }

                $organization->serialNumbers()
                    ->whereKey($units->modelKeys())
                    ->update(['current_warehouse_id' => $destination->id]);

                $movement->serialNumbers()->attach($units->modelKeys());
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => trans_choice(
            'Transferred :units units of :count variant from :from to :to.|Transferred :units units across :count variants from :from to :to.',
            count($lines),
            [
                'units' => array_sum(array_column($lines, 'quantity')),
                'from' => $source->code,
                'to' => $destination->code,
            ],
        )]);

        return to_route('operations.transfer.create');
    }
}
