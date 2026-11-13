<?php

namespace Tests\Feature\Purchasing;

use App\Models\Organization;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SupplierTest extends TestCase
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
        $this->get(route('purchasing.suppliers.index'))->assertRedirect(route('login'));
    }

    public function test_index_lists_only_the_current_organizations_suppliers_active_first(): void
    {
        Supplier::factory()->for($this->organization)->inactive()->create(['name' => 'Alpha Old']);
        Supplier::factory()->for($this->organization)->create(['name' => 'Zeta Fabrics']);
        Supplier::factory()->create(['name' => 'Other org supplier']);

        $this->actingAs($this->manager)
            ->get(route('purchasing.suppliers.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('purchasing/suppliers/index')
                ->has('suppliers', 2)
                ->where('suppliers.0.name', 'Zeta Fabrics')
                ->where('suppliers.1.name', 'Alpha Old'));
    }

    public function test_members_can_view_suppliers_but_not_change_them(): void
    {
        $member = User::factory()->withOrganization($this->organization, 'member')->create();
        $supplier = Supplier::factory()->for($this->organization)->create();

        $this->actingAs($member)
            ->get(route('purchasing.suppliers.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('auth.can.managePurchasing', false));

        $this->actingAs($member)->post(route('purchasing.suppliers.store'), ['name' => 'New'])->assertForbidden();
        $this->actingAs($member)->put(route('purchasing.suppliers.update', $supplier), ['name' => 'Renamed'])->assertForbidden();
        $this->actingAs($member)->delete(route('purchasing.suppliers.destroy', $supplier))->assertForbidden();

        $this->assertDatabaseCount('suppliers', 1);
        $this->assertSame($supplier->name, $supplier->fresh()->name);
    }

    public function test_a_supplier_can_be_created_with_trimmed_fields(): void
    {
        $this->actingAs($this->manager)
            ->post(route('purchasing.suppliers.store'), [
                'name' => ' Rahman Textiles ',
                'contact_name' => 'Abdur Rahman',
                'email' => 'Sales@Rahman.test',
                'phone' => '+880 1711 000001',
                'is_active' => '1',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('suppliers', [
            'organization_id' => $this->organization->id,
            'name' => 'Rahman Textiles',
            'email' => 'sales@rahman.test',
            'is_active' => true,
        ]);
    }

    public function test_supplier_names_must_be_unique_within_the_organization_only(): void
    {
        Supplier::factory()->for($this->organization)->create(['name' => 'Rahman Textiles']);
        Supplier::factory()->create(['name' => 'Gazipur Garments']);

        $this->actingAs($this->manager)
            ->post(route('purchasing.suppliers.store'), ['name' => 'Rahman Textiles'])
            ->assertSessionHasErrors('name');

        $this->actingAs($this->manager)
            ->post(route('purchasing.suppliers.store'), ['name' => 'Gazipur Garments'])
            ->assertSessionHasNoErrors();
    }

    public function test_a_supplier_can_be_updated_and_deactivated_keeping_its_own_name(): void
    {
        $supplier = Supplier::factory()->for($this->organization)->create(['name' => 'Rahman Textiles']);

        $this->actingAs($this->manager)
            ->put(route('purchasing.suppliers.update', $supplier), [
                'name' => 'Rahman Textiles',
                'notes' => 'Net 30',
            ])
            ->assertSessionHasNoErrors();

        $supplier->refresh();
        $this->assertSame('Net 30', $supplier->notes);
        $this->assertFalse($supplier->is_active);
    }

    public function test_suppliers_of_another_organization_cannot_be_updated_or_deleted(): void
    {
        $foreignSupplier = Supplier::factory()->create();

        $this->actingAs($this->manager)
            ->put(route('purchasing.suppliers.update', $foreignSupplier), ['name' => 'Hijacked'])
            ->assertNotFound();

        $this->actingAs($this->manager)
            ->delete(route('purchasing.suppliers.destroy', $foreignSupplier))
            ->assertNotFound();

        $this->assertModelExists($foreignSupplier);
    }

    public function test_a_supplier_can_be_deleted(): void
    {
        $supplier = Supplier::factory()->for($this->organization)->create();

        $this->actingAs($this->manager)
            ->delete(route('purchasing.suppliers.destroy', $supplier))
            ->assertRedirect();

        $this->assertModelMissing($supplier);
    }
}
