<?php

namespace Tests\Feature\Catalog;

use App\Models\Organization;
use App\Models\Size;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SizeTest extends TestCase
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
        $this->get(route('catalog.sizes.index'))->assertRedirect(route('login'));
    }

    public function test_index_lists_only_the_current_organizations_sizes(): void
    {
        Size::factory()->for($this->organization)->create(['name' => 'M']);
        Size::factory()->create(['name' => 'Other org size']);

        $this->actingAs($this->user)
            ->get(route('catalog.sizes.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('catalog/sizes/index')
                ->has('sizes', 1)
                ->where('sizes.0.name', 'M'));
    }

    public function test_a_size_can_be_created(): void
    {
        $this->actingAs($this->user)
            ->post(route('catalog.sizes.store'), [
                'name' => 'XL',
                'code' => 'XL',
                'sort_order' => 5,
                'is_active' => true,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('sizes', [
            'organization_id' => $this->organization->id,
            'name' => 'XL',
            'sort_order' => 5,
            'is_active' => true,
        ]);
    }

    public function test_size_names_must_be_unique_within_the_organization(): void
    {
        Size::factory()->for($this->organization)->create(['name' => 'XL']);

        $this->actingAs($this->user)
            ->post(route('catalog.sizes.store'), ['name' => 'XL'])
            ->assertSessionHasErrors('name');
    }

    public function test_the_same_size_name_can_exist_in_another_organization(): void
    {
        Size::factory()->create(['name' => 'XL']);

        $this->actingAs($this->user)
            ->post(route('catalog.sizes.store'), ['name' => 'XL'])
            ->assertSessionHasNoErrors();
    }

    public function test_a_size_can_be_updated(): void
    {
        $size = Size::factory()->for($this->organization)->create(['name' => 'M']);

        $this->actingAs($this->user)
            ->put(route('catalog.sizes.update', $size), [
                'name' => 'M',
                'code' => 'MED',
                'sort_order' => 2,
                'is_active' => false,
            ])
            ->assertSessionHasNoErrors();

        $size->refresh();
        $this->assertSame('MED', $size->code);
        $this->assertFalse($size->is_active);
    }

    public function test_sizes_of_another_organization_cannot_be_updated_or_deleted(): void
    {
        $foreignSize = Size::factory()->create();

        $this->actingAs($this->user)
            ->put(route('catalog.sizes.update', $foreignSize), ['name' => 'Hijacked'])
            ->assertNotFound();

        $this->actingAs($this->user)
            ->delete(route('catalog.sizes.destroy', $foreignSize))
            ->assertNotFound();

        $this->assertModelExists($foreignSize);
    }

    public function test_an_unused_size_can_be_deleted(): void
    {
        $size = Size::factory()->for($this->organization)->create();

        $this->actingAs($this->user)
            ->delete(route('catalog.sizes.destroy', $size))
            ->assertRedirect();

        $this->assertModelMissing($size);
    }
}
