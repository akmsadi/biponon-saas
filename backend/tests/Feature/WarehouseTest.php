<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\SerialNumber;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WarehouseTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();
        $this->user = User::factory()->withOrganization($this->organization)->create();
    }

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get(route('warehouses.index'))->assertRedirect(route('login'));
    }

    public function test_index_lists_only_the_current_organizations_warehouses_with_in_stock_counts(): void
    {
        $warehouse = Warehouse::factory()->for($this->organization)->create(['name' => 'Dhaka Main']);
        SerialNumber::factory()->count(2)->for($this->organization)->create(['current_warehouse_id' => $warehouse->id]);
        Warehouse::factory()->create(['name' => 'Other org warehouse']);

        $this->actingAs($this->user)
            ->get(route('warehouses.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('warehouses/index')
                ->has('warehouses', 1)
                ->where('warehouses.0.name', 'Dhaka Main')
                ->where('warehouses.0.in_stock_count', 2));
    }

    public function test_a_warehouse_can_be_created_with_normalized_codes(): void
    {
        $this->actingAs($this->user)
            ->post(route('warehouses.store'), [
                'name' => 'Chattogram Depot',
                'code' => 'ctg-01',
                'city' => 'Chattogram',
                'country_code' => 'bd',
                'is_active' => true,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('warehouses', [
            'organization_id' => $this->organization->id,
            'name' => 'Chattogram Depot',
            'code' => 'CTG-01',
            'country_code' => 'BD',
            'is_active' => true,
        ]);
    }

    public function test_warehouse_codes_must_be_unique_within_the_organization(): void
    {
        Warehouse::factory()->for($this->organization)->create(['code' => 'DHK-01']);

        $this->actingAs($this->user)
            ->post(route('warehouses.store'), ['name' => 'Second', 'code' => 'dhk-01'])
            ->assertSessionHasErrors('code');
    }

    public function test_country_code_must_be_two_letters(): void
    {
        foreach (['BGD', 'B', '12'] as $invalidCountryCode) {
            $this->actingAs($this->user)
                ->post(route('warehouses.store'), ['name' => 'Bad', 'code' => 'X-'.$invalidCountryCode, 'country_code' => $invalidCountryCode])
                ->assertSessionHasErrors('country_code');
        }

        $this->assertDatabaseCount('warehouses', 0);
    }

    public function test_a_warehouse_holding_stock_can_be_deactivated(): void
    {
        $warehouse = Warehouse::factory()->for($this->organization)->create();
        SerialNumber::factory()->for($this->organization)->create(['current_warehouse_id' => $warehouse->id]);

        $this->actingAs($this->user)
            ->put(route('warehouses.update', $warehouse), [
                'name' => $warehouse->name,
                'code' => $warehouse->code,
                'is_active' => false,
            ])
            ->assertSessionHasNoErrors();

        $this->assertFalse($warehouse->refresh()->is_active);
    }

    public function test_warehouses_of_another_organization_cannot_be_updated_or_deleted(): void
    {
        $foreignWarehouse = Warehouse::factory()->create();

        $this->actingAs($this->user)
            ->put(route('warehouses.update', $foreignWarehouse), ['name' => 'Hijacked', 'code' => 'HJK'])
            ->assertNotFound();

        $this->actingAs($this->user)
            ->delete(route('warehouses.destroy', $foreignWarehouse))
            ->assertNotFound();

        $this->assertModelExists($foreignWarehouse);
    }

    public function test_a_warehouse_without_history_can_be_deleted(): void
    {
        $warehouse = Warehouse::factory()->for($this->organization)->create();

        $this->actingAs($this->user)
            ->delete(route('warehouses.destroy', $warehouse))
            ->assertRedirect();

        $this->assertModelMissing($warehouse);
    }

    public function test_a_warehouse_holding_stock_cannot_be_deleted(): void
    {
        $warehouse = Warehouse::factory()->for($this->organization)->create();
        SerialNumber::factory()->for($this->organization)->create(['current_warehouse_id' => $warehouse->id]);

        $this->actingAs($this->user)
            ->delete(route('warehouses.destroy', $warehouse))
            ->assertRedirect();

        $this->assertModelExists($warehouse);
    }

    public function test_a_warehouse_with_only_movement_history_cannot_be_deleted(): void
    {
        $warehouse = Warehouse::factory()->for($this->organization)->create();
        $dispatchedSerial = SerialNumber::factory()->dispatched()->for($this->organization)->create();
        StockMovement::factory()->create([
            'serial_number_id' => $dispatchedSerial->id,
            'from_warehouse_id' => $warehouse->id,
            'to_warehouse_id' => null,
            'movement_type' => 'dispatch',
        ]);

        $this->actingAs($this->user)
            ->delete(route('warehouses.destroy', $warehouse))
            ->assertRedirect();

        $this->assertModelExists($warehouse);
    }
}
