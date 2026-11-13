<?php

namespace Tests\Feature;

use App\Models\Color;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Size;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Roles rank admin > manager > member. Everyone views and runs operations; managers change the catalog and
 * warehouses; only admins manage members.
 */
class RolePermissionsTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();
    }

    /**
     * @return array<string, array{string, array{runOperations: bool, manageCatalog: bool, manageWarehouses: bool, managePurchasing: bool, manageSales: bool, manageMembers: bool, manageSettings: bool, administerPlatform: bool}}>
     */
    public static function rolePermissions(): array
    {
        return [
            'member' => ['member', ['runOperations' => true, 'manageCatalog' => false, 'manageWarehouses' => false, 'managePurchasing' => false, 'manageSales' => false, 'manageMembers' => false, 'manageSettings' => false, 'administerPlatform' => false]],
            'manager' => ['manager', ['runOperations' => true, 'manageCatalog' => true, 'manageWarehouses' => true, 'managePurchasing' => true, 'manageSales' => true, 'manageMembers' => false, 'manageSettings' => false, 'administerPlatform' => false]],
            'admin' => ['admin', ['runOperations' => true, 'manageCatalog' => true, 'manageWarehouses' => true, 'managePurchasing' => true, 'manageSales' => true, 'manageMembers' => true, 'manageSettings' => true, 'administerPlatform' => false]],
        ];
    }

    /**
     * @param  array<string, bool>  $permissions
     */
    #[DataProvider('rolePermissions')]
    public function test_pages_receive_the_permissions_of_the_role(string $role, array $permissions): void
    {
        $this->actingAs($this->userWithRole($role))
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('auth.can', $permissions));
    }

    public function test_members_can_view_everything_and_run_operations(): void
    {
        $member = $this->userWithRole('member');
        $product = Product::factory()->for($this->organization)->create();

        foreach ([
            route('catalog.sizes.index'),
            route('catalog.colors.index'),
            route('catalog.products.index'),
            route('catalog.products.show', $product),
            route('warehouses.index'),
            route('inventory.stock.index'),
            route('operations.receive.create'),
            route('operations.transfer.create'),
            route('operations.dispatch.create'),
            route('operations.return.create'),
        ] as $url) {
            $this->actingAs($member)->get($url)->assertOk();
        }
    }

    public function test_members_cannot_change_the_catalog_or_warehouses(): void
    {
        $member = $this->userWithRole('member');
        $size = Size::factory()->for($this->organization)->create();
        $color = Color::factory()->for($this->organization)->create();
        $product = Product::factory()->for($this->organization)->create();
        $variant = ProductVariant::factory()->for($product)->create();
        $warehouse = Warehouse::factory()->for($this->organization)->create();

        $requests = [
            ['post', route('catalog.sizes.store')],
            ['put', route('catalog.sizes.update', $size)],
            ['delete', route('catalog.sizes.destroy', $size)],
            ['post', route('catalog.colors.store')],
            ['put', route('catalog.colors.update', $color)],
            ['delete', route('catalog.colors.destroy', $color)],
            ['post', route('catalog.products.store')],
            ['put', route('catalog.products.update', $product)],
            ['delete', route('catalog.products.destroy', $product)],
            ['post', route('catalog.products.variants.store', $product)],
            ['put', route('catalog.variants.update', $variant)],
            ['delete', route('catalog.variants.destroy', $variant)],
            ['post', route('warehouses.store')],
            ['put', route('warehouses.update', $warehouse)],
            ['delete', route('warehouses.destroy', $warehouse)],
        ];

        foreach ($requests as [$method, $url]) {
            $this->actingAs($member)->{$method}($url, [])->assertForbidden();
        }

        $this->assertModelExists($size);
        $this->assertModelExists($variant);
        $this->assertModelExists($warehouse);
    }

    public function test_managers_can_change_the_catalog_and_warehouses(): void
    {
        $manager = $this->userWithRole('manager');

        $this->actingAs($manager)
            ->post(route('catalog.sizes.store'), ['name' => 'XL', 'sort_order' => 1, 'is_active' => '1'])
            ->assertSessionHasNoErrors();

        $this->actingAs($manager)
            ->post(route('warehouses.store'), ['name' => 'Shop', 'code' => 'SHP', 'is_active' => '1'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('sizes', ['organization_id' => $this->organization->id, 'name' => 'XL']);
        $this->assertDatabaseHas('warehouses', ['organization_id' => $this->organization->id, 'code' => 'SHP']);
    }

    public function test_managers_cannot_manage_members(): void
    {
        $this->actingAs($this->userWithRole('manager'))
            ->get(route('organization.members.index'))
            ->assertForbidden();
    }

    private function userWithRole(string $role): User
    {
        return User::factory()->withOrganization($this->organization, $role)->create();
    }
}
