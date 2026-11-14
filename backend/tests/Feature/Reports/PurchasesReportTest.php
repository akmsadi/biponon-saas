<?php

namespace Tests\Feature\Reports;

use App\Models\Organization;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PurchasesReportTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private User $manager;

    private Supplier $aarong;

    private Supplier $bengal;

    protected function setUp(): void
    {
        parent::setUp();

        // 2026-11-14 10:00 in Dhaka (UTC+6).
        Carbon::setTestNow(Carbon::parse('2026-11-14 04:00:00', 'UTC'));

        $this->organization = Organization::factory()->create();
        $this->manager = User::factory()->withOrganization($this->organization, 'manager')->create();
        $this->aarong = Supplier::factory()->for($this->organization)->create(['name' => 'Aarong Textiles']);
        $this->bengal = Supplier::factory()->for($this->organization)->create(['name' => 'Bengal Fabrics']);
    }

    /**
     * @param  array<int, array{0: int, 1: int, 2: ?string}>  $lines  ordered, received, unit cost
     */
    private function order(Supplier $supplier, string $status, ?string $orderedAt, array $lines): PurchaseOrder
    {
        $order = PurchaseOrder::factory()->for($this->organization)->create([
            'supplier_id' => $supplier->id,
            'status' => $status,
            'ordered_at' => $orderedAt === null ? null : Carbon::parse($orderedAt, 'UTC'),
        ]);

        foreach ($lines as $line) {
            PurchaseOrderLine::factory()->for($order)->create([
                'quantity_ordered' => $line[0],
                'quantity_received' => $line[1],
                'unit_cost' => $line[2],
            ]);
        }

        return $order;
    }

    public function test_it_totals_placed_orders_per_supplier_with_amounts_for_managers(): void
    {
        $this->order($this->aarong, 'ordered', '2026-11-10 06:00', [[10, 0, '100.00'], [5, 0, null]]);
        $this->order($this->aarong, 'partially_received', '2026-11-11 06:00', [[20, 8, '50.00']]);
        $this->order($this->bengal, 'closed', '2026-11-12 06:00', [[6, 2, '10.00']]);
        $this->order($this->bengal, 'received', '2026-11-12 06:00', [[3, 3, '10.00']]);
        // Not counted: drafts, cancelled orders, other organizations.
        $this->order($this->aarong, 'draft', null, [[99, 0, '1.00']]);
        $this->order($this->aarong, 'cancelled', '2026-11-12 06:00', [[99, 0, '1.00']]);
        PurchaseOrderLine::factory()->create(['purchase_order_id' => PurchaseOrder::factory()->ordered()]);

        $this->actingAs($this->manager)
            ->get(route('reports.purchases.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('reports/purchases')
                ->where('canSeeCosts', true)
                ->has('rows.data', 2)
                ->where('rows.data.0', [
                    'supplier' => ['id' => $this->aarong->id, 'name' => 'Aarong Textiles'],
                    'orders' => 2,
                    'open_orders' => 2,
                    'units_ordered' => 35,
                    'units_received' => 8,
                    'units_outstanding' => 27,
                    'amount_ordered' => '2000.00',
                    'amount_received' => '400.00',
                ])
                // Closed short: the 4 unreceived units are not still to come.
                ->where('rows.data.1.supplier.name', 'Bengal Fabrics')
                ->where('rows.data.1.open_orders', 0)
                ->where('rows.data.1.units_outstanding', 0)
                ->where('summary', [
                    'orders' => 4,
                    'open_orders' => 2,
                    'units_ordered' => 44,
                    'units_received' => 13,
                    'units_outstanding' => 27,
                    'amount_ordered' => '2090.00',
                    'amount_received' => '450.00',
                ]));
    }

    public function test_members_see_the_report_without_amounts(): void
    {
        $member = User::factory()->withOrganization($this->organization, 'member')->create();
        $this->order($this->aarong, 'ordered', '2026-11-10 06:00', [[10, 0, '100.00']]);

        $this->actingAs($member)
            ->get(route('reports.purchases.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('canSeeCosts', false)
                ->missing('rows.data.0.amount_ordered')
                ->missing('summary.amount_ordered'));

        $csv = $this->actingAs($member)->get(route('reports.purchases.export'))->streamedContent();

        $this->assertStringNotContainsString('Amount', $csv);
        $this->assertStringNotContainsString('1000.00', $csv);
    }

    public function test_it_filters_by_status_supplier_and_placed_date(): void
    {
        $this->order($this->aarong, 'ordered', '2026-11-10 06:00', [[10, 0, null]]);
        $this->order($this->bengal, 'closed', '2026-11-12 06:00', [[6, 2, null]]);
        $this->order($this->bengal, 'received', '2025-01-01 06:00', [[3, 3, null]]);

        $this->actingAs($this->manager)
            ->get(route('reports.purchases.index', ['status' => 'closed']))
            ->assertInertia(fn (Assert $page) => $page->has('rows.data', 1)->where('rows.data.0.supplier.name', 'Bengal Fabrics'));

        $this->actingAs($this->manager)
            ->get(route('reports.purchases.index', ['search' => 'aar']))
            ->assertInertia(fn (Assert $page) => $page->has('rows.data', 1)->where('summary.orders', 1));

        // The default is the last 30 days; empty dates are all time.
        $this->actingAs($this->manager)
            ->get(route('reports.purchases.index'))
            ->assertInertia(fn (Assert $page) => $page->where('summary.orders', 2));

        $this->actingAs($this->manager)
            ->get(route('reports.purchases.index', ['date_from' => '', 'date_to' => '']))
            ->assertInertia(fn (Assert $page) => $page->where('summary.orders', 3));
    }

    public function test_it_exports_supplier_rows_with_amounts_for_managers(): void
    {
        $this->order($this->aarong, 'partially_received', '2026-11-10 06:00', [[4, 1, '25.00']]);

        $response = $this->actingAs($this->manager)->get(route('reports.purchases.export'));

        $response->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $lines = array_map(str_getcsv(...), explode("\n", trim(substr($response->streamedContent(), 3))));

        $this->assertSame([
            ['Supplier', 'Orders', 'Open orders', 'Units ordered', 'Units received', 'Still to come', 'Amount ordered (BDT)', 'Amount received (BDT)'],
            ['Aarong Textiles', '1', '1', '4', '1', '3', '100.00', '25.00'],
        ], $lines);
    }
}
