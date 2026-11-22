<?php

namespace App\Actions\Demo;

use App\Actions\Inventory\RecordStockMovement;
use App\Models\Color;
use App\Models\Customer;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Models\SerialNumber;
use App\Models\Size;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Builds "Demo Fashion Ltd", the organization visitors explore with the shared demo accounts while the site is in
 * demo mode. It touches every feature: catalog, warehouses, stock with serials, plain operations, purchase and
 * sales orders in every status with receipts, dispatches and returns, members and an invitation, so every list,
 * chart and report has something to show. Its online shop is open with every active product listed, so the
 * Next.js shop has something to show after each nightly rebuild too.
 *
 * The data is a timeline over the last 150 days, run oldest first through the same RecordStockMovement action
 * the app uses, so balances, movements, orders and reports always agree. A fixed random seed makes every rebuild
 * the same, with dates relative to the moment it runs.
 */
class BuildDemoCompany
{
    public const SLUG = 'demo-fashion';

    public const NAME = 'Demo Fashion Ltd';

    /**
     * Shown on the login page in demo mode, so it is public by design.
     */
    public const PASSWORD = 'demo-password';

    /**
     * The accounts offered on the login page, keyed by role.
     *
     * @var array<string, array{label: string, name: string, email: string}>
     */
    public const ACCOUNTS = [
        'admin' => ['label' => 'Admin', 'name' => 'Ayesha Rahman', 'email' => 'admin@demo-fashion.test'],
        'manager' => ['label' => 'Manager', 'name' => 'Tanvir Hasan', 'email' => 'manager@demo-fashion.test'],
        'member' => ['label' => 'Staff', 'name' => 'Nusrat Jahan', 'email' => 'staff@demo-fashion.test'],
    ];

    /**
     * A former employee, shown as a suspended member. Not offered on the login page.
     */
    private const SUSPENDED_ACCOUNT = ['name' => 'Rafiq Islam', 'email' => 'rafiq@demo-fashion.test'];

    private const RANDOM_SEED = 20261116;

    /**
     * @var list<array{name: string, code: string, is_active: bool}>
     */
    private const SIZES = [
        ['name' => 'S', 'code' => 'S', 'is_active' => true],
        ['name' => 'M', 'code' => 'M', 'is_active' => true],
        ['name' => 'L', 'code' => 'L', 'is_active' => true],
        ['name' => 'XL', 'code' => 'XL', 'is_active' => true],
        ['name' => 'One size', 'code' => 'OS', 'is_active' => true],
        ['name' => 'XXXL', 'code' => 'XXXL', 'is_active' => false],
    ];

    /**
     * @var list<array{name: string, code: string, hex_code: string, is_active: bool}>
     */
    private const COLORS = [
        ['name' => 'Black', 'code' => 'BLK', 'hex_code' => '#111111', 'is_active' => true],
        ['name' => 'White', 'code' => 'WHT', 'hex_code' => '#FAFAFA', 'is_active' => true],
        ['name' => 'Navy', 'code' => 'NVY', 'hex_code' => '#1F2A44', 'is_active' => true],
        ['name' => 'Maroon', 'code' => 'MRN', 'hex_code' => '#7A1E2C', 'is_active' => true],
        ['name' => 'Olive', 'code' => 'OLV', 'hex_code' => '#6B7A3A', 'is_active' => true],
        ['name' => 'Grey', 'code' => 'GRY', 'hex_code' => '#8A8D91', 'is_active' => true],
        ['name' => 'Mustard', 'code' => 'MST', 'hex_code' => '#C9A227', 'is_active' => false],
    ];

    /**
     * Track Joggers is inactive, Linen Kurta never sells (slow-moving) and Winter Shawl has no stock history.
     *
     * @var list<array{name: string, code: string, description: string, price: int, sizes: list<string>, colors: list<string>, is_active: bool}>
     */
    private const PRODUCTS = [
        ['name' => 'Classic Cotton T-Shirt', 'code' => 'TSH', 'description' => '100% combed cotton crew neck tee.', 'price' => 450, 'sizes' => ['S', 'M', 'L', 'XL'], 'colors' => ['BLK', 'WHT', 'NVY'], 'is_active' => true],
        ['name' => 'Pique Polo Shirt', 'code' => 'POL', 'description' => 'Short sleeve pique polo with ribbed collar.', 'price' => 890, 'sizes' => ['M', 'L', 'XL'], 'colors' => ['NVY', 'MRN', 'WHT'], 'is_active' => true],
        ['name' => 'Fleece Hoodie', 'code' => 'HOD', 'description' => 'Brushed fleece pullover hoodie.', 'price' => 1650, 'sizes' => ['M', 'L', 'XL'], 'colors' => ['BLK', 'GRY'], 'is_active' => true],
        ['name' => 'Slim Fit Denim Jeans', 'code' => 'JNS', 'description' => 'Stretch denim, slim fit.', 'price' => 1850, 'sizes' => ['S', 'M', 'L'], 'colors' => ['NVY', 'BLK'], 'is_active' => true],
        ['name' => 'Cotton Chino Pants', 'code' => 'CHN', 'description' => 'Garment-washed cotton twill chinos.', 'price' => 1450, 'sizes' => ['M', 'L', 'XL'], 'colors' => ['OLV', 'GRY'], 'is_active' => true],
        ['name' => 'Oxford Button-Down Shirt', 'code' => 'OXF', 'description' => 'Long sleeve oxford cotton shirt.', 'price' => 1290, 'sizes' => ['M', 'L'], 'colors' => ['WHT', 'NVY'], 'is_active' => true],
        ['name' => 'Embroidered Panjabi', 'code' => 'PNJ', 'description' => 'Festive cotton panjabi with neckline embroidery. Every unit carries a serial number.', 'price' => 2450, 'sizes' => ['M', 'L'], 'colors' => ['WHT', 'MRN'], 'is_active' => true],
        ['name' => 'Linen Kurta', 'code' => 'KRT', 'description' => 'Breathable linen kurta.', 'price' => 1750, 'sizes' => ['M', 'L'], 'colors' => ['WHT', 'OLV'], 'is_active' => true],
        ['name' => 'Winter Shawl', 'code' => 'SHL', 'description' => 'Hand-woven wool shawl, arriving next season.', 'price' => 2200, 'sizes' => ['OS'], 'colors' => ['MRN', 'GRY'], 'is_active' => true],
        ['name' => 'Track Joggers', 'code' => 'JOG', 'description' => 'Tapered joggers with elastic cuffs. Discontinued.', 'price' => 990, 'sizes' => ['M', 'L'], 'colors' => ['BLK', 'GRY'], 'is_active' => false],
    ];

    /**
     * Products sold in the everyday sales orders.
     *
     * @var list<string>
     */
    private const EVERYDAY_PRODUCTS = ['TSH', 'POL', 'HOD', 'JNS', 'CHN', 'OXF', 'JOG'];

    /**
     * @var list<array{name: string, code: string, address_line: string, city: string, is_active: bool}>
     */
    private const WAREHOUSES = [
        ['name' => 'Central Warehouse', 'code' => 'CWH', 'address_line' => 'Plot 12, Tejgaon I/A', 'city' => 'Dhaka', 'is_active' => true],
        ['name' => 'Chattogram Depot', 'code' => 'CTG', 'address_line' => 'Agrabad C/A', 'city' => 'Chattogram', 'is_active' => true],
        ['name' => 'Gulshan Outlet', 'code' => 'GUL', 'address_line' => 'Road 11, Gulshan 2', 'city' => 'Dhaka', 'is_active' => true],
        ['name' => 'Sylhet Pop-up Store', 'code' => 'SYL', 'address_line' => 'Zindabazar', 'city' => 'Sylhet', 'is_active' => false],
    ];

    /**
     * @var list<array{name: string, contact_name: string, email: string, phone: string, address: string, is_active: bool}>
     */
    private const SUPPLIERS = [
        ['name' => 'Rahman Textiles', 'contact_name' => 'Abdur Rahman', 'email' => 'sales@rahmantextiles.test', 'phone' => '+880 1711 000001', 'address' => 'Narayanganj, Dhaka', 'is_active' => true],
        ['name' => 'Gazipur Garments Ltd', 'contact_name' => 'Nasrin Akter', 'email' => 'orders@gazipurgarments.test', 'phone' => '+880 1711 000002', 'address' => 'Konabari, Gazipur', 'is_active' => true],
        ['name' => 'Chittagong Stitch House', 'contact_name' => 'Imran Hossain', 'email' => 'imran@stitchhouse.test', 'phone' => '+880 1811 000003', 'address' => 'Agrabad, Chattogram', 'is_active' => true],
        ['name' => 'Old Loom Traders', 'contact_name' => 'Jamal Mia', 'email' => 'jamal@oldloom.test', 'phone' => '+880 1811 000004', 'address' => 'Tangail', 'is_active' => false],
    ];

    /**
     * @var list<array{name: string, contact_name: ?string, email: ?string, phone: ?string, address: ?string, is_active: bool}>
     */
    private const CUSTOMERS = [
        ['name' => 'Karim Fashion House', 'contact_name' => 'Karim Uddin', 'email' => 'karim@fashionhouse.test', 'phone' => '+880 1911 000011', 'address' => 'New Market, Dhaka', 'is_active' => true],
        ['name' => 'Sylhet Style Corner', 'contact_name' => 'Farhana Begum', 'email' => 'orders@stylecorner.test', 'phone' => '+880 1911 000012', 'address' => 'Zindabazar, Sylhet', 'is_active' => true],
        ['name' => 'Dhaka Boutique Co', 'contact_name' => 'Sabrina Haque', 'email' => 'buying@dhakaboutique.test', 'phone' => '+880 1911 000013', 'address' => 'Banani, Dhaka', 'is_active' => true],
        ['name' => 'Corporate Uniforms BD', 'contact_name' => 'Mahmud Hasan', 'email' => 'mahmud@uniformsbd.test', 'phone' => '+880 1911 000014', 'address' => 'Motijheel, Dhaka', 'is_active' => true],
        ['name' => 'Online Shop Walk-ins', 'contact_name' => null, 'email' => null, 'phone' => null, 'address' => null, 'is_active' => true],
        ['name' => 'Old Mall Kiosk', 'contact_name' => 'Babul Ahmed', 'email' => null, 'phone' => '+880 1911 000016', 'address' => 'Mirpur, Dhaka', 'is_active' => false],
    ];

    /**
     * Days ago on which an everyday sales order is confirmed; each is dispatched about a day later.
     *
     * @var list<int>
     */
    private const EVERYDAY_SALE_DAYS = [56, 53, 51, 48, 45, 43, 41, 37, 34, 32, 29, 27, 24, 22, 19, 16, 14, 11, 9, 7, 6, 4, 3, 2, 1];

    private Organization $organization;

    /** @var array<string, User> keyed by role */
    private array $users = [];

    /** @var array<string, Warehouse> keyed by code */
    private array $warehouses = [];

    /** @var array<string, ProductVariant> keyed by SKU */
    private array $variants = [];

    /** @var array<string, Supplier> keyed by name */
    private array $suppliers = [];

    /** @var array<string, Customer> keyed by name */
    private array $customers = [];

    /** @var array<string, PurchaseOrder> keyed by a timeline label */
    private array $purchaseOrders = [];

    /** @var array<string, SalesOrder> keyed by a timeline label */
    private array $salesOrders = [];

    /** @var list<array{daysAgo: int, hour: int, run: Closure(CarbonImmutable): void}> */
    private array $timeline = [];

    private CarbonImmutable $now;

    public function __construct(private RecordStockMovement $recordStockMovement) {}

    /**
     * The accounts to offer on the login page: only those that exist, so a site without the demo company
     * offers none.
     *
     * @return list<array{role: string, label: string, email: string, password: string}>
     */
    public static function loginAccounts(): array
    {
        $existing = User::query()
            ->where('is_demo', true)
            ->whereIn('email', array_column(self::ACCOUNTS, 'email'))
            ->pluck('email')
            ->all();

        $accounts = [];

        foreach (self::ACCOUNTS as $role => $account) {
            if (in_array($account['email'], $existing, true)) {
                $accounts[] = ['role' => $role, 'label' => $account['label'], 'email' => $account['email'], 'password' => self::PASSWORD];
            }
        }

        return $accounts;
    }

    public static function organization(): ?Organization
    {
        return Organization::firstWhere('slug', self::SLUG);
    }

    /**
     * Deletes the demo organization and everything in it. The demo accounts are kept (they are reset on the
     * next build), so their sessions survive a rebuild.
     */
    public function delete(): void
    {
        $organization = self::organization();

        if ($organization === null) {
            return;
        }

        DB::transaction(function () use ($organization): void {
            // Children first: most links between an organization's records refuse deletion while referenced.
            $organization->stockMovements()->delete();
            $organization->serialNumbers()->delete();
            $organization->stockBalances()->delete();
            $organization->salesOrders()->delete();
            $organization->purchaseOrders()->delete();
            $organization->productVariants()->delete();
            $organization->products()->delete();
            $organization->sizes()->delete();
            $organization->colors()->delete();
            $organization->warehouses()->delete();
            $organization->customers()->delete();
            $organization->suppliers()->delete();
            $organization->invitations()->delete();
            $organization->delete();
        });

        // Product photo rows go with their products; their files live in one folder per organization.
        Storage::disk(ProductImage::DISK)->deleteDirectory("products/{$organization->id}");
    }

    /**
     * Builds the demo organization. Call delete() first when it already exists.
     */
    public function handle(): Organization
    {
        $previousSeed = mt_rand();
        mt_srand(self::RANDOM_SEED);
        $this->now = CarbonImmutable::now();

        try {
            return DB::transaction(function (): Organization {
                $this->createOrganizationAndPeople();
                $this->createCatalog();
                $this->planTimeline();
                $this->runTimeline();
                $this->setReorderLevels();

                return $this->organization;
            });
        } finally {
            mt_srand($previousSeed);
        }
    }

    private function createOrganizationAndPeople(): void
    {
        $admin = $this->demoUser(self::ACCOUNTS['admin']['name'], self::ACCOUNTS['admin']['email'], self::PASSWORD);

        $this->organization = Organization::create([
            'name' => self::NAME,
            'slug' => self::SLUG,
            'status' => 'active',
            'created_by' => $admin->id,
        ]);

        foreach (self::ACCOUNTS as $role => $account) {
            $this->users[$role] = $role === 'admin' ? $admin : $this->demoUser($account['name'], $account['email'], self::PASSWORD);
            $this->organization->users()->attach($this->users[$role]->id, [
                'role' => $role,
                'status' => 'active',
                'joined_at' => $this->at(120, 10),
            ]);
        }

        $suspended = $this->demoUser(self::SUSPENDED_ACCOUNT['name'], self::SUSPENDED_ACCOUNT['email'], Str::password(32));
        $this->organization->users()->attach($suspended->id, ['role' => 'member', 'status' => 'suspended', 'joined_at' => $this->at(110, 10)]);

        $invitation = new OrganizationInvitation(['organization_id' => $this->organization->id, 'email' => 'new.hire@demo-fashion.test', 'role' => 'member']);
        $invitation->refreshToken();
        $invitation->invited_by = $admin->id;
        $invitation->save();

        foreach (self::SUPPLIERS as $supplier) {
            $this->suppliers[$supplier['name']] = $this->organization->suppliers()->create($supplier);
        }

        foreach (self::CUSTOMERS as $customer) {
            $this->customers[$customer['name']] = $this->organization->customers()->create($customer);
        }
    }

    /**
     * Creates or resets a demo account: name, password and verified email restored; two-factor and passkeys removed.
     */
    private function demoUser(string $name, string $email, string $password): User
    {
        $user = User::firstOrNew(['email' => $email]);
        $user->fill(['name' => $name, 'password' => $password]);
        $user->forceFill([
            'is_demo' => true,
            'is_super_admin' => false,
            'email_verified_at' => $user->email_verified_at ?? $this->now,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        $user->passkeys()->delete();

        return $user;
    }

    private function createCatalog(): void
    {
        $sizes = [];
        foreach (self::SIZES as $index => $size) {
            $sizes[$size['code']] = Size::create([...$size, 'organization_id' => $this->organization->id, 'sort_order' => $index + 1]);
        }

        $colors = [];
        foreach (self::COLORS as $index => $color) {
            $colors[$color['code']] = Color::create([...$color, 'organization_id' => $this->organization->id, 'sort_order' => $index + 1]);
        }

        foreach (self::WAREHOUSES as $warehouse) {
            $this->warehouses[$warehouse['code']] = Warehouse::create([...$warehouse, 'organization_id' => $this->organization->id, 'country_code' => 'BD']);
        }

        $this->organization->putSettings([
            'contact_email' => 'hello@demo-fashion.test',
            'phone' => '+880 1700 000000',
            'address' => "Plot 12, Tejgaon I/A\nDhaka 1208, Bangladesh",
            'timezone' => 'Asia/Dhaka',
            'currency' => 'BDT',
            'default_warehouse_id' => $this->warehouses['CWH']->id,
            'default_reorder_level' => 5,
            'shop_enabled' => true,
        ]);
        $this->organization->save();

        $barcode = 0;

        foreach (self::PRODUCTS as $definition) {
            $product = Product::create([
                'organization_id' => $this->organization->id,
                'name' => $definition['name'],
                'description' => $definition['description'],
                'is_active' => $definition['is_active'],
                'show_in_shop' => $definition['is_active'],
            ]);

            foreach ($definition['sizes'] as $sizeCode) {
                foreach ($definition['colors'] as $colorCode) {
                    $sku = "{$definition['code']}-{$sizeCode}-{$colorCode}";

                    $this->variants[$sku] = ProductVariant::create([
                        'organization_id' => $this->organization->id,
                        'product_id' => $product->id,
                        'size_id' => $sizes[$sizeCode]->id,
                        'color_id' => $colors[$colorCode]->id,
                        'sku' => $sku,
                        'barcode' => sprintf('2990%09d', ++$barcode),
                        'price' => $definition['price'] + ($sizeCode === 'XL' ? 50 : 0),
                        'is_active' => true,
                    ]);
                }
            }
        }
    }

    /**
     * Every event of the demo history. Events are run oldest first, so order numbers follow their dates and stock
     * is always there before it leaves.
     */
    private function planTimeline(): void
    {
        // Opening stock without a cost, never sold: shows on the slow-moving report and as "no cost" in stock value.
        $this->on(150, 10, fn (CarbonImmutable $at) => $this->receivePlain('KRT', 'CWH', 6, $at, 'Opening stock from the old system'));

        // Purchase orders in every status.
        $this->on(88, 10, fn (CarbonImmutable $at) => $this->placePurchaseOrder('spring', 'Rahman Textiles', 'CWH', ['TSH' => 50, 'POL' => 35, 'JOG' => 25], $at, notes: 'Spring restock.'));
        $this->on(84, 14, fn (CarbonImmutable $at) => $this->receivePurchaseOrder('spring', $at));
        $this->on(80, 11, fn (CarbonImmutable $at) => $this->placePurchaseOrder('winter', 'Gazipur Garments Ltd', 'CWH', ['HOD' => 30, 'JNS' => 40, 'CHN' => 30], $at));
        $this->on(75, 15, fn (CarbonImmutable $at) => $this->receivePurchaseOrder('winter', $at, splitTo: ['JNS' => 'CTG']));
        $this->on(65, 12, fn (CarbonImmutable $at) => $this->placePurchaseOrder('oxford', 'Chittagong Stitch House', 'CTG', ['OXF' => 30], $at, notes: 'Supplier warned of a cotton shortage.'));
        $this->on(58, 16, fn (CarbonImmutable $at) => $this->receivePurchaseOrder('oxford', $at, share: 0.6));
        $this->on(30, 11, fn (CarbonImmutable $at) => $this->closePurchaseOrder('oxford', $at));
        $this->on(25, 10, fn (CarbonImmutable $at) => $this->placePurchaseOrder('eid', 'Rahman Textiles', 'GUL', ['PNJ' => 6], $at, notes: 'Eid collection, serial numbers on every unit.'));
        $this->on(20, 13, fn (CarbonImmutable $at) => $this->receivePurchaseOrder('eid', $at, share: 0.5, withSerials: true));
        $this->on(9, 10, fn (CarbonImmutable $at) => $this->placePurchaseOrder('cancelled', 'Gazipur Garments Ltd', 'CWH', ['HOD' => 10], $at));
        $this->on(8, 12, fn (CarbonImmutable $at) => $this->cancelPurchaseOrder('cancelled', $at));
        $this->on(7, 11, fn (CarbonImmutable $at) => $this->placePurchaseOrder('reorder', 'Chittagong Stitch House', 'CWH', ['OXF' => 20, 'CHN' => 15], $at));
        $this->on(2, 15, fn (CarbonImmutable $at) => $this->placePurchaseOrder('draft', 'Rahman Textiles', 'GUL', ['TSH' => 20], $at, status: 'draft', notes: 'Waiting for the summer price list.'));

        // Plain operations.
        $this->on(82, 11, fn (CarbonImmutable $at) => $this->transferProduct('TSH', 'CWH', 'GUL', 12, $at));
        $this->on(82, 12, fn (CarbonImmutable $at) => $this->transferProduct('POL', 'CWH', 'GUL', 8, $at));
        $this->on(81, 10, fn (CarbonImmutable $at) => $this->transferProduct('TSH', 'CWH', 'CTG', 8, $at));
        $this->on(73, 11, fn (CarbonImmutable $at) => $this->transferProduct('HOD', 'CWH', 'GUL', 8, $at));
        $this->on(73, 12, fn (CarbonImmutable $at) => $this->transferProduct('JNS', 'CWH', 'GUL', 5, $at));
        $this->on(50, 14, fn (CarbonImmutable $at) => $this->receivePlain('CHN', 'GUL', 4, $at, 'Samples sent by the supplier'));
        $this->on(5, 16, fn (CarbonImmutable $at) => $this->transferAll('TSH-S-WHT', 'GUL', 'CWH', $at, 'Slow seller at the outlet, moved back to the warehouse'));
        $this->on(3, 17, fn (CarbonImmutable $at) => $this->move('dispatch', 'TSH-M-BLK', 2, 'GUL', null, $at, 'Walk-in sale'));
        $this->on(3, 17, fn (CarbonImmutable $at) => $this->move('dispatch', 'POL-M-NVY', 1, 'GUL', null, $at, 'Walk-in sale'));
        $this->on(1, 12, fn (CarbonImmutable $at) => $this->move('return', 'TSH-M-BLK', 1, null, 'GUL', $at, 'Walk-in return, wrong size'));
        $this->on(1, 14, fn (CarbonImmutable $at) => $this->move('transfer', 'JNS-M-NVY', 3, 'CTG', 'GUL', $at));

        // Everyday sales, dispatched in full about a day after they are confirmed.
        $customers = ['Karim Fashion House', 'Sylhet Style Corner', 'Dhaka Boutique Co', 'Online Shop Walk-ins', 'Corporate Uniforms BD'];

        foreach (self::EVERYDAY_SALE_DAYS as $index => $daysAgo) {
            $label = "everyday-{$index}";
            $customer = $customers[$index % count($customers)];
            $shipFrom = ['CWH', 'GUL', 'CWH', 'CTG'][$index % 4];

            $this->on($daysAgo, 10 + $index % 6, fn (CarbonImmutable $at) => $this->sellEveryday($label, $customer, $shipFrom, $at));
            $this->on(max(0, $daysAgo - 1), 16, fn (CarbonImmutable $at) => $this->dispatchSalesOrder($label, $at));
        }

        // Sales orders in the other statuses.
        $this->on(40, 11, fn (CarbonImmutable $at) => $this->createSalesOrder('returned', 'Karim Fashion House', 'CWH', ['TSH-M-BLK' => 6, 'POL-L-NVY' => 4], $at));
        $this->on(39, 15, fn (CarbonImmutable $at) => $this->dispatchSalesOrder('returned', $at));
        $this->on(35, 12, fn (CarbonImmutable $at) => $this->returnSalesOrder('returned', 'TSH-M-BLK', 2, 'CWH', $at, 'Customer ordered the wrong size'));

        $this->on(18, 10, fn (CarbonImmutable $at) => $this->createSalesOrder('closed', 'Sylhet Style Corner', 'CTG', ['JNS-M-NVY' => 8, 'OXF-L-WHT' => 6], $at));
        $this->on(17, 14, fn (CarbonImmutable $at) => $this->dispatchSalesOrder('closed', $at, share: 0.5));
        $this->on(10, 11, fn (CarbonImmutable $at) => $this->closeSalesOrder('closed', $at));

        $this->on(15, 12, fn (CarbonImmutable $at) => $this->createSalesOrder('serials', 'Dhaka Boutique Co', 'GUL', ['PNJ-M-WHT' => 2, 'PNJ-L-MRN' => 1], $at, notes: 'Gift wrap, Eid delivery.'));
        $this->on(14, 15, fn (CarbonImmutable $at) => $this->dispatchSalesOrder('serials', $at));
        $this->on(12, 13, fn (CarbonImmutable $at) => $this->returnSalesOrder('serials', 'PNJ-M-WHT', 1, 'GUL', $at, 'Stitching fault, returned for repair'));

        $this->on(12, 10, fn (CarbonImmutable $at) => $this->createSalesOrder('partial', 'Online Shop Walk-ins', 'GUL', ['HOD-L-BLK' => 6, 'POL-M-WHT' => 4], $at));
        $this->on(11, 16, fn (CarbonImmutable $at) => $this->dispatchSalesOrder('partial', $at, share: 0.5));

        $this->on(6, 11, fn (CarbonImmutable $at) => $this->createSalesOrder('cancelled', 'Sylhet Style Corner', 'CWH', ['CHN-M-OLV' => 5], $at));
        $this->on(5, 10, fn (CarbonImmutable $at) => $this->cancelSalesOrder('cancelled', $at));

        $this->on(4, 12, fn (CarbonImmutable $at) => $this->createSalesOrder('backorder', 'Corporate Uniforms BD', 'CWH', ['HOD-XL-GRY' => 40, 'TSH-L-WHT' => 10], $at, notes: 'Staff uniforms. Ship together once the hoodies arrive.'));

        $this->on(0, 9, fn (CarbonImmutable $at) => $this->createSalesOrder('draft', 'Karim Fashion House', 'GUL', ['OXF-M-NVY' => 3, 'JNS-S-BLK' => 2], $at, status: 'draft'));
    }

    /**
     * @param  Closure(CarbonImmutable): void  $run
     */
    private function on(int $daysAgo, int $hour, Closure $run): void
    {
        $this->timeline[] = ['daysAgo' => $daysAgo, 'hour' => $hour, 'run' => $run];
    }

    private function runTimeline(): void
    {
        $events = $this->timeline;

        // Stable sort: oldest day first, then by hour, keeping the planned order for events at the same time.
        usort($events, fn (array $a, array $b): int => [$b['daysAgo'], $a['hour']] <=> [$a['daysAgo'], $b['hour']]);

        foreach ($events as $index => $event) {
            ($event['run'])($this->at($event['daysAgo'], $event['hour'], $index % 50));
        }
    }

    /**
     * A moment in the organization's timezone that many days ago, never later than now.
     */
    private function at(int $daysAgo, int $hour, int $minute = 0): CarbonImmutable
    {
        $at = $this->now->setTimezone('Asia/Dhaka')->subDays($daysAgo)->setTime($hour, $minute)->utc();

        return $at->isAfter($this->now) ? $this->now->subMinutes(5) : $at;
    }

    /**
     * Sets reorder levels once the history is in place: hoodies at a level most of their rows are below, so the
     * dashboard and stock overview show low stock.
     */
    private function setReorderLevels(): void
    {
        foreach ($this->variants as $sku => $variant) {
            $level = match (Str::before($sku, '-')) {
                'HOD' => 15,
                'SHL' => null,
                default => 5,
            };

            $variant->update(['reorder_level' => $level]);
        }
    }

    private function move(string $type, string $sku, int $quantity, ?string $from, ?string $to, CarbonImmutable $at, ?string $notes = null, ?PurchaseOrderLine $purchaseOrderLine = null, ?SalesOrderLine $salesOrderLine = null): StockMovement
    {
        return $this->recordStockMovement->handle(
            variant: $this->variants[$sku],
            movementType: $type,
            quantity: $quantity,
            fromWarehouse: $from ? $this->warehouses[$from] : null,
            toWarehouse: $to ? $this->warehouses[$to] : null,
            performedBy: $type === 'receive' ? $this->users['manager'] : $this->users['member'],
            occurredAt: $at,
            notes: $notes,
            purchaseOrderLine: $purchaseOrderLine,
            salesOrderLine: $salesOrderLine,
        );
    }

    private function available(string $sku, string $warehouse): int
    {
        return (int) StockBalance::query()
            ->where('product_variant_id', $this->variants[$sku]->id)
            ->where('warehouse_id', $this->warehouses[$warehouse]->id)
            ->value('quantity');
    }

    /**
     * @return list<string> SKUs of the product's variants
     */
    private function skusOf(string $productCode): array
    {
        return array_values(array_filter(array_keys($this->variants), fn (string $sku): bool => str_starts_with($sku, "{$productCode}-")));
    }

    private function receivePlain(string $productCode, string $warehouse, int $quantityEach, CarbonImmutable $at, string $notes): void
    {
        foreach ($this->skusOf($productCode) as $sku) {
            $this->move('receive', $sku, $quantityEach, null, $warehouse, $at, $notes);
        }
    }

    private function transferProduct(string $productCode, string $from, string $to, int $quantityEach, CarbonImmutable $at): void
    {
        foreach ($this->skusOf($productCode) as $sku) {
            $quantity = min($quantityEach, $this->available($sku, $from));

            if ($quantity > 0) {
                $this->move('transfer', $sku, $quantity, $from, $to, $at);
            }
        }
    }

    private function transferAll(string $sku, string $from, string $to, CarbonImmutable $at, string $notes): void
    {
        $quantity = $this->available($sku, $from);

        if ($quantity > 0) {
            $this->move('transfer', $sku, $quantity, $from, $to, $at, $notes);
        }
    }

    /**
     * @param  array<string, int>  $products  quantity of each variant, keyed by product code
     */
    private function placePurchaseOrder(string $label, string $supplier, string $warehouse, array $products, CarbonImmutable $at, string $status = 'ordered', ?string $notes = null): void
    {
        $sequence = count($this->purchaseOrders) + 1;

        $order = PurchaseOrder::create([
            'organization_id' => $this->organization->id,
            'sequence' => $sequence,
            'number' => PurchaseOrder::numberFor($sequence),
            'supplier_id' => $this->suppliers[$supplier]->id,
            'warehouse_id' => $this->warehouses[$warehouse]->id,
            'status' => $status,
            'expected_at' => $at->addDays(10)->toDateString(),
            'notes' => $notes,
            'created_by' => $this->users['manager']->id,
            'ordered_at' => $status === 'draft' ? null : $at,
        ]);
        $this->backdate($order, $at);

        foreach ($products as $productCode => $quantity) {
            foreach ($this->skusOf($productCode) as $sku) {
                $order->lines()->create([
                    'product_variant_id' => $this->variants[$sku]->id,
                    'quantity_ordered' => $quantity,
                    'quantity_received' => 0,
                    // Suppliers charge a little over half the selling price.
                    'unit_cost' => number_format((float) $this->variants[$sku]->price * 0.55, 2, '.', ''),
                ]);
            }
        }

        $this->purchaseOrders[$label] = $order;
    }

    /**
     * Receives a share of what is still to come on each line, into the order's warehouse. Products in $splitTo
     * have half of their units sent to another warehouse instead.
     *
     * @param  array<string, string>  $splitTo  warehouse code keyed by product code
     */
    private function receivePurchaseOrder(string $label, CarbonImmutable $at, float $share = 1.0, array $splitTo = [], bool $withSerials = false): void
    {
        $order = $this->purchaseOrders[$label];
        $warehouse = array_search($order->warehouse_id, array_map(fn (Warehouse $w): int => $w->id, $this->warehouses), true);

        foreach ($order->lines()->with('productVariant')->get() as $line) {
            $sku = $line->productVariant->sku;
            $quantity = (int) max(1, floor($line->quantityRemaining() * $share));
            $split = $splitTo[Str::before($sku, '-')] ?? null;
            $allocations = $split ? [$warehouse => (int) ceil($quantity / 2), $split => intdiv($quantity, 2)] : [$warehouse => $quantity];

            foreach ($allocations as $destination => $allocated) {
                $movement = $this->move('receive', $sku, $allocated, null, $destination, $at, purchaseOrderLine: $line);

                if ($withSerials) {
                    $this->receiveSerials($movement, $sku, $allocated, $destination, $at);
                }
            }

            $line->increment('quantity_received', $quantity);
        }

        $complete = $order->lines()->whereColumn('quantity_received', '<', 'quantity_ordered')->doesntExist();
        $order->update(['status' => $complete ? 'received' : 'partially_received']);
    }

    private function receiveSerials(StockMovement $movement, string $sku, int $quantity, string $warehouse, CarbonImmutable $at): void
    {
        $existing = $this->organization->serialNumbers()->where('product_variant_id', $this->variants[$sku]->id)->count();

        for ($unit = 1; $unit <= $quantity; $unit++) {
            $movement->serialNumbers()->attach(SerialNumber::create([
                'organization_id' => $this->organization->id,
                'product_variant_id' => $this->variants[$sku]->id,
                'current_warehouse_id' => $this->warehouses[$warehouse]->id,
                'serial_number' => sprintf('%s-%04d', $sku, $existing + $unit),
                'status' => 'in_stock',
                'received_at' => $at,
            ]));
        }
    }

    private function closePurchaseOrder(string $label, CarbonImmutable $at): void
    {
        $this->purchaseOrders[$label]->update(['status' => 'closed', 'closed_at' => $at]);
    }

    private function cancelPurchaseOrder(string $label, CarbonImmutable $at): void
    {
        $this->purchaseOrders[$label]->update(['status' => 'cancelled', 'cancelled_at' => $at]);
    }

    /**
     * A confirmed order for one to three everyday variants the ship-from warehouse has in stock.
     */
    private function sellEveryday(string $label, string $customer, string $shipFrom, CarbonImmutable $at): void
    {
        $inStock = [];

        foreach (self::EVERYDAY_PRODUCTS as $productCode) {
            foreach ($this->skusOf($productCode) as $sku) {
                if ($this->available($sku, $shipFrom) >= 2) {
                    $inStock[] = $sku;
                }
            }
        }

        $lines = [];

        for ($pick = mt_rand(1, 3); $pick > 0 && $inStock !== []; $pick--) {
            $index = mt_rand(0, count($inStock) - 1);
            $sku = $inStock[$index];
            array_splice($inStock, $index, 1);

            $lines[$sku] = min(mt_rand(2, 6), $this->available($sku, $shipFrom));
        }

        if ($lines !== []) {
            $this->createSalesOrder($label, $customer, $shipFrom, $lines, $at);
        }
    }

    /**
     * @param  array<string, int>  $lines  quantity keyed by SKU
     */
    private function createSalesOrder(string $label, string $customer, string $shipFrom, array $lines, CarbonImmutable $at, string $status = 'confirmed', ?string $notes = null): void
    {
        $sequence = count($this->salesOrders) + 1;

        $order = SalesOrder::create([
            'organization_id' => $this->organization->id,
            'sequence' => $sequence,
            'number' => SalesOrder::numberFor($sequence),
            'customer_id' => $this->customers[$customer]->id,
            'warehouse_id' => $this->warehouses[$shipFrom]->id,
            'status' => $status,
            'deliver_by' => $at->addDays(5)->toDateString(),
            'notes' => $notes,
            'created_by' => $this->users['member']->id,
            'confirmed_at' => $status === 'draft' ? null : $at,
        ]);
        $this->backdate($order, $at);

        foreach ($lines as $sku => $quantity) {
            $order->lines()->create([
                'product_variant_id' => $this->variants[$sku]->id,
                'quantity_ordered' => $quantity,
                'quantity_dispatched' => 0,
                'quantity_returned' => 0,
                'unit_price' => $this->variants[$sku]->price,
            ]);
        }

        $this->salesOrders[$label] = $order;
    }

    /**
     * Dispatches a share of each line from the ship-from warehouse, as far as its stock allows. Serial-numbered
     * products send their oldest units there.
     */
    private function dispatchSalesOrder(string $label, CarbonImmutable $at, float $share = 1.0): void
    {
        $order = $this->salesOrders[$label] ?? null;

        if ($order === null) {
            return;
        }

        $shipFrom = $order->warehouse->code;

        foreach ($order->lines()->with('productVariant')->get() as $line) {
            $sku = $line->productVariant->sku;
            $quantity = min((int) max(1, floor($line->quantityRemaining() * $share)), $this->available($sku, $shipFrom));

            if ($quantity < 1) {
                continue;
            }

            $movement = $this->move('dispatch', $sku, $quantity, $shipFrom, null, $at, salesOrderLine: $line);
            $line->increment('quantity_dispatched', $quantity);

            $serials = SerialNumber::query()
                ->where('product_variant_id', $line->product_variant_id)
                ->where('current_warehouse_id', $order->warehouse_id)
                ->where('status', 'in_stock')
                ->orderBy('serial_number')
                ->limit($quantity)
                ->get();

            foreach ($serials as $serial) {
                $serial->update(['status' => 'dispatched', 'current_warehouse_id' => null]);
                $movement->serialNumbers()->attach($serial);
            }
        }

        $lines = $order->lines()->get();
        $dispatched = $lines->sum('quantity_dispatched');

        if ($dispatched > 0) {
            $order->update(['status' => $dispatched >= $lines->sum('quantity_ordered') ? 'dispatched' : 'partially_dispatched']);
        }
    }

    private function returnSalesOrder(string $label, string $sku, int $quantity, string $warehouse, CarbonImmutable $at, string $notes): void
    {
        $order = $this->salesOrders[$label];
        $line = $order->lines()->where('product_variant_id', $this->variants[$sku]->id)->firstOrFail();

        $movement = $this->move('return', $sku, $quantity, null, $warehouse, $at, $notes, salesOrderLine: $line);
        $line->increment('quantity_returned', $quantity);

        $serials = SerialNumber::query()
            ->where('product_variant_id', $line->product_variant_id)
            ->where('status', 'dispatched')
            ->orderBy('serial_number')
            ->limit($quantity)
            ->get();

        foreach ($serials as $serial) {
            $serial->update(['status' => 'in_stock', 'current_warehouse_id' => $this->warehouses[$warehouse]->id]);
            $movement->serialNumbers()->attach($serial);
        }
    }

    private function closeSalesOrder(string $label, CarbonImmutable $at): void
    {
        $this->salesOrders[$label]->update(['status' => 'closed', 'closed_at' => $at]);
    }

    private function cancelSalesOrder(string $label, CarbonImmutable $at): void
    {
        $this->salesOrders[$label]->update(['status' => 'cancelled', 'cancelled_at' => $at]);
    }

    private function backdate(Model $model, CarbonImmutable $at): void
    {
        $model->forceFill(['created_at' => $at, 'updated_at' => $at])->saveQuietly();
    }
}
