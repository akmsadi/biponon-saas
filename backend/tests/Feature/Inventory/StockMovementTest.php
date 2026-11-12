<?php

namespace Tests\Feature\Inventory;

use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SerialNumber;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StockMovementTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private User $user;

    private Warehouse $dhaka;

    private Warehouse $chattogram;

    private ProductVariant $panjabi;

    private ProductVariant $saree;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();
        $this->user = User::factory()->withOrganization($this->organization)->create(['name' => 'Rahim']);
        $this->dhaka = Warehouse::factory()->for($this->organization)->create(['code' => 'DHK']);
        $this->chattogram = Warehouse::factory()->for($this->organization)->create(['code' => 'CTG']);
        $this->panjabi = ProductVariant::factory()
            ->for(Product::factory()->for($this->organization)->create(['name' => 'Panjabi']))
            ->create(['sku' => 'PNJ-M-RED']);
        $this->saree = ProductVariant::factory()
            ->for(Product::factory()->for($this->organization)->create(['name' => 'Saree']))
            ->create(['sku' => 'SAR-F-BLU']);
    }

    private function receive(ProductVariant $variant, Warehouse $warehouse, int $quantity, string $occurredAt, array $attributes = []): StockMovement
    {
        return StockMovement::factory()->for($variant)->create([
            'to_warehouse_id' => $warehouse->id,
            'quantity' => $quantity,
            'occurred_at' => Carbon::parse($occurredAt, 'UTC'),
            ...$attributes,
        ]);
    }

    private function transfer(ProductVariant $variant, Warehouse $from, Warehouse $to, int $quantity, string $occurredAt): StockMovement
    {
        return StockMovement::factory()->for($variant)->create([
            'movement_type' => 'transfer',
            'from_warehouse_id' => $from->id,
            'to_warehouse_id' => $to->id,
            'quantity' => $quantity,
            'occurred_at' => Carbon::parse($occurredAt, 'UTC'),
        ]);
    }

    /**
     * @return list<int>
     */
    private function listedIds(array $query = []): array
    {
        return collect($this->actingAs($this->user)->get(route('inventory.movements.index', $query))->viewData('page')['props']['movements']['data'])
            ->pluck('id')
            ->all();
    }

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get(route('inventory.movements.index'))->assertRedirect(route('login'));
    }

    public function test_users_without_an_organization_cannot_see_movements(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('inventory.movements.index'))
            ->assertRedirect(route('no-organization'));
    }

    public function test_it_lists_only_the_current_organizations_movements_newest_first(): void
    {
        $older = $this->receive($this->panjabi, $this->dhaka, 10, '2026-11-01 10:00', ['performed_by' => $this->user->id, 'notes' => 'PO-17']);
        $newer = $this->transfer($this->panjabi, $this->dhaka, $this->chattogram, 3, '2026-11-02 10:00');
        $older->serialNumbers()->attach(SerialNumber::factory()->for($this->panjabi)->count(2)->create());
        StockMovement::factory()->create();

        $this->actingAs($this->user)
            ->get(route('inventory.movements.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('inventory/movements')
                ->has('movements.data', 2)
                ->where('movements.data.0.id', $newer->id)
                ->where('movements.data.0.from.code', 'DHK')
                ->where('movements.data.0.to.code', 'CTG')
                ->where('movements.data.0.direction', null)
                ->where('movements.data.1.id', $older->id)
                ->where('movements.data.1.from', null)
                ->where('movements.data.1.performed_by', 'Rahim')
                ->where('movements.data.1.serial_count', 2)
                ->where('movements.data.1.notes', 'PO-17')
                ->where('types', ['receive', 'transfer'])
                ->where('product', null));
    }

    public function test_it_filters_by_search_type_and_product(): void
    {
        $panjabiReceipt = $this->receive($this->panjabi, $this->dhaka, 10, '2026-11-01 10:00');
        $panjabiTransfer = $this->transfer($this->panjabi, $this->dhaka, $this->chattogram, 3, '2026-11-02 10:00');
        $sareeReceipt = $this->receive($this->saree, $this->dhaka, 5, '2026-11-03 10:00');

        $this->assertSame([$sareeReceipt->id], $this->listedIds(['search' => 'sar-f']));
        $this->assertSame([$panjabiTransfer->id, $panjabiReceipt->id], $this->listedIds(['search' => 'panj']));
        $this->assertSame([$panjabiTransfer->id], $this->listedIds(['type' => 'transfer']));
        $this->assertSame([$panjabiTransfer->id, $panjabiReceipt->id], $this->listedIds(['product' => $this->panjabi->product_id]));

        $this->actingAs($this->user)
            ->get(route('inventory.movements.index', ['product' => $this->panjabi->product_id]))
            ->assertInertia(fn (Assert $page) => $page->where('product.name', 'Panjabi'));
    }

    public function test_the_warehouse_filter_matches_either_side_and_signs_the_quantity(): void
    {
        $receipt = $this->receive($this->panjabi, $this->dhaka, 10, '2026-11-01 10:00');
        $transfer = $this->transfer($this->panjabi, $this->dhaka, $this->chattogram, 3, '2026-11-02 10:00');

        $this->actingAs($this->user)
            ->get(route('inventory.movements.index', ['warehouse' => $this->dhaka->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('movements.data', 2)
                ->where('movements.data.0.id', $transfer->id)
                ->where('movements.data.0.direction', 'out')
                ->where('movements.data.1.id', $receipt->id)
                ->where('movements.data.1.direction', 'in'));

        $this->actingAs($this->user)
            ->get(route('inventory.movements.index', ['warehouse' => $this->chattogram->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('movements.data', 1)
                ->where('movements.data.0.direction', 'in'));
    }

    public function test_date_bounds_are_whole_days_in_the_viewers_timezone(): void
    {
        // 20:00 UTC on the 10th is 02:00 on the 11th in Dhaka (UTC+6).
        $lateEvening = $this->receive($this->panjabi, $this->dhaka, 1, '2026-11-10 20:00');
        $previousDay = $this->receive($this->panjabi, $this->dhaka, 1, '2026-11-09 12:00');

        $this->assertSame([$lateEvening->id], $this->listedIds(['date_from' => '2026-11-10', 'date_to' => '2026-11-10']));
        $this->assertSame([], $this->listedIds(['date_from' => '2026-11-10', 'date_to' => '2026-11-10', 'tz' => 'Asia/Dhaka']));
        $this->assertSame([$lateEvening->id], $this->listedIds(['date_from' => '2026-11-11', 'tz' => 'Asia/Dhaka']));
        $this->assertSame([$previousDay->id], $this->listedIds(['date_to' => '2026-11-09']));
        $this->assertSame([$lateEvening->id, $previousDay->id], $this->listedIds(['date_from' => 'not-a-date', 'tz' => 'Not/AZone']));
    }

    public function test_export_downloads_the_filtered_movements_as_csv(): void
    {
        $receipt = $this->receive($this->panjabi, $this->dhaka, 10, '2026-11-10 20:00', [
            'performed_by' => $this->user->id,
            'notes' => '=HYPERLINK("http://evil")',
        ]);
        $receipt->serialNumbers()->attach([
            SerialNumber::factory()->for($this->panjabi)->create(['serial_number' => 'SN-1'])->id,
            SerialNumber::factory()->for($this->panjabi)->create(['serial_number' => 'SN-2'])->id,
        ]);
        $this->receive($this->saree, $this->dhaka, 5, '2026-11-03 10:00');
        StockMovement::factory()->create();

        $response = $this->actingAs($this->user)
            ->get(route('inventory.movements.export', ['search' => 'panjabi', 'tz' => 'Asia/Dhaka']))
            ->assertOk()
            ->assertDownload();

        $lines = array_map(str_getcsv(...), preg_split('/\R/', trim(ltrim($response->streamedContent(), "\u{FEFF}"))));

        $this->assertCount(2, $lines);
        $this->assertSame('Date', $lines[0][0]);
        $this->assertSame(
            ['2026-11-11 02:00', 'receive', 'Panjabi', 'PNJ-M-RED', '', 'DHK', '10', 'Rahim', 'SN-1 SN-2', '\'=HYPERLINK("http://evil")'],
            [$lines[1][0], $lines[1][1], $lines[1][2], $lines[1][5], $lines[1][6], $lines[1][7], $lines[1][8], $lines[1][9], $lines[1][10], $lines[1][11]],
        );
    }

    public function test_a_movements_serials_can_be_listed_but_not_another_organizations(): void
    {
        $receipt = $this->receive($this->panjabi, $this->dhaka, 2, '2026-11-01 10:00');
        $receipt->serialNumbers()->attach([
            SerialNumber::factory()->for($this->panjabi)->create(['serial_number' => 'SN-B'])->id,
            SerialNumber::factory()->for($this->panjabi)->create(['serial_number' => 'SN-A'])->id,
        ]);

        $this->actingAs($this->user)
            ->getJson(route('inventory.movements.serials', $receipt))
            ->assertOk()
            ->assertJsonPath('serials.0.serial_number', 'SN-A')
            ->assertJsonPath('serials.1.serial_number', 'SN-B')
            ->assertJsonPath('serials.0.status', 'in_stock');

        $this->actingAs($this->user)
            ->getJson(route('inventory.movements.serials', StockMovement::factory()->create()))
            ->assertNotFound();
    }
}
