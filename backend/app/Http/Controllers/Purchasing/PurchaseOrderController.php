<?php

namespace App\Http\Controllers\Purchasing;

use App\Concerns\ScopesToOrganization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Purchasing\PurchaseOrderRequest;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Purchase orders. Everyone creates, edits and deletes drafts; managers and admins place and cancel orders
 * (enforced by the routes) and are the only ones who see or set unit costs.
 */
class PurchaseOrderController extends Controller
{
    use ScopesToOrganization;

    private const PER_PAGE = 25;

    /**
     * List the organization's purchase orders, newest first, filtered by search, status, supplier and warehouse.
     */
    public function index(Request $request): Response
    {
        $organization = $this->organization($request);
        $canSeeCosts = $this->roleIncludes($request, 'manager');
        $filters = [
            'search' => mb_substr(trim($request->string('search')->toString()), 0, 100),
            'status' => in_array($request->string('status')->toString(), PurchaseOrder::STATUSES, true) ? $request->string('status')->toString() : null,
            'supplier' => $request->integer('supplier') ?: null,
            'warehouse' => $request->integer('warehouse') ?: null,
        ];

        $orders = $organization->purchaseOrders()
            ->with(['supplier:id,name', 'warehouse:id,name,code'])
            ->withCount('lines')
            ->withSum('lines as units_ordered', 'quantity_ordered')
            ->withSum('lines as units_received', 'quantity_received')
            ->when($canSeeCosts, fn (Builder $query) => $query
                ->withCount(['lines as uncosted_lines_count' => fn (Builder $lines) => $lines->whereNull('unit_cost')])
                ->addSelect(['total_cost' => PurchaseOrderLine::selectRaw('sum(quantity_ordered * unit_cost)')
                    ->whereColumn('purchase_order_id', 'purchase_orders.id')]))
            ->when($filters['search'] !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('number', 'like', '%'.$filters['search'].'%')
                ->orWhereHas('supplier', fn (Builder $supplier) => $supplier->where('name', 'like', '%'.$filters['search'].'%'))))
            ->when($filters['status'], fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['supplier'], fn (Builder $query, int $supplier) => $query->where('supplier_id', $supplier))
            ->when($filters['warehouse'], fn (Builder $query, int $warehouse) => $query->where('warehouse_id', $warehouse))
            ->orderByDesc('sequence')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (PurchaseOrder $order): array => [
                'id' => $order->id,
                'number' => $order->number,
                'status' => $order->status,
                'supplier' => $order->supplier->name,
                'warehouse' => ['name' => $order->warehouse->name, 'code' => $order->warehouse->code],
                'expected_at' => $order->expected_at?->toDateString(),
                'lines_count' => $order->lines_count,
                'units_ordered' => (int) $order->units_ordered,
                'units_received' => (int) $order->units_received,
                ...($canSeeCosts ? [
                    'total_cost' => $this->money($order->total_cost === null ? null : (int) round((float) $order->total_cost * 100)),
                    'has_uncosted_lines' => $order->uncosted_lines_count > 0,
                ] : []),
            ]);

        return Inertia::render('purchasing/orders/index', [
            'orders' => $orders,
            'suppliers' => $organization->suppliers()->orderBy('name')->get(['id', 'name']),
            'warehouses' => $organization->warehouses()->orderBy('name')->get(['id', 'name', 'code']),
            'filters' => $filters,
            'canSeeCosts' => $canSeeCosts,
        ]);
    }

    /**
     * Show the form for a new draft.
     */
    public function create(Request $request): Response
    {
        $organization = $this->organization($request);

        return Inertia::render('purchasing/orders/form', [
            ...$this->formOptions($organization),
            'order' => null,
            'defaultWarehouseId' => $organization->defaultWarehouseId(),
            'canSeeCosts' => $this->roleIncludes($request, 'manager'),
        ]);
    }

    /**
     * Create a draft with the next number of the organization.
     */
    public function store(PurchaseOrderRequest $request): RedirectResponse
    {
        $organization = $this->organization($request);

        $order = DB::transaction(function () use ($organization, $request): PurchaseOrder {
            // Lock the organization so two drafts created at once cannot take the same number.
            Organization::whereKey($organization->id)->lockForUpdate()->first();

            $sequence = ($organization->purchaseOrders()->max('sequence') ?? 0) + 1;

            $order = $organization->purchaseOrders()->create([
                'sequence' => $sequence,
                'number' => PurchaseOrder::numberFor($sequence),
                'supplier_id' => $request->validated('supplier_id'),
                'warehouse_id' => $request->validated('warehouse_id'),
                'status' => 'draft',
                'expected_at' => $request->validated('expected_at'),
                'notes' => $request->validated('notes'),
                'created_by' => $request->user()->id,
            ]);

            $this->saveLines($order, $request->validated('lines'), $request->canSetCosts());

            return $order;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':number saved as a draft.', ['number' => $order->number])]);

        return to_route('purchasing.orders.show', $order);
    }

    /**
     * Show one order with its lines. Costs and totals are left out for members.
     */
    public function show(Request $request, PurchaseOrder $purchaseOrder): Response
    {
        $this->ensureBelongsToOrganization($request, $purchaseOrder);

        $canSeeCosts = $this->roleIncludes($request, 'manager');
        $purchaseOrder->load([
            'supplier:id,name,contact_name,email,phone',
            'warehouse:id,name,code',
            'creator:id,name',
            'lines.productVariant' => fn ($query) => $query->with(['product:id,name', 'size:id,name', 'color:id,name,hex_code']),
        ]);
        $lines = $purchaseOrder->lines->sortBy('id')->values();
        $lineCents = $lines->map(fn (PurchaseOrderLine $line): ?int => $line->totalCents());

        return Inertia::render('purchasing/orders/show', [
            'order' => [
                'id' => $purchaseOrder->id,
                'number' => $purchaseOrder->number,
                'status' => $purchaseOrder->status,
                'supplier' => $purchaseOrder->supplier->only(['id', 'name', 'contact_name', 'email', 'phone']),
                'warehouse' => $purchaseOrder->warehouse->only(['id', 'name', 'code']),
                'expected_at' => $purchaseOrder->expected_at?->toDateString(),
                'notes' => $purchaseOrder->notes,
                'created_by' => $purchaseOrder->creator?->name,
                'created_at' => $purchaseOrder->created_at->toIso8601String(),
                'ordered_at' => $purchaseOrder->ordered_at?->toIso8601String(),
                'cancelled_at' => $purchaseOrder->cancelled_at?->toIso8601String(),
                'units_ordered' => $lines->sum('quantity_ordered'),
                'units_received' => $lines->sum('quantity_received'),
                ...($canSeeCosts ? [
                    'total_cost' => $lineCents->filter(fn (?int $cents): bool => $cents !== null)->isEmpty()
                        ? null
                        : $this->money($lineCents->sum()),
                    'has_uncosted_lines' => $lineCents->contains(null),
                ] : []),
                'lines' => $lines->map(fn (PurchaseOrderLine $line, int $index): array => [
                    'id' => $line->id,
                    'product' => $line->productVariant->product->only(['id', 'name']),
                    'sku' => $line->productVariant->sku,
                    'size' => $line->productVariant->size->name,
                    'color' => $line->productVariant->color->name,
                    'hex_code' => $line->productVariant->color->hex_code,
                    'quantity_ordered' => $line->quantity_ordered,
                    'quantity_received' => $line->quantity_received,
                    ...($canSeeCosts ? [
                        'unit_cost' => $line->unit_cost,
                        'total_cost' => $this->money($lineCents[$index]),
                    ] : []),
                ]),
            ],
            'can' => [
                'edit' => $purchaseOrder->isDraft(),
                'delete' => $purchaseOrder->isDraft(),
                'place' => $purchaseOrder->isDraft() && $this->roleIncludes($request, 'manager'),
                'cancel' => $purchaseOrder->canBeCancelled() && $this->roleIncludes($request, 'manager'),
            ],
            'canSeeCosts' => $canSeeCosts,
        ]);
    }

    /**
     * Show the form for editing a draft.
     */
    public function edit(Request $request, PurchaseOrder $purchaseOrder): Response|RedirectResponse
    {
        $this->ensureBelongsToOrganization($request, $purchaseOrder);

        if (! $purchaseOrder->isDraft()) {
            return $this->notADraft($purchaseOrder);
        }

        $canSeeCosts = $this->roleIncludes($request, 'manager');
        $purchaseOrder->load('lines.productVariant:id,product_id');

        return Inertia::render('purchasing/orders/form', [
            ...$this->formOptions($this->organization($request)),
            'order' => [
                'id' => $purchaseOrder->id,
                'number' => $purchaseOrder->number,
                'supplier_id' => $purchaseOrder->supplier_id,
                'warehouse_id' => $purchaseOrder->warehouse_id,
                'expected_at' => $purchaseOrder->expected_at?->toDateString(),
                'notes' => $purchaseOrder->notes,
                'lines' => $purchaseOrder->lines->sortBy('id')->values()->map(fn (PurchaseOrderLine $line): array => [
                    'product_id' => $line->productVariant->product_id,
                    'product_variant_id' => $line->product_variant_id,
                    'quantity' => $line->quantity_ordered,
                    ...($canSeeCosts ? ['unit_cost' => $line->unit_cost] : []),
                ]),
            ],
            'defaultWarehouseId' => null,
            'canSeeCosts' => $canSeeCosts,
        ]);
    }

    /**
     * Replace a draft's details and lines.
     */
    public function update(PurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->ensureBelongsToOrganization($request, $purchaseOrder);

        if (! $purchaseOrder->isDraft()) {
            return $this->notADraft($purchaseOrder);
        }

        DB::transaction(function () use ($purchaseOrder, $request): void {
            $purchaseOrder->update([
                'supplier_id' => $request->validated('supplier_id'),
                'warehouse_id' => $request->validated('warehouse_id'),
                'expected_at' => $request->validated('expected_at'),
                'notes' => $request->validated('notes'),
            ]);

            $this->saveLines($purchaseOrder, $request->validated('lines'), $request->canSetCosts());
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':number updated.', ['number' => $purchaseOrder->number])]);

        return to_route('purchasing.orders.show', $purchaseOrder);
    }

    /**
     * Delete a draft. Orders that were placed are cancelled instead, so their history stays.
     */
    public function destroy(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->ensureBelongsToOrganization($request, $purchaseOrder);

        if (! $purchaseOrder->isDraft()) {
            return $this->notADraft($purchaseOrder);
        }

        $purchaseOrder->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':number deleted.', ['number' => $purchaseOrder->number])]);

        return to_route('purchasing.orders.index');
    }

    /**
     * Mark a draft as ordered from the supplier, which locks its lines.
     */
    public function place(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->ensureBelongsToOrganization($request, $purchaseOrder);

        if (! $purchaseOrder->isDraft()) {
            return $this->notADraft($purchaseOrder);
        }

        $purchaseOrder->update(['status' => 'ordered', 'ordered_at' => now()]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':number marked as ordered.', ['number' => $purchaseOrder->number])]);

        return back();
    }

    /**
     * Cancel a draft or an order that has not received anything.
     */
    public function cancel(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->ensureBelongsToOrganization($request, $purchaseOrder);

        if (! $purchaseOrder->canBeCancelled()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __(':number can no longer be cancelled.', ['number' => $purchaseOrder->number])]);

            return back();
        }

        $purchaseOrder->update(['status' => 'cancelled', 'cancelled_at' => now()]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':number cancelled.', ['number' => $purchaseOrder->number])]);

        return back();
    }

    /**
     * Replace the order's lines. When the user may not set costs, the costs already on the order are kept
     * for variants that stay on it.
     *
     * @param  list<array{product_variant_id: int|string, quantity: int|string, unit_cost?: string|float|int|null}>  $lines
     */
    private function saveLines(PurchaseOrder $order, array $lines, bool $canSetCosts): void
    {
        $existingCosts = $order->lines()->pluck('unit_cost', 'product_variant_id');

        $order->lines()->delete();

        $order->lines()->createMany(array_map(fn (array $line): array => [
            'product_variant_id' => (int) $line['product_variant_id'],
            'quantity_ordered' => (int) $line['quantity'],
            'unit_cost' => $canSetCosts
                ? ($line['unit_cost'] ?? null)
                : ($existingCosts[(int) $line['product_variant_id']] ?? null),
        ], $lines));
    }

    /**
     * Active suppliers, active warehouses and the active variants of active products, for the draft form.
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

        return [
            'suppliers' => $organization->suppliers()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'warehouses' => $organization->warehouses()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']),
            'products' => $products,
        ];
    }

    private function notADraft(PurchaseOrder $order): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'error', 'message' => __(':number is no longer a draft.', ['number' => $order->number])]);

        return to_route('purchasing.orders.show', $order);
    }

    /**
     * Whole cents as a decimal string such as "85000.00".
     */
    private function money(?int $cents): ?string
    {
        return $cents === null ? null : number_format($cents / 100, 2, '.', '');
    }
}
