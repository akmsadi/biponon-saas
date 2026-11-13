<?php

namespace Database\Seeders;

use App\Models\Color;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SerialNumber;
use App\Models\Size;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Seeds demo catalog, warehouse and stock data (quantities, with a few serialized units) for local development.
 *
 * Organizations that already have products are skipped, so the seeder is safe to re-run.
 */
class DemoInventorySeeder extends Seeder
{
    /**
     * @var list<array{name: string, code: string}>
     */
    private const SIZES = [
        ['name' => 'XS', 'code' => 'XS'],
        ['name' => 'S', 'code' => 'S'],
        ['name' => 'M', 'code' => 'M'],
        ['name' => 'L', 'code' => 'L'],
        ['name' => 'XL', 'code' => 'XL'],
        ['name' => 'XXL', 'code' => 'XXL'],
    ];

    /**
     * @var list<array{name: string, code: string, hex_code: string}>
     */
    private const COLORS = [
        ['name' => 'Black', 'code' => 'BLK', 'hex_code' => '#111111'],
        ['name' => 'White', 'code' => 'WHT', 'hex_code' => '#FAFAFA'],
        ['name' => 'Navy', 'code' => 'NVY', 'hex_code' => '#1F2A44'],
        ['name' => 'Maroon', 'code' => 'MRN', 'hex_code' => '#7A1E2C'],
        ['name' => 'Olive', 'code' => 'OLV', 'hex_code' => '#6B7A3A'],
        ['name' => 'Grey', 'code' => 'GRY', 'hex_code' => '#8A8D91'],
    ];

    /**
     * @var list<array{name: string, code: string, description: string, price: int}>
     */
    private const PRODUCTS = [
        ['name' => 'Classic Cotton T-Shirt', 'code' => 'TSH', 'description' => '100% combed cotton crew neck tee.', 'price' => 450],
        ['name' => 'Pique Polo Shirt', 'code' => 'POL', 'description' => 'Short sleeve pique polo with ribbed collar.', 'price' => 890],
        ['name' => 'Fleece Hoodie', 'code' => 'HOD', 'description' => 'Brushed fleece pullover hoodie.', 'price' => 1650],
        ['name' => 'Slim Fit Denim Jeans', 'code' => 'JNS', 'description' => 'Stretch denim, slim fit.', 'price' => 1850],
        ['name' => 'Cotton Chino Pants', 'code' => 'CHN', 'description' => 'Garment-washed cotton twill chinos.', 'price' => 1450],
        ['name' => 'Embroidered Panjabi', 'code' => 'PNJ', 'description' => 'Festive cotton panjabi with neckline embroidery.', 'price' => 2450],
        ['name' => 'Oxford Button-Down Shirt', 'code' => 'OXF', 'description' => 'Long sleeve oxford cotton shirt.', 'price' => 1290],
        ['name' => 'Track Joggers', 'code' => 'JOG', 'description' => 'Tapered joggers with elastic cuffs.', 'price' => 990],
    ];

    /**
     * Products whose units are received with individual serial numbers, to demo optional serial tracking.
     *
     * @var list<string>
     */
    private const SERIALIZED_PRODUCTS = ['Embroidered Panjabi'];

    /**
     * @var list<array{name: string, code: string, address_line: string, city: string}>
     */
    private const WAREHOUSES = [
        ['name' => 'Central Warehouse', 'code' => 'CWH', 'address_line' => 'Plot 12, Tejgaon I/A', 'city' => 'Dhaka'],
        ['name' => 'Chattogram Depot', 'code' => 'CTG', 'address_line' => 'Agrabad C/A', 'city' => 'Chattogram'],
        ['name' => 'Gulshan Outlet', 'code' => 'GUL', 'address_line' => 'Road 11, Gulshan 2', 'city' => 'Dhaka'],
    ];

    /**
     * @var list<array{name: string, contact_name: string, email: string, phone: string, address: string}>
     */
    private const SUPPLIERS = [
        ['name' => 'Rahman Textiles', 'contact_name' => 'Abdur Rahman', 'email' => 'sales@rahmantextiles.test', 'phone' => '+880 1711 000001', 'address' => 'Narayanganj, Dhaka'],
        ['name' => 'Gazipur Garments Ltd', 'contact_name' => 'Nasrin Akter', 'email' => 'orders@gazipurgarments.test', 'phone' => '+880 1711 000002', 'address' => 'Konabari, Gazipur'],
        ['name' => 'Chittagong Stitch House', 'contact_name' => 'Imran Hossain', 'email' => 'imran@stitchhouse.test', 'phone' => '+880 1811 000003', 'address' => 'Agrabad, Chattogram'],
    ];

    public function run(): void
    {
        $admin = User::where('email', 'akmsadi.cste@gmail.com')->firstOrFail();

        $biponon = Organization::where('slug', 'biponon')->firstOrFail();
        $acme = $this->createOrganization('Acme Traders', 'acme-traders', $admin);

        $this->attachMember($acme, $admin, 'admin');
        $this->attachMember($biponon, $this->createUser('Rahim Manager', 'manager@biponon.test'), 'manager');
        $this->attachMember($biponon, $this->createUser('Karim Staff', 'staff@biponon.test'), 'member');
        $this->attachMember($acme, $this->createUser('Acme Owner', 'owner@acme.test'), 'admin');

        $this->seedInventory($biponon, $admin, productCount: 8);
        $this->seedInventory($acme, $admin, productCount: 4);

        $this->seedSuppliers($biponon);
        $this->seedSuppliers($acme);
    }

    /**
     * Suppliers are seeded separately from inventory, so they also reach organizations seeded before suppliers existed.
     */
    private function seedSuppliers(Organization $organization): void
    {
        foreach (self::SUPPLIERS as $supplier) {
            Supplier::firstOrCreate(
                ['organization_id' => $organization->id, 'name' => $supplier['name']],
                [...$supplier, 'is_active' => true],
            );
        }
    }

    private function createOrganization(string $name, string $slug, User $creator): Organization
    {
        return Organization::firstOrCreate(
            ['slug' => $slug],
            ['name' => $name, 'status' => 'active', 'created_by' => $creator->id],
        );
    }

    private function createUser(string $name, string $email): User
    {
        $user = User::firstOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => Hash::make('password')],
        );

        $user->forceFill(['email_verified_at' => $user->email_verified_at ?? now()])->save();

        return $user;
    }

    private function attachMember(Organization $organization, User $user, string $role): void
    {
        $user->organizations()->syncWithoutDetaching([
            $organization->id => ['role' => $role, 'status' => 'active', 'joined_at' => now()],
        ]);
    }

    private function seedInventory(Organization $organization, User $performer, int $productCount): void
    {
        if ($organization->products()->exists()) {
            $this->command?->warn("Skipping {$organization->name}: products already exist.");

            return;
        }

        mt_srand($organization->id);

        DB::transaction(function () use ($organization, $performer, $productCount): void {
            $sizes = $this->seedSizes($organization);
            $colors = $this->seedColors($organization);
            $warehouses = $this->seedWarehouses($organization);
            $variants = $this->seedProducts($organization, $sizes, $colors, $productCount);

            $this->seedStockAndMovements($organization, $variants, $warehouses, $performer);
        });

        $this->command?->info(sprintf(
            '%s: %d products, %d variants, %d units in stock, %d serials, %d movements.',
            $organization->name,
            $organization->products()->count(),
            $organization->productVariants()->count(),
            $organization->stockBalances()->sum('quantity'),
            $organization->serialNumbers()->count(),
            $organization->stockMovements()->count(),
        ));
    }

    /**
     * @return Collection<int, Size>
     */
    private function seedSizes(Organization $organization): Collection
    {
        return collect(self::SIZES)->map(fn (array $size, int $index): Size => Size::firstOrCreate(
            ['organization_id' => $organization->id, 'name' => $size['name']],
            ['code' => $size['code'], 'sort_order' => $index + 1, 'is_active' => true],
        ));
    }

    /**
     * @return Collection<int, Color>
     */
    private function seedColors(Organization $organization): Collection
    {
        return collect(self::COLORS)->map(fn (array $color, int $index): Color => Color::firstOrCreate(
            ['organization_id' => $organization->id, 'name' => $color['name']],
            ['code' => $color['code'], 'hex_code' => $color['hex_code'], 'sort_order' => $index + 1, 'is_active' => true],
        ));
    }

    /**
     * @return Collection<int, Warehouse>
     */
    private function seedWarehouses(Organization $organization): Collection
    {
        return collect(self::WAREHOUSES)->map(fn (array $warehouse): Warehouse => Warehouse::firstOrCreate(
            ['organization_id' => $organization->id, 'code' => $warehouse['code']],
            [
                'name' => $warehouse['name'],
                'address_line' => $warehouse['address_line'],
                'city' => $warehouse['city'],
                'country_code' => 'BD',
                'is_active' => true,
            ],
        ));
    }

    /**
     * Creates products, each with a random subset of sizes and colors as variants.
     *
     * @param  Collection<int, Size>  $sizes
     * @param  Collection<int, Color>  $colors
     * @return Collection<int, ProductVariant>
     */
    private function seedProducts(Organization $organization, Collection $sizes, Collection $colors, int $productCount): Collection
    {
        $variants = collect();
        $barcodeSequence = 0;

        foreach (array_slice(self::PRODUCTS, 0, $productCount) as $productIndex => $definition) {
            $product = Product::create([
                'organization_id' => $organization->id,
                'name' => $definition['name'],
                'description' => $definition['description'],
                // One inactive product (Track Joggers) so "active only" filters have something to hide.
                'is_active' => $productIndex !== 7,
            ]);

            $productSizes = $sizes->slice(mt_rand(0, 1), mt_rand(3, 5));
            $productColors = $colors->shuffle(mt_rand())->take(mt_rand(2, 4));

            foreach ($productSizes as $size) {
                foreach ($productColors as $color) {
                    $variants->push(ProductVariant::create([
                        'organization_id' => $organization->id,
                        'product_id' => $product->id,
                        'size_id' => $size->id,
                        'color_id' => $color->id,
                        'sku' => "{$definition['code']}-{$size->code}-{$color->code}",
                        'barcode' => sprintf('2%03d%09d', $organization->id, ++$barcodeSequence),
                        'price' => $definition['price'] + (in_array($size->code, ['XL', 'XXL'], true) ? 50 : 0),
                        'is_active' => true,
                    ]));
                }
            }
        }

        return $variants;
    }

    /**
     * Gives each variant a realistic quantity history: an opening receipt, then sometimes a transfer,
     * a dispatch and a partial return. Variants of serialized products are received with one serial per unit.
     *
     * @param  Collection<int, ProductVariant>  $variants
     * @param  Collection<int, Warehouse>  $warehouses
     */
    private function seedStockAndMovements(Organization $organization, Collection $variants, Collection $warehouses, User $performer): void
    {
        $serialSequence = 0;

        foreach ($variants as $variant) {
            /** @var array<int, int> $quantities on-hand quantity keyed by warehouse id */
            $quantities = [];
            $occurredAt = now()->subDays(mt_rand(20, 90))->setTime(mt_rand(9, 18), mt_rand(0, 59));

            $move = function (string $type, ?Warehouse $from, ?Warehouse $to, int $quantity, ?string $notes = null) use ($organization, $variant, $performer, &$occurredAt, &$quantities): StockMovement {
                if ($from) {
                    $quantities[$from->id] -= $quantity;
                }

                if ($to) {
                    $quantities[$to->id] = ($quantities[$to->id] ?? 0) + $quantity;
                }

                return StockMovement::create([
                    'organization_id' => $organization->id,
                    'product_variant_id' => $variant->id,
                    'from_warehouse_id' => $from?->id,
                    'to_warehouse_id' => $to?->id,
                    'quantity' => $quantity,
                    'movement_type' => $type,
                    'performed_by' => $performer->id,
                    'notes' => $notes,
                    'occurred_at' => $occurredAt,
                ]);
            };

            $warehouse = $warehouses->random();

            if (in_array($variant->product->name, self::SERIALIZED_PRODUCTS, true)) {
                $quantity = mt_rand(2, 5);
                $receipt = $move('receive', null, $warehouse, $quantity, 'Opening stock (serialized)');

                for ($unit = 0; $unit < $quantity; $unit++) {
                    $receipt->serialNumbers()->attach(SerialNumber::create([
                        'organization_id' => $organization->id,
                        'product_variant_id' => $variant->id,
                        'current_warehouse_id' => $warehouse->id,
                        'serial_number' => sprintf('%s-%06d', $variant->sku, ++$serialSequence),
                        'status' => 'in_stock',
                        'received_at' => $occurredAt,
                    ]));
                }
            } else {
                $move('receive', null, $warehouse, mt_rand(10, 60), 'Opening stock');

                if (mt_rand(1, 100) <= 30) {
                    $occurredAt = $this->later($occurredAt);
                    $destination = $warehouses->reject(fn (Warehouse $candidate): bool => $candidate->is($warehouse))->random();
                    $move('transfer', $warehouse, $destination, mt_rand(1, intdiv($quantities[$warehouse->id], 2)));
                }

                if (mt_rand(1, 100) <= 40) {
                    $occurredAt = $this->later($occurredAt);
                    $dispatched = mt_rand(1, intdiv($quantities[$warehouse->id], 2));
                    $move('dispatch', $warehouse, null, $dispatched, 'Customer order');

                    if (mt_rand(1, 100) <= 25) {
                        $occurredAt = $this->later($occurredAt);
                        $move('return', null, $warehouse, mt_rand(1, $dispatched), 'Customer return');
                    }
                }
            }

            foreach ($quantities as $warehouseId => $quantity) {
                StockBalance::create([
                    'organization_id' => $organization->id,
                    'product_variant_id' => $variant->id,
                    'warehouse_id' => $warehouseId,
                    'quantity' => $quantity,
                ]);
            }
        }
    }

    private function later(CarbonInterface $after): CarbonInterface
    {
        return $after->toImmutable()->addDays(mt_rand(1, 6))->addHours(mt_rand(0, 6));
    }
}
