<?php

namespace Tests\Feature\Sales;

use App\Models\Customer;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CustomerTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();
        $this->manager = User::factory()->withOrganization($this->organization, 'manager')->create();
    }

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get(route('sales.customers.index'))->assertRedirect(route('login'));
    }

    public function test_index_lists_only_the_current_organizations_customers_active_first(): void
    {
        Customer::factory()->for($this->organization)->inactive()->create(['name' => 'Alpha Old']);
        Customer::factory()->for($this->organization)->create(['name' => 'Zeta Fabrics']);
        Customer::factory()->create(['name' => 'Other org customer']);

        $this->actingAs($this->manager)
            ->get(route('sales.customers.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('sales/customers/index')
                ->has('customers', 2)
                ->where('customers.0.name', 'Zeta Fabrics')
                ->where('customers.1.name', 'Alpha Old'));
    }

    public function test_members_can_view_customers_but_not_change_them(): void
    {
        $member = User::factory()->withOrganization($this->organization, 'member')->create();
        $customer = Customer::factory()->for($this->organization)->create();

        $this->actingAs($member)
            ->get(route('sales.customers.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('auth.can.manageSales', false));

        $this->actingAs($member)->post(route('sales.customers.store'), ['name' => 'New'])->assertForbidden();
        $this->actingAs($member)->put(route('sales.customers.update', $customer), ['name' => 'Renamed'])->assertForbidden();
        $this->actingAs($member)->delete(route('sales.customers.destroy', $customer))->assertForbidden();

        $this->assertDatabaseCount('customers', 1);
        $this->assertSame($customer->name, $customer->fresh()->name);
    }

    public function test_a_customer_can_be_created_with_trimmed_fields(): void
    {
        $this->actingAs($this->manager)
            ->post(route('sales.customers.store'), [
                'name' => ' Karim Fashion House ',
                'contact_name' => 'Abdur Rahman',
                'email' => 'Sales@Rahman.test',
                'phone' => '+880 1711 000001',
                'is_active' => '1',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('customers', [
            'organization_id' => $this->organization->id,
            'name' => 'Karim Fashion House',
            'email' => 'sales@rahman.test',
            'is_active' => true,
        ]);
    }

    public function test_customer_names_must_be_unique_within_the_organization_only(): void
    {
        Customer::factory()->for($this->organization)->create(['name' => 'Karim Fashion House']);
        Customer::factory()->create(['name' => 'Gazipur Garments']);

        $this->actingAs($this->manager)
            ->post(route('sales.customers.store'), ['name' => 'Karim Fashion House'])
            ->assertSessionHasErrors('name');

        $this->actingAs($this->manager)
            ->post(route('sales.customers.store'), ['name' => 'Gazipur Garments'])
            ->assertSessionHasNoErrors();
    }

    public function test_a_customer_can_be_updated_and_deactivated_keeping_its_own_name(): void
    {
        $customer = Customer::factory()->for($this->organization)->create(['name' => 'Karim Fashion House']);

        $this->actingAs($this->manager)
            ->put(route('sales.customers.update', $customer), [
                'name' => 'Karim Fashion House',
                'notes' => 'Net 30',
            ])
            ->assertSessionHasNoErrors();

        $customer->refresh();
        $this->assertSame('Net 30', $customer->notes);
        $this->assertFalse($customer->is_active);
    }

    public function test_customers_of_another_organization_cannot_be_updated_or_deleted(): void
    {
        $foreignCustomer = Customer::factory()->create();

        $this->actingAs($this->manager)
            ->put(route('sales.customers.update', $foreignCustomer), ['name' => 'Hijacked'])
            ->assertNotFound();

        $this->actingAs($this->manager)
            ->delete(route('sales.customers.destroy', $foreignCustomer))
            ->assertNotFound();

        $this->assertModelExists($foreignCustomer);
    }

    public function test_a_customer_can_be_deleted(): void
    {
        $customer = Customer::factory()->for($this->organization)->create();

        $this->actingAs($this->manager)
            ->delete(route('sales.customers.destroy', $customer))
            ->assertRedirect();

        $this->assertModelMissing($customer);
    }
}
