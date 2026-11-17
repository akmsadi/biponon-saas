<?php

namespace Tests\Feature\Shop;

use App\Models\Customer;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\SalesOrder;
use App\Models\StockBalance;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ShopApiTest extends TestCase
{
    use RefreshDatabase;

    private Organization $shop;

    private Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->shop = Organization::factory()->create(['name' => 'Demo Fashion', 'slug' => 'demo-fashion']);
        $this->warehouse = Warehouse::factory()->for($this->shop)->create();
        $this->shop->putSettings([
            'shop_enabled' => true,
            'default_warehouse_id' => $this->warehouse->id,
            'currency' => 'BDT',
            'phone' => '+880 1700 000000',
        ]);
        $this->shop->save();
    }

    /**
     * A product listed in the shop with one variant per given price, each holding the given stock.
     *
     * @param  array<int, string>  $prices
     */
    private function listedProduct(string $name, array $prices = ['1200.00'], int $stock = 5): Product
    {
        $product = Product::factory()->for($this->shop)->create(['name' => $name, 'show_in_shop' => true]);

        foreach ($prices as $price) {
            $variant = ProductVariant::factory()->for($product)->create(['price' => $price]);

            if ($stock > 0) {
                StockBalance::factory()->for($variant)->create(['warehouse_id' => $this->warehouse->id, 'quantity' => $stock]);
            }
        }

        return $product;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function placeOrder(array $overrides = []): TestResponse
    {
        return $this->postJson(route('api.v1.shops.orders.store', 'demo-fashion'), [
            'name' => 'Rahim Uddin',
            'phone' => '01712-345678',
            'email' => 'rahim@example.test',
            'address' => 'House 1, Road 2, Dhaka',
            'note' => 'Call before coming',
            ...$overrides,
        ]);
    }

    private function closeShop(): void
    {
        $this->shop->putSettings(['shop_enabled' => false]);
        $this->shop->save();
    }

    public function test_the_shop_front_shows_its_details_and_whether_it_is_open(): void
    {
        $this->getJson(route('api.v1.shops.show', 'demo-fashion'))
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=60, public')
            ->assertExactJson(['data' => [
                'slug' => 'demo-fashion',
                'name' => 'Demo Fashion',
                'open' => true,
                'currency' => 'BDT',
                'contact' => ['email' => null, 'phone' => '+880 1700 000000', 'address' => null],
            ]]);

        $this->warehouse->update(['is_active' => false]);

        $this->getJson(route('api.v1.shops.show', 'demo-fashion'))->assertJsonPath('data.open', false);
    }

    public function test_unknown_and_suspended_organizations_have_no_shop(): void
    {
        $this->getJson(route('api.v1.shops.show', 'nobody'))->assertNotFound();

        $this->shop->update(['status' => 'suspended']);

        $this->getJson(route('api.v1.shops.show', 'demo-fashion'))->assertNotFound();
    }

    public function test_a_closed_shop_lists_nothing(): void
    {
        $product = $this->listedProduct('Classic Tee');
        $this->closeShop();

        $this->getJson(route('api.v1.shops.products.index', 'demo-fashion'))->assertNotFound();
        $this->getJson(route('api.v1.shops.products.show', ['demo-fashion', $product->slug]))->assertNotFound();
    }

    public function test_the_product_grid_lists_only_shop_products_with_an_orderable_variant(): void
    {
        $tee = $this->listedProduct('Classic Tee', ['1500.00', '1200.00']);
        ProductImage::factory()->for($tee)->create(['path' => 'products/tee-back.jpg', 'sort_order' => 2]);
        ProductImage::factory()->for($tee)->create(['path' => 'products/tee-front.jpg', 'sort_order' => 1]);
        $this->listedProduct('Linen Kurta', stock: 0);

        Product::factory()->for($this->shop)->create(['name' => 'Not chosen for the shop']);
        $this->listedProduct('Inactive product')->update(['is_active' => false]);
        $unpriced = $this->listedProduct('Unpriced');
        $unpriced->productVariants()->update(['price' => null]);
        $inactiveVariants = $this->listedProduct('Inactive variants');
        $inactiveVariants->productVariants()->update(['is_active' => false]);
        Product::factory()->create(['name' => 'Other shop', 'show_in_shop' => true]);

        $this->getJson(route('api.v1.shops.products.index', 'demo-fashion'))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Classic Tee')
            ->assertJsonPath('data.0.slug', 'classic-tee')
            ->assertJsonPath('data.0.image', Storage::disk('public')->url('products/tee-front.jpg'))
            ->assertJsonPath('data.0.price', ['min' => '1200.00', 'max' => '1500.00'])
            ->assertJsonPath('data.0.in_stock', true)
            ->assertJsonPath('data.1.name', 'Linen Kurta')
            ->assertJsonPath('data.1.image', null)
            ->assertJsonPath('data.1.in_stock', false)
            ->assertJsonPath('meta.total', 2);
    }

    public function test_the_product_grid_searches_by_name(): void
    {
        $this->listedProduct('Classic Tee');
        $this->listedProduct('Linen Kurta');

        $this->getJson(route('api.v1.shops.products.index', ['demo-fashion', 'search' => 'kurt']))
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Linen Kurta');
    }

    public function test_stock_in_inactive_warehouses_does_not_count(): void
    {
        $product = $this->listedProduct('Classic Tee', stock: 0);
        $closedWarehouse = Warehouse::factory()->for($this->shop)->create(['is_active' => false]);
        StockBalance::factory()->for($product->productVariants->first())->create(['warehouse_id' => $closedWarehouse->id, 'quantity' => 9]);

        $this->getJson(route('api.v1.shops.products.index', 'demo-fashion'))->assertJsonPath('data.0.in_stock', false);
    }

    public function test_a_product_page_shows_photos_options_and_orderable_variants_without_counts(): void
    {
        $product = $this->listedProduct('Classic Tee', ['1200.00']);
        $variant = $product->productVariants->first();
        $soldOut = ProductVariant::factory()->for($product)->create(['price' => '1300.00']);
        ProductVariant::factory()->for($product)->create(['price' => null]);
        ProductImage::factory()->for($product)->count(2)->create();

        $response = $this->getJson(route('api.v1.shops.products.show', ['demo-fashion', 'classic-tee']))
            ->assertOk()
            ->assertJsonPath('data.name', 'Classic Tee')
            ->assertJsonCount(2, 'data.images')
            ->assertJsonCount(2, 'data.sizes')
            ->assertJsonCount(2, 'data.colors')
            ->assertJsonCount(2, 'data.variants');

        $variants = collect($response->json('data.variants'))->keyBy('id');

        $this->assertSame(['id', 'sku', 'size_id', 'color_id', 'price', 'in_stock'], array_keys($variants[$variant->id]));
        $this->assertTrue($variants[$variant->id]['in_stock']);
        $this->assertSame('1200.00', $variants[$variant->id]['price']);
        $this->assertFalse($variants[$soldOut->id]['in_stock']);
    }

    public function test_products_outside_the_shop_have_no_page(): void
    {
        Product::factory()->for($this->shop)->create(['name' => 'Hidden']);
        $foreign = Product::factory()->create(['name' => 'Foreign', 'show_in_shop' => true]);
        ProductVariant::factory()->for($foreign)->create();

        $this->getJson(route('api.v1.shops.products.show', ['demo-fashion', 'hidden']))->assertNotFound();
        $this->getJson(route('api.v1.shops.products.show', ['demo-fashion', $foreign->slug]))->assertNotFound();
    }

    public function test_an_order_becomes_a_draft_sales_order_from_the_default_warehouse_with_a_new_customer(): void
    {
        $tee = $this->listedProduct('Classic Tee', ['1200.00'])->productVariants->first();
        $kurta = $this->listedProduct('Linen Kurta', ['950.50'])->productVariants->first();

        $this->placeOrder(['lines' => [
            ['variant_id' => $tee->id, 'quantity' => 2],
            ['variant_id' => $kurta->id, 'quantity' => 1],
        ]])
            ->assertCreated()
            ->assertExactJson(['data' => [
                'number' => 'SO-0001',
                'total' => '3350.50',
                'currency' => 'BDT',
                'payment' => 'cash_on_delivery',
            ]]);

        $order = SalesOrder::sole();

        $this->assertSame('draft', $order->status);
        $this->assertSame($this->warehouse->id, $order->warehouse_id);
        $this->assertNull($order->created_by);
        $this->assertStringContainsString('House 1, Road 2, Dhaka', $order->notes);
        $this->assertStringContainsString('Call before coming', $order->notes);
        $this->assertSame([[$tee->id, 2, '1200.00'], [$kurta->id, 1, '950.50']], $order->lines()->orderBy('id')->get()
            ->map(fn ($line) => [$line->product_variant_id, $line->quantity_ordered, $line->unit_price])->all());

        $customer = $order->customer;
        $this->assertSame($this->shop->id, $customer->organization_id);
        $this->assertSame('Rahim Uddin', $customer->name);
        $this->assertSame('01712-345678', $customer->phone);
        $this->assertSame('House 1, Road 2, Dhaka', $customer->address);
    }

    public function test_a_returning_shopper_is_matched_by_phone_number(): void
    {
        $variant = $this->listedProduct('Classic Tee')->productVariants->first();
        $customer = Customer::factory()->for($this->shop)->create(['name' => 'Rahim', 'phone' => '+880 1712 345678']);

        $this->placeOrder(['lines' => [['variant_id' => $variant->id, 'quantity' => 1]]])->assertCreated();

        $this->assertSame($customer->id, SalesOrder::sole()->customer_id);
        $this->assertSame(1, $this->shop->customers()->count());
    }

    public function test_a_new_shopper_whose_name_is_taken_gets_their_phone_added(): void
    {
        $variant = $this->listedProduct('Classic Tee')->productVariants->first();
        Customer::factory()->for($this->shop)->create(['name' => 'Rahim Uddin', 'phone' => '01999 000000']);

        $this->placeOrder(['lines' => [['variant_id' => $variant->id, 'quantity' => 1]]])->assertCreated();

        $this->assertSame('Rahim Uddin (01712-345678)', SalesOrder::sole()->customer->name);
    }

    public function test_orders_cannot_exceed_stock_or_use_items_outside_the_shop(): void
    {
        $variant = $this->listedProduct('Classic Tee', stock: 3)->productVariants->first();
        $hidden = ProductVariant::factory()->for(Product::factory()->for($this->shop))->create();
        $foreign = ProductVariant::factory()->create();

        $this->placeOrder(['lines' => [
            ['variant_id' => $variant->id, 'quantity' => 4],
            ['variant_id' => $hidden->id, 'quantity' => 1],
            ['variant_id' => $foreign->id, 'quantity' => 1],
        ]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['lines.0.quantity', 'lines.1.variant_id', 'lines.2.variant_id']);

        $this->assertSame(0, SalesOrder::count());
    }

    public function test_a_closed_shop_takes_no_orders(): void
    {
        $variant = $this->listedProduct('Classic Tee')->productVariants->first();
        $this->closeShop();

        $this->placeOrder(['lines' => [['variant_id' => $variant->id, 'quantity' => 1]]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('shop');

        $this->assertSame(0, SalesOrder::count());
    }

    public function test_the_honeypot_and_missing_details_are_refused(): void
    {
        $variant = $this->listedProduct('Classic Tee')->productVariants->first();
        $lines = [['variant_id' => $variant->id, 'quantity' => 1]];

        $this->placeOrder(['lines' => $lines, 'website' => 'http://spam.test'])->assertJsonValidationErrors('website');
        $this->placeOrder(['lines' => $lines, 'phone' => 'call me'])->assertJsonValidationErrors('phone');
        $this->placeOrder(['lines' => $lines, 'phone' => '123'])->assertJsonValidationErrors('phone');
        $this->placeOrder(['lines' => [], 'address' => ''])->assertJsonValidationErrors(['lines', 'address']);

        $this->assertSame(0, SalesOrder::count());
    }

    public function test_one_phone_number_can_place_five_orders_an_hour(): void
    {
        $variant = $this->listedProduct('Classic Tee', stock: 50)->productVariants->first();
        $lines = [['variant_id' => $variant->id, 'quantity' => 1]];

        for ($order = 1; $order <= 5; $order++) {
            $this->placeOrder(['lines' => $lines])->assertCreated();
        }

        $this->placeOrder(['lines' => $lines])->assertTooManyRequests();
        $this->placeOrder(['lines' => $lines, 'phone' => '01812 000000'])->assertCreated();
    }
}
