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

class ReceiveStockTest extends TestCase
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
        $this->warehouse = Warehouse::factory()->for($this->organization)->create(['code' => 'CWH']);
        $this->variant = $this->variantInOrganization();
    }

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get(route('operations.receive.create'))->assertRedirect(route('login'));
    }

    public function test_the_form_offers_only_active_warehouses_and_active_variants_of_active_products(): void
    {
        Warehouse::factory()->inactive()->for($this->organization)->create();
        ProductVariant::factory()->for($this->variant->product)->create(['is_active' => false]);
        $this->variantInOrganization(['is_active' => false]);
        Warehouse::factory()->create();

        $this->actingAs($this->user)
            ->get(route('operations.receive.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('operations/receive')
                ->has('warehouses', 1)
                ->where('warehouses.0.id', $this->warehouse->id)
                ->has('products', 1)
                ->has('products.0.variants', 1)
                ->where('products.0.variants.0.id', $this->variant->id));
    }

    public function test_receiving_a_quantity_records_a_movement_and_creates_the_balance(): void
    {
        $this->receive([['product_variant_id' => $this->variant->id, 'quantity' => 12]], notes: 'Invoice 42')
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('operations.receive.create'));

        $this->assertDatabaseHas('stock_movements', [
            'organization_id' => $this->organization->id,
            'product_variant_id' => $this->variant->id,
            'from_warehouse_id' => null,
            'to_warehouse_id' => $this->warehouse->id,
            'quantity' => 12,
            'movement_type' => 'receive',
            'performed_by' => $this->user->id,
            'notes' => 'Invoice 42',
        ]);
        $this->assertSame(12, $this->balance($this->variant));
        $this->assertDatabaseCount('serial_numbers', 0);
    }

    public function test_receiving_again_adds_to_the_existing_balance(): void
    {
        $this->receive([['product_variant_id' => $this->variant->id, 'quantity' => 5]]);
        $this->receive([['product_variant_id' => $this->variant->id, 'quantity' => 7]]);

        $this->assertSame(12, $this->balance($this->variant));
        $this->assertDatabaseCount('stock_balances', 1);
        $this->assertDatabaseCount('stock_movements', 2);
    }

    public function test_several_lines_are_received_in_one_receipt(): void
    {
        $otherVariant = $this->variantInOrganization();

        $this->receive([
            ['product_variant_id' => $this->variant->id, 'quantity' => 3],
            ['product_variant_id' => $otherVariant->id, 'quantity' => 4],
        ])->assertSessionHasNoErrors();

        $this->assertSame(3, $this->balance($this->variant));
        $this->assertSame(4, $this->balance($otherVariant));
        $this->assertDatabaseCount('stock_movements', 2);
    }

    public function test_serials_are_created_in_stock_and_linked_to_the_movement(): void
    {
        $this->receive([['product_variant_id' => $this->variant->id, 'quantity' => 2, 'serials' => " SN-001\r\n\r\nsn-002 \n"]])
            ->assertSessionHasNoErrors();

        $movement = StockMovement::sole();

        $this->assertSame(['SN-001', 'sn-002'], $movement->serialNumbers()->orderBy('serial_number')->pluck('serial_number')->all());
        $this->assertSame(2, SerialNumber::where([
            'product_variant_id' => $this->variant->id,
            'current_warehouse_id' => $this->warehouse->id,
            'status' => 'in_stock',
        ])->count());
        $this->assertSame(2, $this->balance($this->variant));
    }

    public function test_the_serial_count_must_match_the_quantity(): void
    {
        $this->receive([['product_variant_id' => $this->variant->id, 'quantity' => 3, 'serials' => "SN-001\nSN-002"]])
            ->assertSessionHasErrors('lines.0.serials');

        $this->assertNothingReceived();
    }

    public function test_serials_cannot_repeat_within_the_receipt(): void
    {
        $otherVariant = $this->variantInOrganization();

        $this->receive([
            ['product_variant_id' => $this->variant->id, 'quantity' => 1, 'serials' => 'SN-001'],
            ['product_variant_id' => $otherVariant->id, 'quantity' => 1, 'serials' => 'sn-001'],
        ])->assertSessionHasErrors('lines.1.serials');

        $this->assertNothingReceived();
    }

    public function test_serials_already_known_to_the_organization_are_rejected(): void
    {
        SerialNumber::factory()->for($this->variant)->create([
            'organization_id' => $this->organization->id,
            'current_warehouse_id' => $this->warehouse->id,
            'serial_number' => 'SN-001',
        ]);

        $this->receive([['product_variant_id' => $this->variant->id, 'quantity' => 2, 'serials' => "SN-001\nSN-002"]])
            ->assertSessionHasErrors(['lines.0.serials' => 'Already exist: SN-001']);

        $this->assertNothingReceived();
    }

    public function test_the_same_variant_cannot_appear_on_two_lines(): void
    {
        $this->receive([
            ['product_variant_id' => $this->variant->id, 'quantity' => 1],
            ['product_variant_id' => $this->variant->id, 'quantity' => 2],
        ])->assertSessionHasErrors('lines.1.product_variant_id');

        $this->assertNothingReceived();
    }

    public function test_inactive_warehouses_and_variants_are_rejected(): void
    {
        $inactiveWarehouse = Warehouse::factory()->inactive()->for($this->organization)->create();
        $inactiveVariant = ProductVariant::factory()->for($this->variant->product)->create(['is_active' => false]);
        $variantOfInactiveProduct = $this->variantInOrganization(productAttributes: ['is_active' => false]);

        $this->receive([['product_variant_id' => $this->variant->id, 'quantity' => 1]], warehouse: $inactiveWarehouse)
            ->assertSessionHasErrors('warehouse_id');

        $this->receive([
            ['product_variant_id' => $inactiveVariant->id, 'quantity' => 1],
            ['product_variant_id' => $variantOfInactiveProduct->id, 'quantity' => 1],
        ])->assertSessionHasErrors(['lines.0.product_variant_id', 'lines.1.product_variant_id']);

        $this->assertNothingReceived();
    }

    public function test_warehouses_and_variants_of_another_organization_are_rejected(): void
    {
        $foreignWarehouse = Warehouse::factory()->create();
        $foreignVariant = ProductVariant::factory()->create();

        $this->receive([['product_variant_id' => $foreignVariant->id, 'quantity' => 1]], warehouse: $foreignWarehouse)
            ->assertSessionHasErrors(['warehouse_id', 'lines.0.product_variant_id']);

        $this->assertNothingReceived();
    }

    public function test_quantities_must_be_positive_whole_numbers_and_dates_not_in_the_future(): void
    {
        $this->receive([['product_variant_id' => $this->variant->id, 'quantity' => 0]])
            ->assertSessionHasErrors('lines.0.quantity');

        $this->receive([['product_variant_id' => $this->variant->id, 'quantity' => 1.5]])
            ->assertSessionHasErrors('lines.0.quantity');

        $this->receive([['product_variant_id' => $this->variant->id, 'quantity' => 1]], receivedAt: now()->addDay()->toIso8601String())
            ->assertSessionHasErrors('received_at');

        $this->assertNothingReceived();
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    private function receive(array $lines, ?Warehouse $warehouse = null, ?string $receivedAt = null, ?string $notes = null): TestResponse
    {
        return $this->actingAs($this->user)->post(route('operations.receive.store'), [
            'warehouse_id' => ($warehouse ?? $this->warehouse)->id,
            'received_at' => $receivedAt ?? now()->subHour()->toIso8601String(),
            'notes' => $notes,
            'lines' => $lines,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $productAttributes
     */
    private function variantInOrganization(array $attributes = [], array $productAttributes = []): ProductVariant
    {
        return ProductVariant::factory()
            ->for(Product::factory()->for($this->organization)->state($productAttributes))
            ->create($attributes);
    }

    private function balance(ProductVariant $variant): int
    {
        return StockBalance::where([
            'product_variant_id' => $variant->id,
            'warehouse_id' => $this->warehouse->id,
        ])->value('quantity');
    }

    private function assertNothingReceived(): void
    {
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertDatabaseCount('stock_balances', 0);
        $this->assertSame(0, SerialNumber::whereHas('stockMovements')->count());
    }
}
