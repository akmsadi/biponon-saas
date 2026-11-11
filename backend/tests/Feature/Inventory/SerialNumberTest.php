<?php

namespace Tests\Feature\Inventory;

use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SerialNumber;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SerialNumberTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private User $user;

    private Warehouse $dhaka;

    private Warehouse $chattogram;

    private ProductVariant $panjabi;

    private ProductVariant $saree;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();
        $this->user = User::factory()->withOrganization($this->organization)->create();
        $this->dhaka = Warehouse::factory()->for($this->organization)->create(['code' => 'DHK']);
        $this->chattogram = Warehouse::factory()->for($this->organization)->create(['code' => 'CTG']);
        $this->panjabi = ProductVariant::factory()
            ->for(Product::factory()->for($this->organization)->create(['name' => 'Panjabi']))
            ->create(['sku' => 'PNJ-M-RED']);
        $this->saree = ProductVariant::factory()
            ->for(Product::factory()->for($this->organization)->create(['name' => 'Saree']))
            ->create(['sku' => 'SAR-F-BLU']);
    }

    /**
     * @return list<string>
     */
    private function listedSerials(array $query = []): array
    {
        return collect($this->actingAs($this->user)->get(route('inventory.serials.index', $query))->viewData('page')['props']['serials']['data'])
            ->pluck('serial_number')
            ->sort()
            ->values()
            ->all();
    }

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get(route('inventory.serials.index'))->assertRedirect(route('login'));
    }

    public function test_it_lists_only_the_current_organizations_serials(): void
    {
        SerialNumber::factory()->for($this->panjabi)->create(['serial_number' => 'SN-001', 'current_warehouse_id' => $this->dhaka->id]);
        SerialNumber::factory()->for($this->saree)->dispatched()->create(['serial_number' => 'SN-002']);
        SerialNumber::factory()->create(['serial_number' => 'FOREIGN-1']);

        $this->actingAs($this->user)
            ->get(route('inventory.serials.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('inventory/serials')
                ->has('serials.data', 2)
                ->where('statuses', ['dispatched', 'in_stock'])
                ->has('warehouses', 2));

        $this->assertSame(['SN-001', 'SN-002'], $this->listedSerials());
    }

    public function test_it_filters_by_search_warehouse_and_status(): void
    {
        SerialNumber::factory()->for($this->panjabi)->create(['serial_number' => 'ABC-100', 'current_warehouse_id' => $this->dhaka->id]);
        SerialNumber::factory()->for($this->panjabi)->create(['serial_number' => 'ABC-200', 'current_warehouse_id' => $this->chattogram->id]);
        SerialNumber::factory()->for($this->saree)->dispatched()->create(['serial_number' => 'XYZ-300']);

        $this->assertSame(['ABC-100', 'ABC-200'], $this->listedSerials(['search' => 'abc']));
        $this->assertSame(['XYZ-300'], $this->listedSerials(['search' => 'sar-f']));
        $this->assertSame(['XYZ-300'], $this->listedSerials(['search' => 'saree']));
        $this->assertSame(['ABC-200'], $this->listedSerials(['warehouse' => $this->chattogram->id]));
        $this->assertSame(['XYZ-300'], $this->listedSerials(['status' => 'dispatched']));
    }

    public function test_history_returns_only_that_serials_movements_newest_first(): void
    {
        $serial = SerialNumber::factory()->for($this->panjabi)->create(['current_warehouse_id' => $this->chattogram->id]);
        $receipt = StockMovement::factory()->for($this->panjabi)->create([
            'to_warehouse_id' => $this->dhaka->id,
            'quantity' => 5,
            'performed_by' => $this->user->id,
            'occurred_at' => now()->subDay(),
        ]);
        $transfer = StockMovement::factory()->for($this->panjabi)->create([
            'movement_type' => 'transfer',
            'from_warehouse_id' => $this->dhaka->id,
            'to_warehouse_id' => $this->chattogram->id,
            'quantity' => 1,
            'occurred_at' => now(),
        ]);
        $serial->stockMovements()->attach([$receipt->id, $transfer->id]);
        StockMovement::factory()->for($this->panjabi)->create();

        $this->actingAs($this->user)
            ->getJson(route('inventory.serials.history', $serial))
            ->assertOk()
            ->assertJsonCount(2, 'movements')
            ->assertJsonPath('movements.0.movement_type', 'transfer')
            ->assertJsonPath('movements.0.from.code', 'DHK')
            ->assertJsonPath('movements.0.to.code', 'CTG')
            ->assertJsonPath('movements.1.movement_type', 'receive')
            ->assertJsonPath('movements.1.from', null)
            ->assertJsonPath('movements.1.performed_by', $this->user->name);
    }

    public function test_another_organizations_serial_history_is_not_found(): void
    {
        $this->actingAs($this->user)
            ->getJson(route('inventory.serials.history', SerialNumber::factory()->create()))
            ->assertNotFound();
    }

    public function test_users_without_an_organization_cannot_see_serials(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('inventory.serials.index'))
            ->assertForbidden();
    }
}
