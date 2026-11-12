<?php

namespace Tests\Feature\Operations;

use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SerialNumber;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReturnStockTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private User $user;

    private Warehouse $warehouse;

    private ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();
        $this->user = User::factory()->withOrganization($this->organization)->create();
        $this->warehouse = Warehouse::factory()->for($this->organization)->create(['code' => 'RET']);
        $this->variant = $this->variantInOrganization();
    }

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get(route('operations.return.create'))->assertRedirect(route('login'));
    }

    public function test_the_form_offers_active_warehouses_and_every_variant_including_inactive_ones(): void
    {
        Warehouse::factory()->inactive()->for($this->organization)->create();
        $this->variant->update(['is_active' => false]);
        ProductVariant::factory()->create();

        $this->actingAs($this->user)
            ->get(route('operations.return.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('operations/return')
                ->has('warehouses', 1)
                ->has('products', 1)
                ->where('products.0.variants.0.id', $this->variant->id));
    }

    public function test_returning_increases_the_balance_and_records_a_movement(): void
    {
        $this->returnStock([['product_variant_id' => $this->variant->id, 'quantity' => 3]], notes: 'Wrong size')
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('operations.return.create'));

        $this->assertDatabaseHas('stock_movements', [
            'organization_id' => $this->organization->id,
            'product_variant_id' => $this->variant->id,
            'from_warehouse_id' => null,
            'to_warehouse_id' => $this->warehouse->id,
            'quantity' => 3,
            'movement_type' => 'return',
            'performed_by' => $this->user->id,
            'notes' => 'Wrong size',
        ]);
        $this->assertSame(3, $this->balance());
    }

    public function test_named_dispatched_serials_come_back_in_stock_and_are_linked_to_the_movement(): void
    {
        $this->serial('SN-001', 'dispatched');

        $this->returnStock([['product_variant_id' => $this->variant->id, 'quantity' => 1, 'serials' => 'SN-001']])
            ->assertSessionHasNoErrors();

        $returned = SerialNumber::sole();

        $this->assertSame('in_stock', $returned->status);
        $this->assertSame($this->warehouse->id, $returned->current_warehouse_id);
        $this->assertTrue(StockMovement::sole()->serialNumbers->contains($returned));
    }

    public function test_serials_must_be_dispatched_units_of_the_variant(): void
    {
        $this->serial('SN-OK', 'dispatched');
        $this->serial('SN-HERE', 'in_stock');
        SerialNumber::factory()->for($this->variantInOrganization())->create([
            'organization_id' => $this->organization->id,
            'current_warehouse_id' => null,
            'status' => 'dispatched',
            'serial_number' => 'SN-OTHER',
        ]);

        $this->returnStock([['product_variant_id' => $this->variant->id, 'quantity' => 4, 'serials' => "SN-OK\nSN-HERE\nSN-OTHER\nSN-UNKNOWN"]])
            ->assertSessionHasErrors(['lines.0.serials' => 'Not dispatched units of this variant: SN-HERE, SN-OTHER, SN-UNKNOWN']);

        $this->assertNothingReturned();
    }

    public function test_the_serial_count_must_match_the_quantity(): void
    {
        $this->serial('SN-001', 'dispatched');

        $this->returnStock([['product_variant_id' => $this->variant->id, 'quantity' => 2, 'serials' => 'SN-001']])
            ->assertSessionHasErrors('lines.0.serials');

        $this->assertNothingReturned();
    }

    public function test_the_warehouse_must_be_an_active_one_of_the_organization(): void
    {
        $this->returnStock([['product_variant_id' => $this->variant->id, 'quantity' => 1]], warehouse: Warehouse::factory()->inactive()->for($this->organization)->create())
            ->assertSessionHasErrors('warehouse_id');

        $this->returnStock([['product_variant_id' => ProductVariant::factory()->create()->id, 'quantity' => 1]], warehouse: Warehouse::factory()->create())
            ->assertSessionHasErrors(['warehouse_id', 'lines.0.product_variant_id']);

        $this->assertNothingReturned();
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    private function returnStock(array $lines, ?Warehouse $warehouse = null, ?string $notes = null): TestResponse
    {
        return $this->actingAs($this->user)->post(route('operations.return.store'), [
            'warehouse_id' => ($warehouse ?? $this->warehouse)->id,
            'returned_at' => now()->subHour()->toIso8601String(),
            'notes' => $notes,
            'lines' => $lines,
        ]);
    }

    private function variantInOrganization(): ProductVariant
    {
        return ProductVariant::factory()
            ->for(Product::factory()->for($this->organization))
            ->create();
    }

    private function serial(string $serialNumber, string $status): SerialNumber
    {
        return SerialNumber::factory()->for($this->variant)->create([
            'organization_id' => $this->organization->id,
            'current_warehouse_id' => $status === 'in_stock' ? $this->warehouse->id : null,
            'serial_number' => $serialNumber,
            'status' => $status,
        ]);
    }

    private function balance(): int
    {
        return (int) StockBalance::where([
            'product_variant_id' => $this->variant->id,
            'warehouse_id' => $this->warehouse->id,
        ])->value('quantity');
    }

    private function assertNothingReturned(): void
    {
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertSame(0, $this->balance());
        $this->assertSame(0, SerialNumber::whereHas('stockMovements')->count());
    }
}
