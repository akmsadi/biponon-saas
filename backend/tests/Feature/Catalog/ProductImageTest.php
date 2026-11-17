<?php

namespace Tests\Feature\Catalog;

use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProductImageTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private User $manager;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->organization = Organization::factory()->create();
        $this->manager = User::factory()->withOrganization($this->organization, 'manager')->create();
        $this->product = Product::factory()->for($this->organization)->create();
    }

    /**
     * A real 1×1 PNG (padded to the given size in KB), so it passes the image check without the GD extension.
     */
    private function photo(string $name, int $kilobytes = 0): UploadedFile
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');

        return UploadedFile::fake()->createWithContent($name, $png.str_repeat('x', $kilobytes * 1024));
    }

    /**
     * Upload photos to the product as the given user.
     *
     * @param  array<int, UploadedFile>  $files
     */
    private function upload(array $files, ?User $user = null, ?Product $product = null): TestResponse
    {
        return $this->actingAs($user ?? $this->manager)
            ->post(route('catalog.products.images.store', $product ?? $this->product), ['images' => $files]);
    }

    public function test_managers_upload_photos_that_are_stored_in_the_products_folder_in_order(): void
    {
        $this->upload([
            $this->photo('front.jpg'),
            $this->photo('back.png'),
        ])->assertSessionHasNoErrors();

        $images = $this->product->images()->get();

        $this->assertCount(2, $images);
        $this->assertSame([1, 2], $images->pluck('sort_order')->all());

        foreach ($images as $image) {
            $this->assertSame($this->organization->id, $image->organization_id);
            $this->assertStringStartsWith("products/{$this->organization->id}/{$this->product->id}/", $image->path);
            Storage::disk('public')->assertExists($image->path);
        }
    }

    public function test_the_product_page_lists_photos_main_first(): void
    {
        $second = ProductImage::factory()->for($this->product)->create(['sort_order' => 2]);
        $main = ProductImage::factory()->for($this->product)->create(['sort_order' => 1]);

        $this->actingAs($this->manager)
            ->get(route('catalog.products.show', $this->product))
            ->assertInertia(fn (Assert $page) => $page
                ->has('images', 2)
                ->where('images.0.id', $main->id)
                ->where('images.0.url', Storage::disk('public')->url($main->path))
                ->where('images.1.id', $second->id)
                ->where('maxImages', ProductImage::MAX_PER_PRODUCT));
    }

    public function test_only_images_up_to_4_mb_are_accepted(): void
    {
        $this->upload([UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf')])
            ->assertSessionHasErrors('images.0');
        $this->upload([$this->photo('huge.jpg', 5000)])
            ->assertSessionHasErrors('images.0');

        $this->assertSame(0, $this->product->images()->count());
    }

    public function test_a_product_holds_at_most_eight_photos(): void
    {
        ProductImage::factory()->for($this->product)->count(7)->create();

        $this->upload([$this->photo('a.jpg'), $this->photo('b.jpg')])
            ->assertSessionHasErrors('images');
        $this->assertSame(7, $this->product->images()->count());

        $this->upload([$this->photo('a.jpg')])->assertSessionHasNoErrors();
        $this->assertSame(8, $this->product->images()->count());
    }

    public function test_making_a_photo_main_moves_it_first_and_keeps_the_others_in_order(): void
    {
        $first = ProductImage::factory()->for($this->product)->create(['sort_order' => 1]);
        $second = ProductImage::factory()->for($this->product)->create(['sort_order' => 2]);
        $third = ProductImage::factory()->for($this->product)->create(['sort_order' => 3]);

        $this->actingAs($this->manager)
            ->post(route('catalog.images.main', $third))
            ->assertSessionHasNoErrors();

        $this->assertSame([$third->id, $first->id, $second->id], $this->product->images()->pluck('id')->all());
    }

    public function test_deleting_a_photo_removes_its_file(): void
    {
        $this->upload([$this->photo('front.jpg')]);
        $image = $this->product->images()->sole();

        $this->actingAs($this->manager)
            ->delete(route('catalog.images.destroy', $image))
            ->assertRedirect();

        $this->assertModelMissing($image);
        Storage::disk('public')->assertMissing($image->path);
    }

    public function test_deleting_a_product_removes_its_photos_and_folder(): void
    {
        $this->upload([$this->photo('front.jpg')]);
        $image = $this->product->images()->sole();

        $this->actingAs($this->manager)
            ->delete(route('catalog.products.destroy', $this->product))
            ->assertRedirect(route('catalog.products.index'));

        $this->assertModelMissing($image);
        Storage::disk('public')->assertMissing(ProductImage::folderFor($this->product));
    }

    public function test_members_cannot_change_photos(): void
    {
        $member = User::factory()->withOrganization($this->organization, 'member')->create();
        $image = ProductImage::factory()->for($this->product)->create();

        $this->upload([$this->photo('front.jpg')], $member)->assertForbidden();
        $this->actingAs($member)->post(route('catalog.images.main', $image))->assertForbidden();
        $this->actingAs($member)->delete(route('catalog.images.destroy', $image))->assertForbidden();

        $this->assertSame(1, $this->product->images()->count());
    }

    public function test_another_organizations_product_and_photos_are_not_found(): void
    {
        $foreignProduct = Product::factory()->create();
        $foreignImage = ProductImage::factory()->for($foreignProduct)->create();

        $this->upload([$this->photo('front.jpg')], product: $foreignProduct)->assertNotFound();
        $this->actingAs($this->manager)->post(route('catalog.images.main', $foreignImage))->assertNotFound();
        $this->actingAs($this->manager)->delete(route('catalog.images.destroy', $foreignImage))->assertNotFound();

        $this->assertSame(1, $foreignProduct->images()->count());
        $this->assertModelExists($foreignImage);
    }
}
