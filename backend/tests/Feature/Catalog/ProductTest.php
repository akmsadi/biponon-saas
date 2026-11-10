<?php

namespace Tests\Feature\Catalog;

use App\Models\Color;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Size;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProductTest extends TestCase
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
        $this->get(route('catalog.products.index'))->assertRedirect(route('login'));
    }

    public function test_index_lists_only_the_current_organizations_products_with_counts(): void
    {
        $product = Product::factory()->for($this->organization)->create(['name' => 'Classic Tee']);
        $variant = ProductVariant::factory()->for($product)->create();
        ProductVariant::factory()->for($product)->create();
        StockBalance::factory()->for($variant)->create(['quantity' => 5]);
        StockBalance::factory()->for($variant)->create(['quantity' => 3]);
        Product::factory()->create(['name' => 'Other org product']);

        $this->actingAs($this->user)
            ->get(route('catalog.products.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('catalog/products/index')
                ->has('products', 1)
                ->where('products.0.name', 'Classic Tee')
                ->where('products.0.product_variants_count', 2)
                ->where('products.0.in_stock_count', 8));
    }

    public function test_creating_a_product_redirects_to_its_page(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('catalog.products.store'), [
                'name' => 'Classic Tee',
                'description' => 'Cotton crew neck',
                'is_active' => true,
            ]);

        $product = Product::sole();

        $response->assertRedirect(route('catalog.products.show', $product));
        $this->assertSame($this->organization->id, $product->organization_id);
        $this->assertSame('Classic Tee', $product->name);
        $this->assertTrue($product->is_active);
    }

    public function test_show_lists_variants_and_only_active_sizes_and_colors(): void
    {
        $product = Product::factory()->for($this->organization)->create();
        ProductVariant::factory()->for($product)->create(['sku' => 'TEE-M-RED']);
        Size::factory()->for($this->organization)->create(['name' => 'XL', 'is_active' => false]);
        Color::factory()->for($this->organization)->create(['name' => 'Beige', 'is_active' => false]);

        $this->actingAs($this->user)
            ->get(route('catalog.products.show', $product))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('catalog/products/show')
                ->where('product.id', $product->id)
                ->has('variants', 1)
                ->where('variants.0.sku', 'TEE-M-RED')
                ->has('sizes', 1)
                ->has('colors', 1));
    }

    public function test_a_product_can_be_updated(): void
    {
        $product = Product::factory()->for($this->organization)->create();

        $this->actingAs($this->user)
            ->put(route('catalog.products.update', $product), ['name' => 'Renamed', 'is_active' => false])
            ->assertSessionHasNoErrors();

        $product->refresh();
        $this->assertSame('Renamed', $product->name);
        $this->assertFalse($product->is_active);
    }

    public function test_products_of_another_organization_are_not_reachable(): void
    {
        $foreignProduct = Product::factory()->create();

        $this->actingAs($this->user)->get(route('catalog.products.show', $foreignProduct))->assertNotFound();
        $this->actingAs($this->user)->put(route('catalog.products.update', $foreignProduct), ['name' => 'Hijacked'])->assertNotFound();
        $this->actingAs($this->user)->delete(route('catalog.products.destroy', $foreignProduct))->assertNotFound();

        $this->assertModelExists($foreignProduct);
    }

    public function test_a_product_without_stock_history_is_deleted_with_its_variants(): void
    {
        $product = Product::factory()->for($this->organization)->create();
        $variant = ProductVariant::factory()->for($product)->create();

        $this->actingAs($this->user)
            ->delete(route('catalog.products.destroy', $product))
            ->assertRedirect(route('catalog.products.index'));

        $this->assertModelMissing($product);
        $this->assertModelMissing($variant);
    }

    public function test_a_product_with_stock_history_cannot_be_deleted(): void
    {
        $product = Product::factory()->for($this->organization)->create();
        $variant = ProductVariant::factory()->for($product)->create();
        StockMovement::factory()->for($variant)->create();

        $this->actingAs($this->user)
            ->delete(route('catalog.products.destroy', $product))
            ->assertRedirect();

        $this->assertModelExists($product);
        $this->assertModelExists($variant);
    }
}
