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

class SalesOrderReturnTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private User $member;

    private Warehouse $warehouse;

    private SalesOrder $order;

    private SalesOrderLine $line;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();
        $this->member = User::factory()->withOrganization($this->organization, 'member')->create();
        $this->warehouse = Warehouse::factory()->for($this->organization)->create();
        $this->order = SalesOrder::factory()->for($this->organization)->create(['warehouse_id' => $this->warehouse->id, 'status' => 'partially_dispatched']);
        $this->line = SalesOrderLine::factory()->for($this->order)->create(['quantity_ordered' => 10, 'quantity_dispatched' => 5]);
    }

    public function test_the_return_form_lists_lines_with_units_out_with_the_customer(): void
    {
        SalesOrderLine::factory()->for($this->order)->create(['quantity_ordered' => 3, 'quantity_dispatched' => 0]);

        $this->actingAs($this->member)
            ->get(route('sales.orders.return.create', $this->order))
            ->assertInertia(fn (Assert $page) => $page
                ->component('sales/orders/return')
                ->has('order.lines', 1)
                ->where('order.lines.0.quantity_returnable', 5));
    }

    public function test_members_take_back_units_into_a_warehouse_with_serials_dispatched_on_the_line(): void
    {
        $annex = Warehouse::factory()->for($this->organization)->create();
        $dispatch = StockMovement::factory()->create([
            'organization_id' => $this->organization->id,
            'product_variant_id' => $this->line->product_variant_id,
            'from_warehouse_id' => $this->warehouse->id,
            'to_warehouse_id' => null,
            'movement_type' => 'dispatch',
            'quantity' => 5,
            'sales_order_line_id' => $this->line->id,
            'occurred_at' => now()->subDay(),
        ]);
        $serial = $this->dispatchedSerial('SN-1');
        $dispatch->serialNumbers()->attach($serial);

        $this->actingAs($this->member)
            ->post(route('sales.orders.return.store', $this->order), $this->payload($annex, [
                ['sales_order_line_id' => $this->line->id, 'quantity' => 2, 'serials' => "SN-1\n"],
            ]))
            ->assertSessionHasErrors(['lines.0.serials']);

        $this->actingAs($this->member)
            ->post(route('sales.orders.return.store', $this->order), $this->payload($annex, [
                ['sales_order_line_id' => $this->line->id, 'quantity' => 1, 'serials' => 'SN-1'],
            ], ['notes' => 'Wrong size']))
            ->assertRedirect(route('sales.orders.show', $this->order))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $this->line->fresh()->quantity_returned);
        $this->assertSame(5, $this->line->fresh()->quantity_dispatched);
        $this->assertSame('partially_dispatched', $this->order->fresh()->status);
        $this->assertSame(1, (int) StockBalance::where('warehouse_id', $annex->id)->value('quantity'));
        $this->assertSame(['in_stock', $annex->id], [$serial->fresh()->status, $serial->fresh()->current_warehouse_id]);

        $this->actingAs($this->member)
            ->get(route('sales.orders.show', $this->order))
            ->assertInertia(fn (Assert $page) => $page
                ->where('order.lines.0.quantity_returned', 1)
                ->where('history.0.type', 'return')
                ->where('history.0.warehouse.code', $annex->code)
                ->where('history.0.notes', 'Wrong size')
                ->where('can.return', true));
    }

    public function test_serials_not_dispatched_on_this_order_are_rejected(): void
    {
        $this->dispatchedSerial('ELSEWHERE');

        $this->actingAs($this->member)
            ->post(route('sales.orders.return.store', $this->order), $this->payload($this->warehouse, [
                ['sales_order_line_id' => $this->line->id, 'quantity' => 1, 'serials' => 'ELSEWHERE'],
            ]))
            ->assertSessionHasErrors(['lines.0.serials' => 'Not dispatched on this order: ELSEWHERE']);
    }

    public function test_returns_cannot_exceed_what_was_sent_and_not_yet_returned(): void
    {
        $this->line->update(['quantity_returned' => 3]);

        $this->actingAs($this->member)
            ->post(route('sales.orders.return.store', $this->order), $this->payload($this->warehouse, [
                ['sales_order_line_id' => $this->line->id, 'quantity' => 3],
            ]))
            ->assertSessionHasErrors(['lines.0.quantity' => 'Only 2 units were sent and not yet returned.']);

        $this->actingAs($this->member)
            ->post(route('sales.orders.return.store', $this->order), $this->payload($this->warehouse, [
                ['sales_order_line_id' => $this->line->id, 'quantity' => ''],
            ]))
            ->assertSessionHasErrors(['lines' => 'Enter a quantity for at least one line.']);

        $this->assertSame(0, StockMovement::count());
    }

    public function test_returns_need_an_active_warehouse_and_a_line_of_this_order(): void
    {
        $inactive = Warehouse::factory()->for($this->organization)->create(['is_active' => false]);
        $otherLine = SalesOrderLine::factory()->for(SalesOrder::factory()->for($this->organization))->create();

        $this->actingAs($this->member)
            ->post(route('sales.orders.return.store', $this->order), $this->payload($inactive, [
                ['sales_order_line_id' => $otherLine->id, 'quantity' => 1],
            ]))
            ->assertSessionHasErrors(['warehouse_id', 'lines.0.sales_order_line_id']);
    }

    public function test_orders_that_sent_nothing_take_no_returns(): void
    {
        foreach (['draft', 'confirmed', 'cancelled'] as $status) {
            $this->order->update(['status' => $status]);

            $this->actingAs($this->member)
                ->get(route('sales.orders.return.create', $this->order))
                ->assertRedirect(route('sales.orders.show', $this->order));

            $this->actingAs($this->member)
                ->post(route('sales.orders.return.store', $this->order), $this->payload($this->warehouse, [
                    ['sales_order_line_id' => $this->line->id, 'quantity' => 1],
                ]))
                ->assertSessionHasErrors('lines');
        }

        $this->assertSame(0, StockMovement::count());
    }

    public function test_orders_of_another_organization_are_not_found(): void
    {
        $foreign = SalesOrder::factory()->create(['status' => 'dispatched']);

        $this->actingAs($this->member)->get(route('sales.orders.return.create', $foreign))->assertNotFound();
        $this->actingAs($this->member)->post(route('sales.orders.return.store', $foreign), $this->payload($this->warehouse, []))->assertNotFound();
    }

    private function dispatchedSerial(string $number): SerialNumber
    {
        return SerialNumber::factory()->create([
            'organization_id' => $this->organization->id,
            'product_variant_id' => $this->line->product_variant_id,
            'current_warehouse_id' => null,
            'serial_number' => $number,
            'status' => 'dispatched',
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(Warehouse $warehouse, array $lines, array $overrides = []): array
    {
        return [
            'warehouse_id' => $warehouse->id,
            'returned_at' => now()->subHour()->toIso8601String(),
            'notes' => null,
            'lines' => $lines,
            ...$overrides,
        ];
    }
}
