<?php

namespace Tests\Feature\Organization;

use App\Models\Organization;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create(['name' => 'Acme', 'slug' => 'acme']);
        $this->admin = User::factory()->withOrganization($this->organization)->create();
    }

    public function test_every_member_sees_the_settings_with_defaults(): void
    {
        $member = User::factory()->withOrganization($this->organization, 'member')->create();

        $this->actingAs($member)
            ->get(route('organization.settings.general.edit'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('organization/settings/general')
                ->where('settings.name', 'Acme')
                ->where('settings.slug', 'acme')
                ->where('settings.contact_email', null)
                ->where('settings.timezone', 'Asia/Dhaka')
                ->where('settings.currency', 'BDT')
                ->where('auth.can.manageSettings', false));

        $this->actingAs($member)
            ->get(route('organization.settings.inventory.edit'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('organization/settings/inventory')
                ->where('settings.default_warehouse_id', null)
                ->where('settings.default_reorder_level', null));
    }

    public function test_admins_save_general_settings_but_not_the_slug(): void
    {
        $this->actingAs($this->admin)
            ->put(route('organization.settings.general.update'), [
                'name' => ' Acme Ltd ',
                'slug' => 'hijacked',
                'contact_email' => 'Info@Acme.test',
                'phone' => '+880 1700 000000',
                'address' => 'Road 1, Dhaka',
                'timezone' => 'Europe/London',
                'currency' => ' gbp ',
            ])
            ->assertSessionHasNoErrors();

        $organization = $this->organization->fresh();

        $this->assertSame('Acme Ltd', $organization->name);
        $this->assertSame('acme', $organization->slug);
        $this->assertSame('info@acme.test', $organization->setting('contact_email'));
        $this->assertSame('+880 1700 000000', $organization->setting('phone'));
        $this->assertSame('Road 1, Dhaka', $organization->setting('address'));
        $this->assertSame('Europe/London', $organization->setting('timezone'));
        $this->assertSame('GBP', $organization->setting('currency'));
    }

    public function test_organization_pages_receive_the_organizations_currency(): void
    {
        $this->organization->putSettings(['currency' => 'USD']);
        $this->organization->save();

        $this->actingAs($this->admin)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('organizationCurrency', 'USD'));

        $this->actingAs($this->admin)
            ->get(route('profile.edit'))
            ->assertInertia(fn (Assert $page) => $page->where('organizationCurrency', null));
    }

    public function test_the_currency_must_be_a_three_letter_code(): void
    {
        foreach (['', 'TK', 'TAKA', '৳', '12A'] as $invalidCurrency) {
            $this->actingAs($this->admin)
                ->put(route('organization.settings.general.update'), ['name' => 'Acme', 'timezone' => 'UTC', 'currency' => $invalidCurrency])
                ->assertSessionHasErrors('currency');
        }

        $this->assertSame('BDT', $this->organization->fresh()->setting('currency'));
    }

    public function test_organization_pages_receive_the_organizations_timezone(): void
    {
        $this->organization->putSettings(['timezone' => 'Europe/London']);
        $this->organization->save();

        $this->actingAs($this->admin)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('organizationTimezone', 'Europe/London'));

        // Pages outside an organization fall back to the browser's timezone.
        $this->actingAs($this->admin)
            ->get(route('profile.edit'))
            ->assertInertia(fn (Assert $page) => $page->where('organizationTimezone', null));
    }

    public function test_the_timezone_must_be_a_real_zone(): void
    {
        $this->actingAs($this->admin)
            ->put(route('organization.settings.general.update'), ['name' => 'Acme', 'timezone' => 'Mars/Olympus'])
            ->assertSessionHasErrors('timezone');

        $this->assertSame('Asia/Dhaka', $this->organization->fresh()->setting('timezone'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function nonAdminRoles(): array
    {
        return ['manager' => ['manager'], 'member' => ['member']];
    }

    #[DataProvider('nonAdminRoles')]
    public function test_only_admins_change_settings(string $role): void
    {
        $user = User::factory()->withOrganization($this->organization, $role)->create();

        $this->actingAs($user)
            ->put(route('organization.settings.general.update'), ['name' => 'Renamed', 'timezone' => 'UTC'])
            ->assertForbidden();
        $this->actingAs($user)
            ->put(route('organization.settings.inventory.update'), ['default_reorder_level' => 5])
            ->assertForbidden();

        $this->assertSame('Acme', $this->organization->fresh()->name);
        $this->assertNull($this->organization->fresh()->setting('default_reorder_level'));
    }

    public function test_the_default_warehouse_must_be_an_active_warehouse_of_this_organization(): void
    {
        $inactive = Warehouse::factory()->for($this->organization)->create(['is_active' => false]);
        $foreign = Warehouse::factory()->create();

        foreach ([$inactive, $foreign] as $warehouse) {
            $this->actingAs($this->admin)
                ->put(route('organization.settings.inventory.update'), ['default_warehouse_id' => $warehouse->id])
                ->assertSessionHasErrors('default_warehouse_id');
        }

        $this->assertNull($this->organization->fresh()->setting('default_warehouse_id'));
    }

    public function test_inventory_defaults_prefill_operations_and_new_variants(): void
    {
        $warehouse = Warehouse::factory()->for($this->organization)->create();
        $product = Product::factory()->for($this->organization)->create();

        $this->actingAs($this->admin)
            ->put(route('organization.settings.inventory.update'), ['default_warehouse_id' => $warehouse->id, 'default_reorder_level' => 12])
            ->assertSessionHasNoErrors();

        foreach (['receive', 'transfer', 'dispatch', 'return'] as $operation) {
            $this->actingAs($this->admin)
                ->get(route("operations.{$operation}.create"))
                ->assertInertia(fn (Assert $page) => $page->where('defaultWarehouseId', $warehouse->id));
        }

        $this->actingAs($this->admin)
            ->get(route('catalog.products.show', $product))
            ->assertInertia(fn (Assert $page) => $page->where('defaultReorderLevel', 12));

        // A deactivated default warehouse is ignored rather than preselected.
        $warehouse->update(['is_active' => false]);

        $this->actingAs($this->admin)
            ->get(route('operations.receive.create'))
            ->assertInertia(fn (Assert $page) => $page->where('defaultWarehouseId', null));

        // "none" clears the default.
        $this->actingAs($this->admin)
            ->put(route('organization.settings.inventory.update'), ['default_warehouse_id' => 'none', 'default_reorder_level' => ''])
            ->assertSessionHasNoErrors();

        $this->assertNull($this->organization->fresh()->setting('default_warehouse_id'));
        $this->assertNull($this->organization->fresh()->setting('default_reorder_level'));
    }

    public function test_members_can_leave_and_lose_access(): void
    {
        $member = User::factory()->withOrganization($this->organization, 'member')->create();

        $this->actingAs($member)
            ->delete(route('organization.settings.membership.destroy'))
            ->assertRedirect(route('dashboard'));

        $this->assertFalse($this->organization->users()->whereKey($member->id)->exists());

        $this->actingAs($member->fresh())->get(route('dashboard'))->assertRedirect(route('no-organization'));
    }

    public function test_the_last_admin_cannot_leave_but_one_of_two_admins_can(): void
    {
        $this->actingAs($this->admin)
            ->get(route('organization.settings.membership.edit'))
            ->assertInertia(fn (Assert $page) => $page->where('isLastAdmin', true));

        $this->actingAs($this->admin)->delete(route('organization.settings.membership.destroy'));

        $this->assertTrue($this->organization->hasAdmin($this->admin));

        User::factory()->withOrganization($this->organization)->create();

        $this->actingAs($this->admin)
            ->delete(route('organization.settings.membership.destroy'))
            ->assertRedirect(route('dashboard'));

        $this->assertFalse($this->organization->users()->whereKey($this->admin->id)->exists());
    }
}
