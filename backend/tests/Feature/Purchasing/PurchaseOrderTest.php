<?php

namespace Tests\Feature\Purchasing;

use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PurchaseOrderTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private User $manager;

    private User $member;

    private Supplier $supplier;

    private Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();
        $this->manager = User::factory()->withOrganization($this->organization, 'manager')->create();
        $this->member = User::factory()->withOrganization($this->organization, 'member')->create();
        $this->supplier = Supplier::factory()->for($this->organization)->create();
        $this->warehouse = Warehouse::factory()->for($this->organization)->create();
    }

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get(route('purchasing.orders.index'))->assertRedirect(route('login'));
    }

    public function test_managers_create_numbered_drafts_with_costs(): void
    {
        [$shirt, $pants] = [$this->variant(), $this->variant()];

        $this->actingAs($this->manager)
            ->post(route('purchasing.orders.store'), $this->payload([
                ['product_variant_id' => $shirt->id, 'quantity' => 100, 'unit_cost' => '850.50'],
                ['product_variant_id' => $pants->id, 'quantity' => 10, 'unit_cost' => ''],
            ], ['expected_at' => '2026-12-01', 'notes' => 'Quote Q-17']))
            ->assertSessionHasNoErrors();

        $order = PurchaseOrder::sole();
        $this->assertSame('PO-0001', $order->number);
        $this->assertSame('draft', $order->status);
        $this->assertSame($this->manager->id, $order->created_by);
        $this->assertSame('2026-12-01', $order->expected_at->toDateString());
        $this->assertSame('850.50', $order->lines()->where('product_variant_id', $shirt->id)->value('unit_cost'));
        $this->assertNull($order->lines()->where('product_variant_id', $pants->id)->value('unit_cost'));

        $this->actingAs($this->manager)
            ->get(route('purchasing.orders.show', $order))
            ->assertInertia(fn (Assert $page) => $page
                ->component('purchasing/orders/show')
                ->where('order.total_cost', '85050.00')
                ->where('order.has_uncosted_lines', true)
                ->where('order.units_ordered', 110)
                ->where('order.lines.0.unit_cost', '850.50')
                ->where('order.lines.0.total_cost', '85050.00')
                ->where('can', ['edit' => true, 'delete' => true, 'place' => true, 'cancel' => true, 'receive' => false, 'close' => false]));
    }

    public function test_numbers_continue_per_organization(): void
    {
        PurchaseOrder::factory()->for($this->organization)->create();
        PurchaseOrder::factory()->for($this->organization)->create();
        PurchaseOrder::factory()->create();

        $this->actingAs($this->member)
            ->post(route('purchasing.orders.store'), $this->payload([['product_variant_id' => $this->variant()->id, 'quantity' => 1]]))
            ->assertSessionHasNoErrors();

        $this->assertSame(
            ['PO-0001', 'PO-0002', 'PO-0003'],
            $this->organization->purchaseOrders()->orderBy('sequence')->pluck('number')->all(),
        );
    }

    public function test_members_create_drafts_but_their_costs_are_ignored_and_hidden(): void
    {
        $variant = $this->variant();

        $this->actingAs($this->member)
            ->post(route('purchasing.orders.store'), $this->payload([
                ['product_variant_id' => $variant->id, 'quantity' => 5, 'unit_cost' => '999.00'],
            ]))
            ->assertSessionHasNoErrors();

        $order = PurchaseOrder::sole();
        $this->assertNull($order->lines()->value('unit_cost'));

        $order->lines()->update(['unit_cost' => '120.00']);

        $this->actingAs($this->member)
            ->get(route('purchasing.orders.show', $order))
            ->assertInertia(fn (Assert $page) => $page
                ->where('canSeeCosts', false)
                ->missing('order.total_cost')
                ->missing('order.lines.0.unit_cost')
                ->missing('order.lines.0.total_cost')
                ->where('can', ['edit' => true, 'delete' => true, 'place' => false, 'cancel' => false, 'receive' => false, 'close' => false]));

        $this->actingAs($this->member)
            ->get(route('purchasing.orders.edit', $order))
            ->assertInertia(fn (Assert $page) => $page->missing('order.lines.0.unit_cost'));

        $this->actingAs($this->member)
            ->get(route('purchasing.orders.index'))
            ->assertInertia(fn (Assert $page) => $page->missing('orders.data.0.total_cost'));
    }

    public function test_a_member_editing_a_draft_keeps_the_costs_a_manager_entered(): void
    {
        $order = PurchaseOrder::factory()->for($this->organization)->create(['supplier_id' => $this->supplier->id, 'warehouse_id' => $this->warehouse->id]);
        $kept = PurchaseOrderLine::factory()->for($order)->create(['unit_cost' => '400.00', 'quantity_ordered' => 3]);
        $removed = PurchaseOrderLine::factory()->for($order)->create();
        $added = $this->variant();

        $this->actingAs($this->member)
            ->put(route('purchasing.orders.update', $order), $this->payload([
                ['product_variant_id' => $kept->product_variant_id, 'quantity' => 7, 'unit_cost' => '1.00'],
                ['product_variant_id' => $added->id, 'quantity' => 2],
            ]))
            ->assertRedirect(route('purchasing.orders.show', $order))
            ->assertSessionHasNoErrors();

        $lines = $order->lines()->get()->keyBy('product_variant_id');
        $this->assertCount(2, $lines);
        $this->assertSame(7, $lines[$kept->product_variant_id]->quantity_ordered);
        $this->assertSame('400.00', $lines[$kept->product_variant_id]->unit_cost);
        $this->assertNull($lines[$added->id]->unit_cost);
        $this->assertFalse($lines->has($removed->product_variant_id));
    }

    public function test_drafts_need_an_active_supplier_warehouse_and_variants_with_valid_costs(): void
    {
        $inactiveSupplier = Supplier::factory()->for($this->organization)->inactive()->create();
        $foreignWarehouse = Warehouse::factory()->create();
        $inactiveVariant = ProductVariant::factory()->for(Product::factory()->for($this->organization))->create(['is_active' => false]);
        $variant = $this->variant();

        $this->actingAs($this->manager)
            ->post(route('purchasing.orders.store'), [
                'supplier_id' => $inactiveSupplier->id,
                'warehouse_id' => $foreignWarehouse->id,
                'expected_at' => '01/12/2026',
                'lines' => [
                    ['product_variant_id' => $inactiveVariant->id, 'quantity' => 1],
                    ['product_variant_id' => $variant->id, 'quantity' => 0, 'unit_cost' => '10.555'],
                    ['product_variant_id' => $variant->id, 'quantity' => 1, 'unit_cost' => '100000000'],
                ],
            ])
            ->assertSessionHasErrors([
                'supplier_id',
                'warehouse_id',
                'expected_at',
                'lines.0.product_variant_id',
                'lines.1.quantity',
                'lines.1.unit_cost',
                'lines.2.product_variant_id',
                'lines.2.unit_cost',
            ]);

        $this->actingAs($this->manager)
            ->post(route('purchasing.orders.store'), $this->payload([]))
            ->assertSessionHasErrors('lines');

        $this->assertDatabaseCount('purchase_orders', 0);
    }

    public function test_only_managers_place_and_cancel_orders(): void
    {
        $order = $this->draftWithLine();

        $this->actingAs($this->member)->post(route('purchasing.orders.place', $order))->assertForbidden();
        $this->actingAs($this->member)->post(route('purchasing.orders.cancel', $order))->assertForbidden();

        $this->actingAs($this->manager)->post(route('purchasing.orders.place', $order))->assertRedirect();

        $order->refresh();
        $this->assertSame('ordered', $order->status);
        $this->assertNotNull($order->ordered_at);

        $this->actingAs($this->manager)->post(route('purchasing.orders.cancel', $order))->assertRedirect();

        $order->refresh();
        $this->assertSame('cancelled', $order->status);
        $this->assertNotNull($order->cancelled_at);
    }

    public function test_placed_orders_can_no_longer_be_edited_deleted_or_placed_again(): void
    {
        $order = $this->draftWithLine();
        $order->update(['status' => 'ordered', 'ordered_at' => now()]);
        $line = $order->lines()->sole();

        $this->actingAs($this->manager)
            ->get(route('purchasing.orders.edit', $order))
            ->assertRedirect(route('purchasing.orders.show', $order));

        $this->actingAs($this->manager)
            ->put(route('purchasing.orders.update', $order), $this->payload([['product_variant_id' => $line->product_variant_id, 'quantity' => 999]]))
            ->assertRedirect(route('purchasing.orders.show', $order));

        $this->actingAs($this->manager)->delete(route('purchasing.orders.destroy', $order));
        $this->actingAs($this->manager)->post(route('purchasing.orders.place', $order));

        $this->assertModelExists($order);
        $this->assertSame($line->quantity_ordered, $line->fresh()->quantity_ordered);
        $this->assertSame('ordered', $order->fresh()->status);
    }

    public function test_orders_that_received_stock_cannot_be_cancelled(): void
    {
        $order = $this->draftWithLine();
        $order->update(['status' => 'partially_received']);

        $this->actingAs($this->manager)->post(route('purchasing.orders.cancel', $order))->assertRedirect();

        $this->assertSame('partially_received', $order->fresh()->status);
    }

    public function test_drafts_can_be_deleted_by_members(): void
    {
        $order = $this->draftWithLine();

        $this->actingAs($this->member)
            ->delete(route('purchasing.orders.destroy', $order))
            ->assertRedirect(route('purchasing.orders.index'));

        $this->assertModelMissing($order);
        $this->assertDatabaseCount('purchase_order_lines', 0);
    }

    public function test_index_filters_orders_and_shows_totals_to_managers(): void
    {
        $draft = $this->draftWithLine(['quantity_ordered' => 4, 'unit_cost' => '25.25']);
        $ordered = $this->draftWithLine();
        $ordered->update(['status' => 'ordered']);
        PurchaseOrder::factory()->create();

        $this->actingAs($this->manager)
            ->get(route('purchasing.orders.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('purchasing/orders/index')
                ->has('orders.data', 2)
                ->where('orders.data.0.number', $ordered->number)
                ->where('orders.data.1.total_cost', '101.00')
                ->where('orders.data.1.has_uncosted_lines', false));

        $this->actingAs($this->manager)
            ->get(route('purchasing.orders.index', ['status' => 'draft', 'search' => $draft->number]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('orders.data', 1)
                ->where('orders.data.0.id', $draft->id)
                ->where('filters.status', 'draft'));

        $this->actingAs($this->manager)
            ->get(route('purchasing.orders.index', ['status' => 'bogus']))
            ->assertInertia(fn (Assert $page) => $page->has('orders.data', 2)->where('filters.status', null));
    }

    public function test_orders_of_another_organization_are_not_found(): void
    {
        $foreign = PurchaseOrder::factory()->create();

        $this->actingAs($this->manager)->get(route('purchasing.orders.show', $foreign))->assertNotFound();
        $this->actingAs($this->manager)->get(route('purchasing.orders.edit', $foreign))->assertNotFound();
        $this->actingAs($this->manager)->delete(route('purchasing.orders.destroy', $foreign))->assertNotFound();
        $this->actingAs($this->manager)->post(route('purchasing.orders.place', $foreign))->assertNotFound();

        $this->assertSame('draft', $foreign->fresh()->status);
    }

    public function test_suppliers_warehouses_products_and_variants_on_orders_cannot_be_deleted(): void
    {
        $order = $this->draftWithLine();
        $variant = $order->lines()->sole()->productVariant;

        $this->actingAs($this->manager)->delete(route('purchasing.suppliers.destroy', $this->supplier));
        $this->actingAs($this->manager)->delete(route('warehouses.destroy', $this->warehouse));
        $this->actingAs($this->manager)->delete(route('catalog.variants.destroy', $variant));
        $this->actingAs($this->manager)->delete(route('catalog.products.destroy', $variant->product));

        $this->assertModelExists($this->supplier);
        $this->assertModelExists($this->warehouse);
        $this->assertModelExists($variant);
        $this->assertModelExists($variant->product);
    }

    private function variant(): ProductVariant
    {
        return ProductVariant::factory()->for(Product::factory()->for($this->organization))->create();
    }

    /**
     * @param  array<string, mixed>  $lineAttributes
     */
    private function draftWithLine(array $lineAttributes = []): PurchaseOrder
    {
        $order = PurchaseOrder::factory()->for($this->organization)->create([
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
        ]);

        PurchaseOrderLine::factory()->for($order)->create($lineAttributes);

        return $order;
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $lines, array $overrides = []): array
    {
        return [
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'notes' => null,
            'lines' => $lines,
            ...$overrides,
        ];
    }
}
