<?php

namespace Tests\Feature\Catalog;

use App\Models\Color;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Size;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ProductVariantTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private User $user;

    private Product $product;

    private Size $medium;

    private Size $large;

    private Color $red;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();
        $this->user = User::factory()->withOrganization($this->organization)->create();
        $this->product = Product::factory()->for($this->organization)->create();
        $this->medium = Size::factory()->for($this->organization)->create(['name' => 'M']);
        $this->large = Size::factory()->for($this->organization)->create(['name' => 'L']);
        $this->red = Color::factory()->for($this->organization)->create(['name' => 'Red']);
    }

    /**
     * @param  array<int, array<string, mixed>>  $variants
     * @param  array<string, mixed>  $extra
     */
    private function storeVariants(array $variants, array $extra = []): TestResponse
    {
        return $this->actingAs($this->user)
            ->post(route('catalog.products.variants.store', $this->product), ['variants' => $variants, ...$extra]);
    }

    public function test_a_batch_of_variants_is_created_with_a_shared_price(): void
    {
        $this->storeVariants([
            ['size_id' => $this->medium->id, 'color_id' => $this->red->id, 'sku' => 'tee-m-red', 'barcode' => ''],
            ['size_id' => $this->large->id, 'color_id' => $this->red->id, 'sku' => 'TEE-L-RED', 'barcode' => '1234567890123'],
        ], ['price' => '499.50'])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('product_variants', [
            'organization_id' => $this->organization->id,
            'product_id' => $this->product->id,
            'size_id' => $this->medium->id,
            'sku' => 'TEE-M-RED',
            'barcode' => null,
            'price' => '499.50',
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('product_variants', ['sku' => 'TEE-L-RED', 'barcode' => '1234567890123']);
    }

    public function test_skus_and_barcodes_must_be_unique_within_the_batch_and_organization(): void
    {
        ProductVariant::factory()->for($this->organization)->create(['sku' => 'TAKEN', 'barcode' => 'B-TAKEN']);

        $this->storeVariants([
            ['size_id' => $this->medium->id, 'color_id' => $this->red->id, 'sku' => 'taken', 'barcode' => 'B-TAKEN'],
        ])->assertSessionHasErrors(['variants.0.sku', 'variants.0.barcode']);

        $this->storeVariants([
            ['size_id' => $this->medium->id, 'color_id' => $this->red->id, 'sku' => 'SAME', 'barcode' => 'B1'],
            ['size_id' => $this->large->id, 'color_id' => $this->red->id, 'sku' => 'same', 'barcode' => 'B1'],
        ])->assertSessionHasErrors(['variants.0.sku', 'variants.1.sku', 'variants.0.barcode', 'variants.1.barcode']);

        $this->assertSame(0, $this->product->productVariants()->count());
    }

    public function test_the_same_sku_is_allowed_in_another_organization(): void
    {
        ProductVariant::factory()->create(['sku' => 'TEE-M-RED']);

        $this->storeVariants([
            ['size_id' => $this->medium->id, 'color_id' => $this->red->id, 'sku' => 'TEE-M-RED'],
        ])->assertSessionHasNoErrors();
    }

    public function test_an_existing_or_repeated_size_and_color_combination_is_rejected(): void
    {
        ProductVariant::factory()->for($this->product)->create(['size_id' => $this->medium->id, 'color_id' => $this->red->id]);

        $this->storeVariants([
            ['size_id' => $this->medium->id, 'color_id' => $this->red->id, 'sku' => 'A'],
            ['size_id' => $this->large->id, 'color_id' => $this->red->id, 'sku' => 'B'],
            ['size_id' => $this->large->id, 'color_id' => $this->red->id, 'sku' => 'C'],
        ])->assertSessionHasErrors(['variants.0.size_id', 'variants.2.size_id']);

        $this->assertSame(1, $this->product->productVariants()->count());
    }

    public function test_inactive_or_foreign_sizes_and_colors_are_rejected(): void
    {
        $inactiveSize = Size::factory()->for($this->organization)->create(['is_active' => false]);
        $foreignColor = Color::factory()->create();

        $this->storeVariants([
            ['size_id' => $inactiveSize->id, 'color_id' => $foreignColor->id, 'sku' => 'X'],
        ])->assertSessionHasErrors(['variants.0.size_id', 'variants.0.color_id']);
    }

    public function test_variants_cannot_be_added_to_another_organizations_product(): void
    {
        $this->product = Product::factory()->create();

        $this->storeVariants([
            ['size_id' => $this->medium->id, 'color_id' => $this->red->id, 'sku' => 'X'],
        ])->assertNotFound();
    }

    public function test_a_variant_can_be_updated_but_its_size_and_color_stay_fixed(): void
    {
        $variant = ProductVariant::factory()->for($this->product)->create(['size_id' => $this->medium->id, 'color_id' => $this->red->id]);

        $this->actingAs($this->user)
            ->put(route('catalog.variants.update', $variant), [
                'sku' => 'new-sku',
                'barcode' => '',
                'price' => '',
                'is_active' => false,
                'size_id' => $this->large->id,
            ])
            ->assertSessionHasNoErrors();

        $variant->refresh();
        $this->assertSame('NEW-SKU', $variant->sku);
        $this->assertNull($variant->barcode);
        $this->assertNull($variant->price);
        $this->assertFalse($variant->is_active);
        $this->assertSame($this->medium->id, $variant->size_id);
    }

    public function test_variants_of_another_organization_cannot_be_updated_or_deleted(): void
    {
        $foreignVariant = ProductVariant::factory()->create();

        $this->actingAs($this->user)->put(route('catalog.variants.update', $foreignVariant), ['sku' => 'HIJACKED'])->assertNotFound();
        $this->actingAs($this->user)->delete(route('catalog.variants.destroy', $foreignVariant))->assertNotFound();

        $this->assertModelExists($foreignVariant);
    }

    public function test_a_variant_without_stock_history_can_be_deleted(): void
    {
        $variant = ProductVariant::factory()->for($this->product)->create();

        $this->actingAs($this->user)->delete(route('catalog.variants.destroy', $variant))->assertRedirect();

        $this->assertModelMissing($variant);
    }

    public function test_a_variant_with_stock_history_cannot_be_deleted(): void
    {
        $variant = ProductVariant::factory()->for($this->product)->create();
        StockMovement::factory()->for($variant)->create();

        $this->actingAs($this->user)->delete(route('catalog.variants.destroy', $variant))->assertRedirect();

        $this->assertModelExists($variant);
    }
}
