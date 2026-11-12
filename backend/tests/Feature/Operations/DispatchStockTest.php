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

class DispatchStockTest extends TestCase
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
        $this->warehouse = Warehouse::factory()->for($this->organization)->create(['code' => 'SRC']);
        $this->variant = $this->variantInOrganization();
        $this->stock($this->variant, $this->warehouse, 10);
    }

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get(route('operations.dispatch.create'))->assertRedirect(route('login'));
    }

    public function test_the_form_offers_variants_in_stock_with_their_quantity_per_warehouse(): void
    {
        $this->variantInOrganization();
        $this->stock(ProductVariant::factory()->create(), Warehouse::factory()->create(), 5);

        $this->actingAs($this->user)
            ->get(route('operations.dispatch.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('operations/dispatch')
                ->has('warehouses', 1)
                ->has('products', 1)
                ->has('products.0.variants', 1)
                ->where('products.0.variants.0.stock', [$this->warehouse->id => 10]));
    }

    public function test_dispatching_reduces_the_balance_and_records_a_movement(): void
    {
        $this->dispatchStock([['product_variant_id' => $this->variant->id, 'quantity' => 4]], notes: 'Order #1001')
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('operations.dispatch.create'));

        $this->assertDatabaseHas('stock_movements', [
            'organization_id' => $this->organization->id,
            'product_variant_id' => $this->variant->id,
            'from_warehouse_id' => $this->warehouse->id,
            'to_warehouse_id' => null,
            'quantity' => 4,
            'movement_type' => 'dispatch',
            'performed_by' => $this->user->id,
            'notes' => 'Order #1001',
        ]);
        $this->assertSame(6, $this->balance());
    }

    public function test_the_whole_balance_can_leave_an_inactive_warehouse(): void
    {
        $this->warehouse->update(['is_active' => false]);

        $this->dispatchStock([['product_variant_id' => $this->variant->id, 'quantity' => 10]])
            ->assertSessionHasNoErrors();

        $this->assertSame(0, $this->balance());
    }

    public function test_named_serials_are_marked_dispatched_and_linked_to_the_movement(): void
    {
        $this->serial('SN-001');
        $staying = $this->serial('SN-002');

        $this->dispatchStock([['product_variant_id' => $this->variant->id, 'quantity' => 1, 'serials' => " SN-001 \n\n"]])
            ->assertSessionHasNoErrors();

        $dispatched = SerialNumber::firstWhere('serial_number', 'SN-001');

        $this->assertSame('dispatched', $dispatched->status);
        $this->assertNull($dispatched->current_warehouse_id);
        $this->assertTrue(StockMovement::sole()->serialNumbers->contains($dispatched));
        $this->assertSame('in_stock', $staying->fresh()->status);
    }

    public function test_serials_are_optional(): void
    {
        $this->serial('SN-001');

        $this->dispatchStock([['product_variant_id' => $this->variant->id, 'quantity' => 3]])
            ->assertSessionHasNoErrors();

        $this->assertSame(7, $this->balance());
        $this->assertSame('in_stock', SerialNumber::sole()->status);
    }

    public function test_the_quantity_cannot_exceed_what_the_warehouse_holds(): void
    {
        $this->dispatchStock([['product_variant_id' => $this->variant->id, 'quantity' => 11]])
            ->assertSessionHasErrors(['lines.0.quantity' => 'Only 10 available in SRC.']);

        $this->assertNothingDispatched();
    }

    public function test_the_serial_count_must_match_the_quantity(): void
    {
        $this->serial('SN-001');

        $this->dispatchStock([['product_variant_id' => $this->variant->id, 'quantity' => 2, 'serials' => 'SN-001']])
            ->assertSessionHasErrors('lines.0.serials');

        $this->assertNothingDispatched();
    }

    public function test_serials_must_be_units_of_the_variant_in_stock_at_the_warehouse(): void
    {
        $this->serial('SN-OK');
        $this->serial('SN-ELSEWHERE', ['current_warehouse_id' => Warehouse::factory()->for($this->organization)->create()->id]);
        $this->serial('SN-GONE', ['status' => 'dispatched', 'current_warehouse_id' => null]);
        SerialNumber::factory()->for($this->variantInOrganization())->create([
            'organization_id' => $this->organization->id,
            'current_warehouse_id' => $this->warehouse->id,
            'serial_number' => 'SN-OTHER',
        ]);

        $this->dispatchStock([['product_variant_id' => $this->variant->id, 'quantity' => 4, 'serials' => "SN-OK\nSN-ELSEWHERE\nSN-GONE\nSN-OTHER"]])
            ->assertSessionHasErrors(['lines.0.serials' => 'Not in stock in SRC for this variant: SN-ELSEWHERE, SN-GONE, SN-OTHER']);

        $this->assertNothingDispatched();
    }

    public function test_serials_cannot_repeat_within_the_dispatch(): void
    {
        $this->serial('SN-001');

        $this->dispatchStock([['product_variant_id' => $this->variant->id, 'quantity' => 2, 'serials' => "SN-001\nSN-001"]])
            ->assertSessionHasErrors(['lines.0.serials' => 'Repeated in this dispatch: SN-001']);

        $this->assertNothingDispatched();
    }

    public function test_warehouses_and_variants_of_another_organization_are_rejected(): void
    {
        $this->dispatchStock(
            [['product_variant_id' => ProductVariant::factory()->create()->id, 'quantity' => 1]],
            warehouse: Warehouse::factory()->create(),
        )->assertSessionHasErrors(['warehouse_id', 'lines.0.product_variant_id']);

        $this->assertNothingDispatched();
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    private function dispatchStock(array $lines, ?Warehouse $warehouse = null, ?string $notes = null): TestResponse
    {
        return $this->actingAs($this->user)->post(route('operations.dispatch.store'), [
            'warehouse_id' => ($warehouse ?? $this->warehouse)->id,
            'dispatched_at' => now()->subHour()->toIso8601String(),
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

    private function stock(ProductVariant $variant, Warehouse $warehouse, int $quantity): void
    {
        StockBalance::factory()->create([
            'organization_id' => $variant->organization_id,
            'product_variant_id' => $variant->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => $quantity,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function serial(string $serialNumber, array $attributes = []): SerialNumber
    {
        return SerialNumber::factory()->for($this->variant)->create([
            'organization_id' => $this->organization->id,
            'current_warehouse_id' => $this->warehouse->id,
            'serial_number' => $serialNumber,
            ...$attributes,
        ]);
    }

    private function balance(): int
    {
        return (int) StockBalance::where([
            'product_variant_id' => $this->variant->id,
            'warehouse_id' => $this->warehouse->id,
        ])->value('quantity');
    }

    private function assertNothingDispatched(): void
    {
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertSame(10, $this->balance());
        $this->assertSame(0, SerialNumber::whereHas('stockMovements')->count());
    }
}
