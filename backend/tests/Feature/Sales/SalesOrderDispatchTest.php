<?php

namespace Tests\Feature\Sales;

use App\Models\Organization;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Models\SerialNumber;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SalesOrderDispatchTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private User $member;

    private Warehouse $warehouse;

    private Warehouse $annex;

    private SalesOrder $order;

    private SalesOrderLine $line;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();
        $this->member = User::factory()->withOrganization($this->organization, 'member')->create();
        $this->warehouse = Warehouse::factory()->for($this->organization)->create();
        $this->annex = Warehouse::factory()->for($this->organization)->create();
        $this->order = SalesOrder::factory()->for($this->organization)->confirmed()->create(['warehouse_id' => $this->warehouse->id]);
        $this->line = SalesOrderLine::factory()->for($this->order)->create(['quantity_ordered' => 10, 'unit_price' => '500.00']);

        $this->stock($this->warehouse, 6);
        $this->stock($this->annex, 8);
    }

    public function test_the_dispatch_form_lists_lines_to_send_with_their_stock(): void
    {
        SalesOrderLine::factory()->for($this->order)->create(['quantity_ordered' => 2, 'quantity_dispatched' => 2]);

        $this->actingAs($this->member)
            ->get(route('sales.orders.dispatch.create', $this->order))
            ->assertInertia(fn (Assert $page) => $page
                ->component('sales/orders/dispatch')
                ->has('order.lines', 1)
                ->where('order.lines.0.quantity_remaining', 10)
                ->where('order.lines.0.stock.'.$this->warehouse->id, 6)
                ->where('order.lines.0.stock.'.$this->annex->id, 8));
    }

    public function test_members_dispatch_a_line_split_across_warehouses_with_serials(): void
    {
        SerialNumber::factory()->count(2)->sequence(['serial_number' => 'SN-1'], ['serial_number' => 'SN-2'])->create([
            'organization_id' => $this->organization->id,
            'product_variant_id' => $this->line->product_variant_id,
            'current_warehouse_id' => $this->annex->id,
            'status' => 'in_stock',
        ]);

        $this->actingAs($this->member)
            ->post(route('sales.orders.dispatch.store', $this->order), $this->payload([
                ['sales_order_line_id' => $this->line->id, 'allocations' => [
                    ['warehouse_id' => $this->warehouse->id, 'quantity' => 6, 'serials' => ''],
                    ['warehouse_id' => $this->annex->id, 'quantity' => 2, 'serials' => "SN-1\nSN-2"],
                    ['warehouse_id' => '', 'quantity' => '', 'serials' => ''],
                ]],
            ]))
            ->assertRedirect(route('sales.orders.show', $this->order))
            ->assertSessionHasNoErrors();

        $this->assertSame(8, $this->line->fresh()->quantity_dispatched);
        $this->assertSame('partially_dispatched', $this->order->fresh()->status);
        $this->assertSame(0, $this->balance($this->warehouse));
        $this->assertSame(6, $this->balance($this->annex));
        $this->assertSame(2, StockMovement::where('sales_order_line_id', $this->line->id)->where('movement_type', 'dispatch')->count());
        $this->assertSame(2, SerialNumber::where('status', 'dispatched')->whereNull('current_warehouse_id')->count());

        $this->actingAs($this->member)
            ->post(route('sales.orders.dispatch.store', $this->order), $this->payload([
                ['sales_order_line_id' => $this->line->id, 'allocations' => [['warehouse_id' => $this->annex->id, 'quantity' => 2]]],
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame('dispatched', $this->order->fresh()->status);
    }

    public function test_splits_cannot_take_more_than_the_warehouse_holds_or_the_line_still_needs(): void
    {
        $this->actingAs($this->member)
            ->post(route('sales.orders.dispatch.store', $this->order), $this->payload([
                ['sales_order_line_id' => $this->line->id, 'allocations' => [['warehouse_id' => $this->warehouse->id, 'quantity' => 7]]],
            ]))
            ->assertSessionHasErrors(['lines.0.allocations.0.quantity' => 'Only 6 available in '.$this->warehouse->code.'.']);

        $this->line->update(['quantity_dispatched' => 7]);

        $this->actingAs($this->member)
            ->post(route('sales.orders.dispatch.store', $this->order), $this->payload([
                ['sales_order_line_id' => $this->line->id, 'allocations' => [
                    ['warehouse_id' => $this->warehouse->id, 'quantity' => 2],
                    ['warehouse_id' => $this->annex->id, 'quantity' => 2],
                ]],
            ]))
            ->assertSessionHasErrors(['lines.0.allocations' => 'Only 3 units are still to send on this line.']);

        $this->assertSame(0, StockMovement::count());
    }

    public function test_splits_need_a_distinct_warehouse_serials_in_stock_there_and_some_quantity(): void
    {
        SerialNumber::factory()->create([
            'organization_id' => $this->organization->id,
            'product_variant_id' => $this->line->product_variant_id,
            'current_warehouse_id' => $this->annex->id,
            'serial_number' => 'ELSEWHERE',
            'status' => 'in_stock',
        ]);

        $this->actingAs($this->member)
            ->post(route('sales.orders.dispatch.store', $this->order), $this->payload([
                ['sales_order_line_id' => $this->line->id, 'allocations' => [
                    ['warehouse_id' => '', 'quantity' => 1],
                    ['warehouse_id' => $this->warehouse->id, 'quantity' => 1, 'serials' => 'ELSEWHERE'],
                    ['warehouse_id' => $this->warehouse->id, 'quantity' => 1],
                ]],
            ]))
            ->assertSessionHasErrors([
                'lines.0.allocations.0.warehouse_id' => 'Choose a warehouse.',
                'lines.0.allocations.1.serials' => 'Not in stock in '.$this->warehouse->code.' for this variant: ELSEWHERE',
                'lines.0.allocations.2.warehouse_id',
            ]);

        $this->actingAs($this->member)
            ->post(route('sales.orders.dispatch.store', $this->order), $this->payload([
                ['sales_order_line_id' => $this->line->id, 'allocations' => [['warehouse_id' => $this->warehouse->id, 'quantity' => 0]]],
            ]))
            ->assertSessionHasErrors(['lines' => 'Enter a quantity for at least one line.']);

        $this->assertSame(0, StockMovement::count());
    }

    public function test_lines_of_another_order_are_rejected(): void
    {
        $otherLine = SalesOrderLine::factory()->for(SalesOrder::factory()->for($this->organization)->confirmed())->create();

        $this->actingAs($this->member)
            ->post(route('sales.orders.dispatch.store', $this->order), $this->payload([
                ['sales_order_line_id' => $otherLine->id, 'allocations' => [['warehouse_id' => $this->warehouse->id, 'quantity' => 1]]],
            ]))
            ->assertSessionHasErrors(['lines.0.sales_order_line_id' => 'This line is not on the order.']);
    }

    public function test_only_confirmed_orders_that_still_need_stock_can_be_dispatched(): void
    {
        foreach (['draft', 'dispatched', 'closed', 'cancelled'] as $status) {
            $this->order->update(['status' => $status]);

            $this->actingAs($this->member)
                ->get(route('sales.orders.dispatch.create', $this->order))
                ->assertRedirect(route('sales.orders.show', $this->order));

            $this->actingAs($this->member)
                ->post(route('sales.orders.dispatch.store', $this->order), $this->payload([
                    ['sales_order_line_id' => $this->line->id, 'allocations' => [['warehouse_id' => $this->warehouse->id, 'quantity' => 1]]],
                ]))
                ->assertSessionHasErrors('lines');
        }

        $this->assertSame(0, StockMovement::count());
    }

    public function test_orders_of_another_organization_are_not_found(): void
    {
        $foreign = SalesOrder::factory()->confirmed()->create();

        $this->actingAs($this->member)->get(route('sales.orders.dispatch.create', $foreign))->assertNotFound();
        $this->actingAs($this->member)->post(route('sales.orders.dispatch.store', $foreign), $this->payload([]))->assertNotFound();
    }

    public function test_dispatches_show_on_the_order_and_in_the_movements_list(): void
    {
        $this->actingAs($this->member)
            ->post(route('sales.orders.dispatch.store', $this->order), $this->payload([
                ['sales_order_line_id' => $this->line->id, 'allocations' => [['warehouse_id' => $this->annex->id, 'quantity' => 3]]],
            ], ['notes' => 'Courier 77']))
            ->assertSessionHasNoErrors();

        $this->actingAs($this->member)
            ->get(route('sales.orders.show', $this->order))
            ->assertInertia(fn (Assert $page) => $page
                ->has('history', 1)
                ->where('history.0.type', 'dispatch')
                ->where('history.0.quantity', 3)
                ->where('history.0.warehouse.code', $this->annex->code)
                ->where('history.0.notes', 'Courier 77')
                ->where('can.dispatch', true));

        $this->actingAs($this->member)
            ->get(route('inventory.movements.index', ['search' => $this->order->number]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('movements.data', 1)
                ->where('movements.data.0.sales_order', ['id' => $this->order->id, 'number' => $this->order->number])
                ->where('movements.data.0.purchase_order', null));

        $this->assertStringContainsString($this->order->number, $this->actingAs($this->member)->get(route('inventory.movements.export'))->streamedContent());
    }

    private function stock(Warehouse $warehouse, int $quantity): void
    {
        StockBalance::factory()->create([
            'organization_id' => $this->organization->id,
            'product_variant_id' => $this->line->product_variant_id,
            'warehouse_id' => $warehouse->id,
            'quantity' => $quantity,
        ]);
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
            'dispatched_at' => now()->subHour()->toIso8601String(),
            'notes' => null,
            'lines' => $lines,
            ...$overrides,
        ];
    }
}
