<?php

namespace Tests\Feature\Purchasing;

use App\Models\Organization;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\SerialNumber;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PurchaseOrderReceiptTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private User $manager;

    private User $member;

    private Warehouse $warehouse;

    private PurchaseOrder $order;

    private PurchaseOrderLine $line;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();
        $this->manager = User::factory()->withOrganization($this->organization, 'manager')->create();
        $this->member = User::factory()->withOrganization($this->organization, 'member')->create();
        $this->warehouse = Warehouse::factory()->for($this->organization)->create();
        $this->order = PurchaseOrder::factory()->for($this->organization)->ordered()->create(['warehouse_id' => $this->warehouse->id]);
        $this->line = PurchaseOrderLine::factory()->for($this->order)->create(['quantity_ordered' => 10, 'unit_cost' => '250.00']);
    }

    public function test_the_receive_form_lists_the_lines_still_expected(): void
    {
        PurchaseOrderLine::factory()->for($this->order)->create(['quantity_ordered' => 4, 'quantity_received' => 4]);

        $this->actingAs($this->member)
            ->get(route('purchasing.orders.receive.create', $this->order))
            ->assertInertia(fn (Assert $page) => $page
                ->component('purchasing/orders/receive')
                ->where('order.warehouse.id', $this->warehouse->id)
                ->has('order.lines', 1)
                ->where('order.lines.0.id', $this->line->id)
                ->where('order.lines.0.quantity_remaining', 10)
                ->missing('order.lines.0.unit_cost'));
    }

    public function test_members_receive_a_line_split_across_warehouses_with_serials(): void
    {
        $annex = Warehouse::factory()->for($this->organization)->create();

        $this->actingAs($this->member)
            ->post(route('purchasing.orders.receive.store', $this->order), $this->payload([
                ['purchase_order_line_id' => $this->line->id, 'allocations' => [
                    ['warehouse_id' => $this->warehouse->id, 'quantity' => 4, 'serials' => ''],
                    ['warehouse_id' => $annex->id, 'quantity' => 2, 'serials' => "SN-1\nSN-2\n"],
                    ['warehouse_id' => '', 'quantity' => '', 'serials' => ''],
                ]],
            ]))
            ->assertRedirect(route('purchasing.orders.show', $this->order))
            ->assertSessionHasNoErrors();

        $this->assertSame(6, $this->line->fresh()->quantity_received);
        $this->assertSame('partially_received', $this->order->fresh()->status);
        $this->assertSame(4, $this->balance($this->warehouse));
        $this->assertSame(2, $this->balance($annex));

        $movements = StockMovement::orderBy('quantity')->get();
        $this->assertCount(2, $movements);
        $this->assertSame([$this->line->id, $this->line->id], $movements->pluck('purchase_order_line_id')->all());
        $this->assertSame(['250.00', '250.00'], $movements->pluck('unit_cost')->all());
        $this->assertSame('receive', $movements[0]->movement_type);
        $this->assertEqualsCanonicalizing(['SN-1', 'SN-2'], $movements[0]->serialNumbers()->pluck('serial_number')->all());
        $this->assertSame(2, SerialNumber::where('current_warehouse_id', $annex->id)->where('status', 'in_stock')->count());

        $this->actingAs($this->member)
            ->post(route('purchasing.orders.receive.store', $this->order), $this->payload([
                ['purchase_order_line_id' => $this->line->id, 'allocations' => [['warehouse_id' => $this->warehouse->id, 'quantity' => 4]]],
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame('received', $this->order->fresh()->status);
        $this->assertFalse($this->order->fresh()->canReceive());
    }

    public function test_over_receiving_is_blocked(): void
    {
        $this->line->update(['quantity_received' => 7]);

        $this->actingAs($this->member)
            ->post(route('purchasing.orders.receive.store', $this->order), $this->payload([
                ['purchase_order_line_id' => $this->line->id, 'allocations' => [
                    ['warehouse_id' => $this->warehouse->id, 'quantity' => 2],
                    ['warehouse_id' => Warehouse::factory()->for($this->organization)->create()->id, 'quantity' => 2],
                ]],
            ]))
            ->assertSessionHasErrors(['lines.0.allocations' => 'Only 3 units are still expected on this line.']);

        $this->assertSame(0, StockMovement::count());
        $this->assertSame(7, $this->line->fresh()->quantity_received);
    }

    public function test_splits_need_an_active_distinct_warehouse_valid_serials_and_some_quantity(): void
    {
        $inactive = Warehouse::factory()->for($this->organization)->create(['is_active' => false]);
        SerialNumber::factory()->create(['organization_id' => $this->organization->id, 'serial_number' => 'TAKEN']);

        $this->actingAs($this->member)
            ->post(route('purchasing.orders.receive.store', $this->order), $this->payload([
                ['purchase_order_line_id' => $this->line->id, 'allocations' => [['warehouse_id' => $inactive->id, 'quantity' => 1]]],
            ]))
            ->assertSessionHasErrors(['lines.0.allocations.0.warehouse_id' => 'Choose an active warehouse.']);

        $this->actingAs($this->member)
            ->post(route('purchasing.orders.receive.store', $this->order), $this->payload([
                ['purchase_order_line_id' => $this->line->id, 'allocations' => [
                    ['warehouse_id' => '', 'quantity' => 1],
                    ['warehouse_id' => $this->warehouse->id, 'quantity' => 1, 'serials' => 'TAKEN'],
                    ['warehouse_id' => $this->warehouse->id, 'quantity' => 2, 'serials' => 'ONLY-ONE'],
                ]],
            ]))
            ->assertSessionHasErrors([
                'lines.0.allocations.0.warehouse_id' => 'Choose a warehouse.',
                'lines.0.allocations.1.serials' => 'Already exist: TAKEN',
                'lines.0.allocations.2.warehouse_id',
                'lines.0.allocations.2.serials',
            ]);

        $this->actingAs($this->member)
            ->post(route('purchasing.orders.receive.store', $this->order), $this->payload([
                ['purchase_order_line_id' => $this->line->id, 'allocations' => [['warehouse_id' => $this->warehouse->id, 'quantity' => 0]]],
            ]))
            ->assertSessionHasErrors(['lines' => 'Enter a quantity for at least one line.']);

        $this->assertSame(0, StockMovement::count());
    }

    public function test_lines_of_another_order_are_rejected(): void
    {
        $otherLine = PurchaseOrderLine::factory()->for(PurchaseOrder::factory()->for($this->organization)->ordered())->create();

        $this->actingAs($this->member)
            ->post(route('purchasing.orders.receive.store', $this->order), $this->payload([
                ['purchase_order_line_id' => $otherLine->id, 'allocations' => [['warehouse_id' => $this->warehouse->id, 'quantity' => 1]]],
            ]))
            ->assertSessionHasErrors(['lines.0.purchase_order_line_id' => 'This line is not on the order.']);
    }

    public function test_only_placed_orders_that_still_expect_stock_can_be_received(): void
    {
        foreach (['draft', 'received', 'closed', 'cancelled'] as $status) {
            $this->order->update(['status' => $status]);

            $this->actingAs($this->member)
                ->get(route('purchasing.orders.receive.create', $this->order))
                ->assertRedirect(route('purchasing.orders.show', $this->order));

            $this->actingAs($this->member)
                ->post(route('purchasing.orders.receive.store', $this->order), $this->payload([
                    ['purchase_order_line_id' => $this->line->id, 'allocations' => [['warehouse_id' => $this->warehouse->id, 'quantity' => 1]]],
                ]))
                ->assertSessionHasErrors('lines');
        }

        $this->assertSame(0, StockMovement::count());
    }

    public function test_orders_of_another_organization_are_not_found(): void
    {
        $foreign = PurchaseOrder::factory()->ordered()->create();

        $this->actingAs($this->member)->get(route('purchasing.orders.receive.create', $foreign))->assertNotFound();
        $this->actingAs($this->member)->post(route('purchasing.orders.receive.store', $foreign), $this->payload([]))->assertNotFound();
    }

    public function test_managers_close_partly_received_orders(): void
    {
        $this->actingAs($this->manager)->post(route('purchasing.orders.close', $this->order))->assertRedirect();
        $this->assertSame('ordered', $this->order->fresh()->status);

        $this->order->update(['status' => 'partially_received']);

        $this->actingAs($this->member)->post(route('purchasing.orders.close', $this->order))->assertForbidden();

        $this->actingAs($this->manager)
            ->get(route('purchasing.orders.show', $this->order))
            ->assertInertia(fn (Assert $page) => $page->where('can.receive', true)->where('can.close', true));

        $this->actingAs($this->manager)->post(route('purchasing.orders.close', $this->order))->assertSessionHasNoErrors();

        $this->assertSame('closed', $this->order->fresh()->status);
        $this->assertNotNull($this->order->fresh()->closed_at);
    }

    public function test_receipts_show_on_the_order_and_in_the_movements_list(): void
    {
        $this->actingAs($this->member)
            ->post(route('purchasing.orders.receive.store', $this->order), $this->payload([
                ['purchase_order_line_id' => $this->line->id, 'allocations' => [['warehouse_id' => $this->warehouse->id, 'quantity' => 3]]],
            ], ['notes' => 'Invoice 42']))
            ->assertSessionHasNoErrors();

        $this->actingAs($this->member)
            ->get(route('purchasing.orders.show', $this->order))
            ->assertInertia(fn (Assert $page) => $page
                ->has('receipts', 1)
                ->where('receipts.0.quantity', 3)
                ->where('receipts.0.warehouse.code', $this->warehouse->code)
                ->where('receipts.0.notes', 'Invoice 42'));

        $this->actingAs($this->member)
            ->get(route('inventory.movements.index', ['search' => $this->order->number]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('movements.data', 1)
                ->where('movements.data.0.purchase_order', ['id' => $this->order->id, 'number' => $this->order->number])
                ->missing('movements.data.0.unit_cost'));

        $this->actingAs($this->manager)
            ->get(route('inventory.movements.index'))
            ->assertInertia(fn (Assert $page) => $page->where('movements.data.0.unit_cost', '250.00'));

        $this->assertStringNotContainsString('Unit cost', $this->actingAs($this->member)->get(route('inventory.movements.export'))->streamedContent());
        $this->assertStringContainsString('250.00', $this->actingAs($this->manager)->get(route('inventory.movements.export'))->streamedContent());
    }

    private function balance(Warehouse $warehouse): int
    {
        return (int) StockBalance::where('warehouse_id', $warehouse->id)
            ->where('product_variant_id', $this->line->product_variant_id)
            ->value('quantity');
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $lines, array $overrides = []): array
    {
        return [
            'received_at' => now()->subHour()->toIso8601String(),
            'notes' => null,
            'lines' => $lines,
            ...$overrides,
        ];
    }
}
