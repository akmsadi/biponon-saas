<?php

namespace Tests\Feature\Catalog;

use App\Models\Color;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ColorTest extends TestCase
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
        $this->get(route('catalog.colors.index'))->assertRedirect(route('login'));
    }

    public function test_index_lists_only_the_current_organizations_colors(): void
    {
        Color::factory()->for($this->organization)->create(['name' => 'Navy']);
        Color::factory()->create(['name' => 'Other org color']);

        $this->actingAs($this->user)
            ->get(route('catalog.colors.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('catalog/colors/index')
                ->has('colors', 1)
                ->where('colors.0.name', 'Navy')
                ->where('colors.0.product_variants_count', 0));
    }

    public function test_a_color_can_be_created_with_a_normalized_hex_code(): void
    {
        $this->actingAs($this->user)
            ->post(route('catalog.colors.store'), [
                'name' => 'Maroon',
                'code' => 'MRN',
                'hex_code' => '#7a1e2c',
                'is_active' => true,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('colors', [
            'organization_id' => $this->organization->id,
            'name' => 'Maroon',
            'hex_code' => '#7A1E2C',
            'is_active' => true,
        ]);
    }

    public function test_hex_code_must_be_a_six_digit_hex_color(): void
    {
        foreach (['red', '#FFF', '#GGGGGG', '7A1E2C'] as $invalidHexCode) {
            $this->actingAs($this->user)
                ->post(route('catalog.colors.store'), ['name' => 'Bad '.$invalidHexCode, 'hex_code' => $invalidHexCode])
                ->assertSessionHasErrors('hex_code');
        }

        $this->assertDatabaseCount('colors', 0);
    }

    public function test_color_names_must_be_unique_within_the_organization(): void
    {
        Color::factory()->for($this->organization)->create(['name' => 'Navy']);

        $this->actingAs($this->user)
            ->post(route('catalog.colors.store'), ['name' => 'Navy'])
            ->assertSessionHasErrors('name');
    }

    public function test_a_color_can_be_updated(): void
    {
        $color = Color::factory()->for($this->organization)->create(['name' => 'Navy']);

        $this->actingAs($this->user)
            ->put(route('catalog.colors.update', $color), [
                'name' => 'Navy',
                'hex_code' => '',
                'is_active' => false,
            ])
            ->assertSessionHasNoErrors();

        $color->refresh();
        $this->assertNull($color->hex_code);
        $this->assertFalse($color->is_active);
    }

    public function test_colors_of_another_organization_cannot_be_updated_or_deleted(): void
    {
        $foreignColor = Color::factory()->create();

        $this->actingAs($this->user)
            ->put(route('catalog.colors.update', $foreignColor), ['name' => 'Hijacked'])
            ->assertNotFound();

        $this->actingAs($this->user)
            ->delete(route('catalog.colors.destroy', $foreignColor))
            ->assertNotFound();

        $this->assertModelExists($foreignColor);
    }

    public function test_an_unused_color_can_be_deleted(): void
    {
        $color = Color::factory()->for($this->organization)->create();

        $this->actingAs($this->user)
            ->delete(route('catalog.colors.destroy', $color))
            ->assertRedirect();

        $this->assertModelMissing($color);
    }

    public function test_a_color_used_by_product_variants_cannot_be_deleted(): void
    {
        $color = Color::factory()->for($this->organization)->create();
        ProductVariant::factory()
            ->for(Product::factory()->for($this->organization))
            ->create(['color_id' => $color->id]);

        $this->actingAs($this->user)
            ->delete(route('catalog.colors.destroy', $color))
            ->assertRedirect();

        $this->assertModelExists($color);
    }
}
