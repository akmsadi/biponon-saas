<?php

namespace Tests\Feature\Sales;

use App\Models\Customer;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Models\StockBalance;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SalesOrderTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private User $manager;

    private User $member;

    private Customer $customer;

    private Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();
        $this->manager = User::factory()->withOrganization($this->organization, 'manager')->create();
        $this->member = User::factory()->withOrganization($this->organization, 'member')->create();
        $this->customer = Customer::factory()->for($this->organization)->create();
        $this->warehouse = Warehouse::factory()->for($this->organization)->create();
    }

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get(route('sales.orders.index'))->assertRedirect(route('login'));
    }

    public function test_members_create_numbered_drafts_with_prices_everyone_sees(): void
    {
        [$shirt, $pants] = [$this->variant(), $this->variant()];

        $this->actingAs($this->member)
            ->post(route('sales.orders.store'), $this->payload([
                ['product_variant_id' => $shirt->id, 'quantity' => 3, 'unit_price' => '1250.50'],
                ['product_variant_id' => $pants->id, 'quantity' => 2, 'unit_price' => '0'],
            ], ['deliver_by' => '2026-12-01', 'notes' => 'Gift wrap']))
            ->assertSessionHasNoErrors();

        $order = SalesOrder::sole();
        $this->assertSame('SO-0001', $order->number);
        $this->assertSame('draft', $order->status);
        $this->assertSame($this->member->id, $order->created_by);
        $this->assertSame('2026-12-01', $order->deliver_by->toDateString());

        $this->actingAs($this->member)
            ->get(route('sales.orders.show', $order))
            ->assertInertia(fn (Assert $page) => $page
                ->component('sales/orders/show')
                ->where('order.total_price', '3751.50')
                ->where('order.units_ordered', 5)
                ->where('order.lines.0.unit_price', '1250.50')
                ->where('order.lines.0.total_price', '3751.50')
                ->where('can', ['edit' => true, 'delete' => true, 'confirm' => false, 'cancel' => false, 'close' => false]));

        $this->actingAs($this->member)
            ->get(route('sales.orders.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('sales/orders/index')
                ->where('orders.data.0.total_price', '3751.50'));
    }

    public function test_numbers_continue_per_organization(): void
    {
        SalesOrder::factory()->for($this->organization)->create();
        SalesOrder::factory()->create();

        $this->actingAs($this->member)
            ->post(route('sales.orders.store'), $this->payload([['product_variant_id' => $this->variant()->id, 'quantity' => 1, 'unit_price' => '10']]))
            ->assertSessionHasNoErrors();

        $this->assertSame(['SO-0001', 'SO-0002'], $this->organization->salesOrders()->orderBy('sequence')->pluck('number')->all());
    }

    public function test_the_form_offers_list_prices_and_stock_per_warehouse(): void
    {
        $variant = $this->variant(['price' => '899.00']);
        StockBalance::factory()->create(['organization_id' => $this->organization->id, 'product_variant_id' => $variant->id, 'warehouse_id' => $this->warehouse->id, 'quantity' => 6]);

        $this->actingAs($this->member)
            ->get(route('sales.orders.create'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('sales/orders/form')
                ->where('products.0.variants.0.price', '899.00')
                ->where('products.0.variants.0.stock.'.$this->warehouse->id, 6));
    }

    public function test_drafts_need_an_active_customer_warehouse_variants_and_valid_prices(): void
    {
        $inactiveCustomer = Customer::factory()->for($this->organization)->inactive()->create();
        $foreignWarehouse = Warehouse::factory()->create();
        $inactiveVariant = $this->variant(['is_active' => false]);
        $variant = $this->variant();

        $this->actingAs($this->member)
            ->post(route('sales.orders.store'), [
                'customer_id' => $inactiveCustomer->id,
                'warehouse_id' => $foreignWarehouse->id,
                'deliver_by' => '01/12/2026',
                'lines' => [
                    ['product_variant_id' => $inactiveVariant->id, 'quantity' => 1, 'unit_price' => '10'],
                    ['product_variant_id' => $variant->id, 'quantity' => 0, 'unit_price' => '10.555'],
                    ['product_variant_id' => $variant->id, 'quantity' => 1, 'unit_price' => ''],
                ],
            ])
            ->assertSessionHasErrors([
                'customer_id',
                'warehouse_id',
                'deliver_by',
                'lines.0.product_variant_id',
                'lines.1.quantity',
                'lines.1.unit_price',
                'lines.2.product_variant_id',
                'lines.2.unit_price' => 'Enter a unit price.',
            ]);

        $this->assertDatabaseCount('sales_orders', 0);
    }

    public function test_managers_confirm_short_orders_and_see_the_shortage(): void
    {
        $order = $this->draftWithLine(['quantity_ordered' => 10]);
        $line = $order->lines()->sole();
        $elsewhere = Warehouse::factory()->for($this->organization)->create();
        StockBalance::factory()->create(['organization_id' => $this->organization->id, 'product_variant_id' => $line->product_variant_id, 'warehouse_id' => $this->warehouse->id, 'quantity' => 4]);
        StockBalance::factory()->create(['organization_id' => $this->organization->id, 'product_variant_id' => $line->product_variant_id, 'warehouse_id' => $elsewhere->id, 'quantity' => 3]);

        $this->actingAs($this->manager)
            ->get(route('sales.orders.show', $order))
            ->assertInertia(fn (Assert $page) => $page
                ->where('order.lines.0.in_stock_here', 4)
                ->where('order.lines.0.in_stock_total', 7)
                ->where('can.confirm', true));

        $this->actingAs($this->member)->post(route('sales.orders.confirm', $order))->assertForbidden();
        $this->actingAs($this->manager)->post(route('sales.orders.confirm', $order))->assertRedirect();

        $order->refresh();
        $this->assertSame('confirmed', $order->status);
        $this->assertNotNull($order->confirmed_at);
    }

    public function test_only_managers_cancel_and_only_untouched_orders_can_be_cancelled(): void
    {
        $order = $this->draftWithLine();
        $order->update(['status' => 'confirmed']);

        $this->actingAs($this->member)->post(route('sales.orders.cancel', $order))->assertForbidden();
        $this->actingAs($this->manager)->post(route('sales.orders.cancel', $order))->assertRedirect();
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertNotNull($order->fresh()->cancelled_at);

        $order->update(['status' => 'partially_dispatched']);
        $this->actingAs($this->manager)->post(route('sales.orders.cancel', $order));
        $this->assertSame('partially_dispatched', $order->fresh()->status);
    }

    public function test_managers_close_partly_dispatched_orders(): void
    {
        $order = $this->draftWithLine();
        $order->update(['status' => 'confirmed']);

        $this->actingAs($this->manager)->post(route('sales.orders.close', $order));
        $this->assertSame('confirmed', $order->fresh()->status);

        $order->update(['status' => 'partially_dispatched']);

        $this->actingAs($this->member)->post(route('sales.orders.close', $order))->assertForbidden();
        $this->actingAs($this->manager)->post(route('sales.orders.close', $order))->assertRedirect();

        $this->assertSame('closed', $order->fresh()->status);
        $this->assertNotNull($order->fresh()->closed_at);
    }

    public function test_confirmed_orders_can_no_longer_be_edited_deleted_or_confirmed_again(): void
    {
        $order = $this->draftWithLine();
        $order->update(['status' => 'confirmed', 'confirmed_at' => now()]);
        $line = $order->lines()->sole();

        $this->actingAs($this->manager)
            ->get(route('sales.orders.edit', $order))
            ->assertRedirect(route('sales.orders.show', $order));

        $this->actingAs($this->manager)
            ->put(route('sales.orders.update', $order), $this->payload([['product_variant_id' => $line->product_variant_id, 'quantity' => 999, 'unit_price' => '1']]))
            ->assertRedirect(route('sales.orders.show', $order));

        $this->actingAs($this->manager)->delete(route('sales.orders.destroy', $order));

        $this->assertModelExists($order);
        $this->assertSame($line->quantity_ordered, $line->fresh()->quantity_ordered);
    }

    public function test_members_edit_and_delete_drafts(): void
    {
        $order = $this->draftWithLine();
        $removed = $order->lines()->sole();
        $added = $this->variant();

        $this->actingAs($this->member)
            ->put(route('sales.orders.update', $order), $this->payload([['product_variant_id' => $added->id, 'quantity' => 2, 'unit_price' => '75.00']]))
            ->assertRedirect(route('sales.orders.show', $order))
            ->assertSessionHasNoErrors();

        $line = $order->lines()->sole();
        $this->assertSame($added->id, $line->product_variant_id);
        $this->assertSame('75.00', $line->unit_price);
        $this->assertModelMissing($removed);

        $this->actingAs($this->member)
            ->delete(route('sales.orders.destroy', $order))
            ->assertRedirect(route('sales.orders.index'));

        $this->assertModelMissing($order);
        $this->assertDatabaseCount('sales_order_lines', 0);
    }

    public function test_index_filters_orders(): void
    {
        $draft = $this->draftWithLine();
        $confirmed = $this->draftWithLine();
        $confirmed->update(['status' => 'confirmed']);
        SalesOrder::factory()->create();

        $this->actingAs($this->member)
            ->get(route('sales.orders.index'))
            ->assertInertia(fn (Assert $page) => $page->has('orders.data', 2)->where('orders.data.0.number', $confirmed->number));

        $this->actingAs($this->member)
            ->get(route('sales.orders.index', ['status' => 'draft', 'search' => $this->customer->name]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('orders.data', 1)
                ->where('orders.data.0.id', $draft->id)
                ->where('filters.status', 'draft'));
    }

    public function test_orders_of_another_organization_are_not_found(): void
    {
        $foreign = SalesOrder::factory()->create();

        $this->actingAs($this->manager)->get(route('sales.orders.show', $foreign))->assertNotFound();
        $this->actingAs($this->manager)->get(route('sales.orders.edit', $foreign))->assertNotFound();
        $this->actingAs($this->manager)->delete(route('sales.orders.destroy', $foreign))->assertNotFound();
        $this->actingAs($this->manager)->post(route('sales.orders.confirm', $foreign))->assertNotFound();

        $this->assertSame('draft', $foreign->fresh()->status);
    }

    public function test_customers_warehouses_products_and_variants_on_orders_cannot_be_deleted(): void
    {
        $order = $this->draftWithLine();
        $variant = $order->lines()->sole()->productVariant;

        $this->actingAs($this->manager)->delete(route('sales.customers.destroy', $this->customer));
        $this->actingAs($this->manager)->delete(route('warehouses.destroy', $this->warehouse));
        $this->actingAs($this->manager)->delete(route('catalog.variants.destroy', $variant));
        $this->actingAs($this->manager)->delete(route('catalog.products.destroy', $variant->product));

        $this->assertModelExists($this->customer);
        $this->assertModelExists($this->warehouse);
        $this->assertModelExists($variant);
        $this->assertModelExists($variant->product);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function variant(array $attributes = []): ProductVariant
    {
        return ProductVariant::factory()->for(Product::factory()->for($this->organization))->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $lineAttributes
     */
    private function draftWithLine(array $lineAttributes = []): SalesOrder
    {
        $order = SalesOrder::factory()->for($this->organization)->create([
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
        ]);

        SalesOrderLine::factory()->for($order)->create($lineAttributes);

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
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'notes' => null,
            'lines' => $lines,
            ...$overrides,
        ];
    }
}
