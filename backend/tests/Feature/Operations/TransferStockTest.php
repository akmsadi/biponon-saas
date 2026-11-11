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

class TransferStockTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private User $user;

    private Warehouse $source;

    private Warehouse $destination;

    private ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();
        $this->user = User::factory()->withOrganization($this->organization)->create();
        $this->source = Warehouse::factory()->for($this->organization)->create(['code' => 'SRC']);
        $this->destination = Warehouse::factory()->for($this->organization)->create(['code' => 'DST']);
        $this->variant = $this->variantInOrganization();
        $this->stock($this->variant, $this->source, 10);
    }

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get(route('operations.transfer.create'))->assertRedirect(route('login'));
    }

    public function test_the_form_offers_variants_in_stock_with_their_quantity_per_warehouse(): void
    {
        $this->variantInOrganization();
        $emptied = $this->variantInOrganization();
        $this->stock($emptied, $this->source, 0);
        $this->stock(ProductVariant::factory()->create(), Warehouse::factory()->create(), 5);

        $this->actingAs($this->user)
            ->get(route('operations.transfer.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('operations/transfer')
                ->has('warehouses', 2)
                ->has('products', 1)
                ->has('products.0.variants', 1)
                ->where('products.0.variants.0.id', $this->variant->id)
                ->where('products.0.variants.0.stock', [$this->source->id => 10]));
    }

    public function test_transferring_moves_the_quantity_and_records_a_movement(): void
    {
        $this->transfer([['product_variant_id' => $this->variant->id, 'quantity' => 4]], notes: 'Restock shop')
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('operations.transfer.create'));

        $this->assertDatabaseHas('stock_movements', [
            'organization_id' => $this->organization->id,
            'product_variant_id' => $this->variant->id,
            'from_warehouse_id' => $this->source->id,
            'to_warehouse_id' => $this->destination->id,
            'quantity' => 4,
            'movement_type' => 'transfer',
            'performed_by' => $this->user->id,
            'notes' => 'Restock shop',
        ]);
        $this->assertSame(6, $this->balance($this->variant, $this->source));
        $this->assertSame(4, $this->balance($this->variant, $this->destination));
    }

    public function test_the_whole_balance_can_leave_an_inactive_source(): void
    {
        $this->source->update(['is_active' => false]);

        $this->transfer([['product_variant_id' => $this->variant->id, 'quantity' => 10]])
            ->assertSessionHasNoErrors();

        $this->assertSame(0, $this->balance($this->variant, $this->source));
        $this->assertSame(10, $this->balance($this->variant, $this->destination));
    }

    public function test_named_serials_move_to_the_destination_and_are_linked_to_the_movement(): void
    {
        $this->serial('SN-001');
        $this->serial('SN-002');
        $staying = $this->serial('SN-003');

        $this->transfer([['product_variant_id' => $this->variant->id, 'quantity' => 2, 'serials' => " SN-001\r\n\r\nSN-002 \n"]])
            ->assertSessionHasNoErrors();

        $movement = StockMovement::sole();

        $this->assertSame(['SN-001', 'SN-002'], $movement->serialNumbers()->orderBy('serial_number')->pluck('serial_number')->all());
        $this->assertSame(2, SerialNumber::where('current_warehouse_id', $this->destination->id)->count());
        $this->assertSame($this->source->id, $staying->fresh()->current_warehouse_id);
    }

    public function test_serials_are_optional(): void
    {
        $this->serial('SN-001');

        $this->transfer([['product_variant_id' => $this->variant->id, 'quantity' => 3]])
            ->assertSessionHasNoErrors();

        $this->assertSame(7, $this->balance($this->variant, $this->source));
        $this->assertSame($this->source->id, SerialNumber::sole()->current_warehouse_id);
    }

    public function test_the_quantity_cannot_exceed_what_the_source_holds(): void
    {
        $this->transfer([['product_variant_id' => $this->variant->id, 'quantity' => 11]])
            ->assertSessionHasErrors(['lines.0.quantity' => 'Only 10 available in SRC.']);

        $this->transfer([['product_variant_id' => $this->variantInOrganization()->id, 'quantity' => 1]])
            ->assertSessionHasErrors(['lines.0.quantity' => 'Only 0 available in SRC.']);

        $this->assertNothingTransferred();
    }

    public function test_the_destination_must_be_another_active_warehouse(): void
    {
        $this->transfer([['product_variant_id' => $this->variant->id, 'quantity' => 1]], destination: $this->source)
            ->assertSessionHasErrors('to_warehouse_id');

        $this->transfer([['product_variant_id' => $this->variant->id, 'quantity' => 1]], destination: Warehouse::factory()->inactive()->for($this->organization)->create())
            ->assertSessionHasErrors('to_warehouse_id');

        $this->assertNothingTransferred();
    }

    public function test_the_serial_count_must_match_the_quantity(): void
    {
        $this->serial('SN-001');

        $this->transfer([['product_variant_id' => $this->variant->id, 'quantity' => 2, 'serials' => 'SN-001']])
            ->assertSessionHasErrors('lines.0.serials');

        $this->assertNothingTransferred();
    }

    public function test_serials_must_be_units_of_the_variant_in_stock_at_the_source(): void
    {
        $this->serial('SN-OK');
        $this->serial('SN-THERE', ['current_warehouse_id' => $this->destination->id]);
        $this->serial('SN-GONE', ['status' => 'dispatched']);
        SerialNumber::factory()->for($this->variantInOrganization())->create([
            'organization_id' => $this->organization->id,
            'current_warehouse_id' => $this->source->id,
            'serial_number' => 'SN-OTHER',
        ]);

        $this->transfer([['product_variant_id' => $this->variant->id, 'quantity' => 5, 'serials' => "SN-OK\nSN-THERE\nSN-GONE\nSN-OTHER\nSN-UNKNOWN"]])
            ->assertSessionHasErrors(['lines.0.serials' => 'Not in stock in SRC for this variant: SN-THERE, SN-GONE, SN-OTHER, SN-UNKNOWN']);

        $this->assertNothingTransferred();
    }

    public function test_serials_cannot_repeat_within_the_transfer(): void
    {
        $this->serial('SN-001');

        $this->transfer([['product_variant_id' => $this->variant->id, 'quantity' => 2, 'serials' => "SN-001\nSN-001"]])
            ->assertSessionHasErrors(['lines.0.serials' => 'Repeated in this transfer: SN-001']);

        $this->assertNothingTransferred();
    }

    public function test_warehouses_and_variants_of_another_organization_are_rejected(): void
    {
        $foreignVariant = ProductVariant::factory()->create();

        $this->transfer(
            [['product_variant_id' => $foreignVariant->id, 'quantity' => 1]],
            source: Warehouse::factory()->create(),
            destination: Warehouse::factory()->create(),
        )->assertSessionHasErrors(['from_warehouse_id', 'to_warehouse_id', 'lines.0.product_variant_id']);

        $this->assertNothingTransferred();
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    private function transfer(array $lines, ?Warehouse $source = null, ?Warehouse $destination = null, ?string $notes = null): TestResponse
    {
        return $this->actingAs($this->user)->post(route('operations.transfer.store'), [
            'from_warehouse_id' => ($source ?? $this->source)->id,
            'to_warehouse_id' => ($destination ?? $this->destination)->id,
            'transferred_at' => now()->subHour()->toIso8601String(),
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
            'current_warehouse_id' => $this->source->id,
            'serial_number' => $serialNumber,
            ...$attributes,
        ]);
    }

    private function balance(ProductVariant $variant, Warehouse $warehouse): int
    {
        return (int) StockBalance::where([
            'product_variant_id' => $variant->id,
            'warehouse_id' => $warehouse->id,
        ])->value('quantity');
    }

    private function assertNothingTransferred(): void
    {
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertSame(10, $this->balance($this->variant, $this->source));
        $this->assertSame(0, SerialNumber::whereHas('stockMovements')->count());
    }
}
