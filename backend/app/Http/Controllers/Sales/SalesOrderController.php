<?php

namespace App\Http\Controllers\Sales;

use App\Concerns\ScopesToOrganization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\SalesOrderRequest;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Sales orders. Everyone creates, edits and deletes drafts and sees prices; managers and admins confirm,
 * cancel and close orders (enforced by the routes). Stock is not reserved, so short orders can be confirmed.
 */
class SalesOrderController extends Controller
{
    use ScopesToOrganization;

    private const PER_PAGE = 25;

    /**
     * List the organization's sales orders, newest first, filtered by search, status, customer and warehouse.
     */
    public function index(Request $request): Response
    {
        $organization = $this->organization($request);
        $filters = [
            'search' => mb_substr(trim($request->string('search')->toString()), 0, 100),
            'status' => in_array($request->string('status')->toString(), SalesOrder::STATUSES, true) ? $request->string('status')->toString() : null,
            'customer' => $request->integer('customer') ?: null,
            'warehouse' => $request->integer('warehouse') ?: null,
        ];

        $orders = $organization->salesOrders()
            ->with(['customer:id,name', 'warehouse:id,name,code'])
            ->withCount('lines')
            ->withSum('lines as units_ordered', 'quantity_ordered')
            ->withSum('lines as units_dispatched', 'quantity_dispatched')
            ->addSelect(['total_price' => SalesOrderLine::selectRaw('sum(quantity_ordered * unit_price)')
                ->whereColumn('sales_order_id', 'sales_orders.id')])
            ->when($filters['search'] !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('number', 'like', '%'.$filters['search'].'%')
                ->orWhereHas('customer', fn (Builder $customer) => $customer->where('name', 'like', '%'.$filters['search'].'%'))))
            ->when($filters['status'], fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['customer'], fn (Builder $query, int $customer) => $query->where('customer_id', $customer))
            ->when($filters['warehouse'], fn (Builder $query, int $warehouse) => $query->where('warehouse_id', $warehouse))
            ->orderByDesc('sequence')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (SalesOrder $order): array => [
                'id' => $order->id,
                'number' => $order->number,
                'status' => $order->status,
                'customer' => $order->customer->name,
                'warehouse' => ['name' => $order->warehouse->name, 'code' => $order->warehouse->code],
                'deliver_by' => $order->deliver_by?->toDateString(),
                'lines_count' => $order->lines_count,
                'units_ordered' => (int) $order->units_ordered,
                'units_dispatched' => (int) $order->units_dispatched,
                'total_price' => $this->money($order->total_price === null ? null : (int) round((float) $order->total_price * 100)),
            ]);

        return Inertia::render('sales/orders/index', [
            'orders' => $orders,
            'customers' => $organization->customers()->orderBy('name')->get(['id', 'name']),
            'warehouses' => $organization->warehouses()->orderBy('name')->get(['id', 'name', 'code']),
            'filters' => $filters,
        ]);
    }

    /**
     * Show the form for a new draft.
     */
    public function create(Request $request): Response
    {
        $organization = $this->organization($request);

        return Inertia::render('sales/orders/form', [
            ...$this->formOptions($organization),
            'order' => null,
            'defaultWarehouseId' => $organization->defaultWarehouseId(),
        ]);
    }

    /**
     * Create a draft with the next number of the organization.
     */
    public function store(SalesOrderRequest $request): RedirectResponse
    {
        $organization = $this->organization($request);

        $order = DB::transaction(function () use ($organization, $request): SalesOrder {
            // Lock the organization so two drafts created at once cannot take the same number.
            Organization::whereKey($organization->id)->lockForUpdate()->first();

            $sequence = ($organization->salesOrders()->max('sequence') ?? 0) + 1;

            $order = $organization->salesOrders()->create([
                'sequence' => $sequence,
                'number' => SalesOrder::numberFor($sequence),
                'customer_id' => $request->validated('customer_id'),
                'warehouse_id' => $request->validated('warehouse_id'),
                'status' => 'draft',
                'deliver_by' => $request->validated('deliver_by'),
                'notes' => $request->validated('notes'),
                'created_by' => $request->user()->id,
            ]);

            $this->saveLines($order, $request->validated('lines'));

            return $order;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':number saved as a draft.', ['number' => $order->number])]);

        return to_route('sales.orders.show', $order);
    }

    /**
     * Show one order with its lines, prices and how much of each line is in stock right now.
     */
    public function show(Request $request, SalesOrder $salesOrder): Response
    {
        $this->ensureBelongsToOrganization($request, $salesOrder);

        $salesOrder->load([
            'customer:id,name,contact_name,email,phone,address',
            'warehouse:id,name,code',
            'creator:id,name',
            'lines.productVariant' => fn ($query) => $query
                ->with(['product:id,name', 'size:id,name', 'color:id,name,hex_code'])
                ->withSum('stockBalances as in_stock_total', 'quantity')
                ->withSum(['stockBalances as in_stock_here' => fn (Builder $balances) => $balances->where('warehouse_id', $salesOrder->warehouse_id)], 'quantity'),
        ]);
        $lines = $salesOrder->lines->sortBy('id')->values();
        $isOpen = in_array($salesOrder->status, ['draft', 'confirmed', 'partially_dispatched'], true);

        return Inertia::render('sales/orders/show', [
            'order' => [
                'id' => $salesOrder->id,
                'number' => $salesOrder->number,
                'status' => $salesOrder->status,
                'customer' => $salesOrder->customer->only(['id', 'name', 'contact_name', 'email', 'phone', 'address']),
                'warehouse' => $salesOrder->warehouse->only(['id', 'name', 'code']),
                'deliver_by' => $salesOrder->deliver_by?->toDateString(),
                'notes' => $salesOrder->notes,
                'created_by' => $salesOrder->creator?->name,
                'created_at' => $salesOrder->created_at->toIso8601String(),
                'confirmed_at' => $salesOrder->confirmed_at?->toIso8601String(),
                'cancelled_at' => $salesOrder->cancelled_at?->toIso8601String(),
                'closed_at' => $salesOrder->closed_at?->toIso8601String(),
                'units_ordered' => $lines->sum('quantity_ordered'),
                'units_dispatched' => $lines->sum('quantity_dispatched'),
                'total_price' => $this->money($lines->sum(fn (SalesOrderLine $line): int => $line->totalCents())),
                'lines' => $lines->map(fn (SalesOrderLine $line): array => [
                    'id' => $line->id,
                    'product' => $line->productVariant->product->only(['id', 'name']),
                    'sku' => $line->productVariant->sku,
                    'size' => $line->productVariant->size->name,
                    'color' => $line->productVariant->color->name,
                    'hex_code' => $line->productVariant->color->hex_code,
                    'quantity_ordered' => $line->quantity_ordered,
                    'quantity_dispatched' => $line->quantity_dispatched,
                    'unit_price' => $line->unit_price,
                    'total_price' => $this->money($line->totalCents()),
                    // Stock is only worth showing while the order still has something to send.
                    ...($isOpen ? [
                        'in_stock_here' => (int) $line->productVariant->in_stock_here,
                        'in_stock_total' => (int) $line->productVariant->in_stock_total,
                    ] : []),
                ]),
            ],
            'can' => [
                'edit' => $salesOrder->isDraft(),
                'delete' => $salesOrder->isDraft(),
                'confirm' => $salesOrder->isDraft() && $this->roleIncludes($request, 'manager'),
                'cancel' => $salesOrder->canBeCancelled() && $this->roleIncludes($request, 'manager'),
                'close' => $salesOrder->canBeClosed() && $this->roleIncludes($request, 'manager'),
            ],
        ]);
    }

    /**
     * Show the form for editing a draft.
     */
    public function edit(Request $request, SalesOrder $salesOrder): Response|RedirectResponse
    {
        $this->ensureBelongsToOrganization($request, $salesOrder);

        if (! $salesOrder->isDraft()) {
            return $this->notADraft($salesOrder);
        }

        $salesOrder->load('lines.productVariant:id,product_id');

        return Inertia::render('sales/orders/form', [
            ...$this->formOptions($this->organization($request)),
            'order' => [
                'id' => $salesOrder->id,
                'number' => $salesOrder->number,
                'customer_id' => $salesOrder->customer_id,
                'warehouse_id' => $salesOrder->warehouse_id,
                'deliver_by' => $salesOrder->deliver_by?->toDateString(),
                'notes' => $salesOrder->notes,
                'lines' => $salesOrder->lines->sortBy('id')->values()->map(fn (SalesOrderLine $line): array => [
                    'product_id' => $line->productVariant->product_id,
                    'product_variant_id' => $line->product_variant_id,
                    'quantity' => $line->quantity_ordered,
                    'unit_price' => $line->unit_price,
                ]),
            ],
            'defaultWarehouseId' => null,
        ]);
    }

    /**
     * Replace a draft's details and lines.
     */
    public function update(SalesOrderRequest $request, SalesOrder $salesOrder): RedirectResponse
    {
        $this->ensureBelongsToOrganization($request, $salesOrder);

        if (! $salesOrder->isDraft()) {
            return $this->notADraft($salesOrder);
        }

        DB::transaction(function () use ($salesOrder, $request): void {
            $salesOrder->update([
                'customer_id' => $request->validated('customer_id'),
                'warehouse_id' => $request->validated('warehouse_id'),
                'deliver_by' => $request->validated('deliver_by'),
                'notes' => $request->validated('notes'),
            ]);

            $this->saveLines($salesOrder, $request->validated('lines'));
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':number updated.', ['number' => $salesOrder->number])]);

        return to_route('sales.orders.show', $salesOrder);
    }

    /**
     * Delete a draft. Confirmed orders are cancelled instead, so their history stays.
     */
    public function destroy(Request $request, SalesOrder $salesOrder): RedirectResponse
    {
        $this->ensureBelongsToOrganization($request, $salesOrder);

        if (! $salesOrder->isDraft()) {
            return $this->notADraft($salesOrder);
        }

        $salesOrder->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':number deleted.', ['number' => $salesOrder->number])]);

        return to_route('sales.orders.index');
    }

    /**
     * Confirm a draft with the customer, which locks its lines. Short stock does not block confirmation.
     */
    public function confirm(Request $request, SalesOrder $salesOrder): RedirectResponse
    {
        $this->ensureBelongsToOrganization($request, $salesOrder);

        if (! $salesOrder->isDraft()) {
            return $this->notADraft($salesOrder);
        }

        $salesOrder->update(['status' => 'confirmed', 'confirmed_at' => now()]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':number confirmed.', ['number' => $salesOrder->number])]);

        return back();
    }

    /**
     * Cancel a draft or an order that has not dispatched anything.
     */
    public function cancel(Request $request, SalesOrder $salesOrder): RedirectResponse
    {
        $this->ensureBelongsToOrganization($request, $salesOrder);

        if (! $salesOrder->canBeCancelled()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __(':number can no longer be cancelled.', ['number' => $salesOrder->number])]);

            return back();
        }

        $salesOrder->update(['status' => 'cancelled', 'cancelled_at' => now()]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':number cancelled.', ['number' => $salesOrder->number])]);

        return back();
    }

    /**
     * Close a partly dispatched order when the rest will not be sent. What was sent stays sent.
     */
    public function close(Request $request, SalesOrder $salesOrder): RedirectResponse
    {
        $this->ensureBelongsToOrganization($request, $salesOrder);

        if (! $salesOrder->canBeClosed()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Only partly dispatched orders can be closed.')]);

            return back();
        }

        $salesOrder->update(['status' => 'closed', 'closed_at' => now()]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':number closed.', ['number' => $salesOrder->number])]);

        return back();
    }

    /**
     * Replace the order's lines.
     *
     * @param  list<array{product_variant_id: int|string, quantity: int|string, unit_price: string|float|int}>  $lines
     */
    private function saveLines(SalesOrder $order, array $lines): void
    {
        $order->lines()->delete();

        $order->lines()->createMany(array_map(fn (array $line): array => [
            'product_variant_id' => (int) $line['product_variant_id'],
            'quantity_ordered' => (int) $line['quantity'],
            'unit_price' => $line['unit_price'],
        ], $lines));
    }

    /**
     * Active customers, active warehouses and the active variants of active products with their list price
     * and stock per warehouse, for the draft form.
     *
     * @return array<string, mixed>
     */
    private function formOptions(Organization $organization): array
    {
        $products = $organization->products()
            ->where('is_active', true)
            ->whereHas('productVariants', fn (Builder $query) => $query->where('is_active', true))
            ->with(['productVariants' => fn ($query) => $query
                ->where('is_active', true)
                ->with(['size:id,name,sort_order', 'color:id,name,hex_code,sort_order', 'stockBalances:id,product_variant_id,warehouse_id,quantity'])
                ->select(['id', 'product_id', 'size_id', 'color_id', 'sku', 'price']),
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
                        'price' => $variant->price,
                        'stock' => $variant->stockBalances
                            ->filter(fn ($balance): bool => $balance->quantity > 0)
                            ->mapWithKeys(fn ($balance): array => [$balance->warehouse_id => $balance->quantity]),
                    ])
                    ->values(),
            ]);

        return [
            'customers' => $organization->customers()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'warehouses' => $organization->warehouses()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']),
            'products' => $products,
        ];
    }

    private function notADraft(SalesOrder $order): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'error', 'message' => __(':number is no longer a draft.', ['number' => $order->number])]);

        return to_route('sales.orders.show', $order);
    }

    /**
     * Whole cents as a decimal string such as "85000.00".
     */
    private function money(?int $cents): ?string
    {
        return $cents === null ? null : number_format($cents / 100, 2, '.', '');
    }
}
