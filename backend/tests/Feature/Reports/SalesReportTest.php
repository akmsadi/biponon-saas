<?php

namespace Tests\Feature\Reports;

use App\Models\Customer;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SalesReportTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private User $member;

    private Customer $rahim;

    private Customer $karim;

    private ProductVariant $panjabi;

    private ProductVariant $saree;

    protected function setUp(): void
    {
        parent::setUp();

        // 2026-11-14 10:00 in Dhaka (UTC+6).
        Carbon::setTestNow(Carbon::parse('2026-11-14 04:00:00', 'UTC'));

        $this->organization = Organization::factory()->create();
        $this->member = User::factory()->withOrganization($this->organization, 'member')->create();
        $this->rahim = Customer::factory()->for($this->organization)->create(['name' => 'Rahim Traders']);
        $this->karim = Customer::factory()->for($this->organization)->create(['name' => 'Karim Fashion']);
        $this->panjabi = ProductVariant::factory()
            ->for(Product::factory()->for($this->organization)->create(['name' => 'Panjabi']))
            ->create(['sku' => 'PNJ-M-RED']);
        $this->saree = ProductVariant::factory()
            ->for(Product::factory()->for($this->organization)->create(['name' => 'Saree']))
            ->create(['sku' => 'SAR-F-BLU']);
    }

    /**
     * @param  array<int, array{0: ProductVariant, 1: int, 2: string, 3?: int, 4?: int}>  $lines  variant, ordered, unit price, dispatched, returned
     */
    private function order(Customer $customer, string $status, ?string $confirmedAt, array $lines): SalesOrder
    {
        $order = SalesOrder::factory()->for($this->organization)->create([
            'customer_id' => $customer->id,
            'status' => $status,
            'confirmed_at' => $confirmedAt === null ? null : Carbon::parse($confirmedAt, 'UTC'),
        ]);

        foreach ($lines as $line) {
            SalesOrderLine::factory()->for($order)->create([
                'product_variant_id' => $line[0]->id,
                'quantity_ordered' => $line[1],
                'unit_price' => $line[2],
                'quantity_dispatched' => $line[3] ?? 0,
                'quantity_returned' => $line[4] ?? 0,
            ]);
        }

        return $order;
    }

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get(route('reports.sales.index'))->assertRedirect(route('login'));
    }

    public function test_it_counts_confirmed_orders_less_returns_and_only_dispatched_units_on_orders_closed_short(): void
    {
        $this->order($this->rahim, 'confirmed', '2026-11-10 06:00', [[$this->panjabi, 10, '100.00']]);
        $this->order($this->rahim, 'dispatched', '2026-11-11 06:00', [[$this->panjabi, 5, '120.00', 5, 2], [$this->saree, 1, '2500.00', 1]]);
        $this->order($this->karim, 'closed', '2026-11-12 06:00', [[$this->saree, 4, '2000.00', 3]]);
        // Not counted: drafts, cancelled orders, other organizations.
        $this->order($this->karim, 'draft', null, [[$this->panjabi, 99, '100.00']]);
        $this->order($this->karim, 'cancelled', '2026-11-12 06:00', [[$this->panjabi, 99, '100.00']]);
        SalesOrderLine::factory()->create(['sales_order_id' => SalesOrder::factory()->confirmed()]);

        $this->actingAs($this->member)
            ->get(route('reports.sales.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('reports/sales')
                ->where('filters.group', 'product')
                ->where('filters.date_from', '2026-10-16')
                ->where('filters.date_to', '2026-11-14')
                ->has('rows.data', 2)
                // Saree: 1 × 2,500 + 3 (closed short) × 2,000 = 8,500.
                ->where('rows.data.0.product.name', 'Saree')
                ->where('rows.data.0.orders', 2)
                ->where('rows.data.0.units', 4)
                ->where('rows.data.0.dispatched', 4)
                ->where('rows.data.0.net_units', 4)
                ->where('rows.data.0.net_revenue', '8500.00')
                // Panjabi: 10 × 100 + (5 − 2) × 120 = 1,360.
                ->where('rows.data.1.product.name', 'Panjabi')
                ->where('rows.data.1.units', 15)
                ->where('rows.data.1.returned', 2)
                ->where('rows.data.1.net_units', 13)
                ->where('rows.data.1.net_revenue', '1360.00')
                ->where('summary', [
                    'net_revenue' => '9860.00',
                    'net_units' => 17,
                    'orders' => 3,
                    'average_order_value' => '3286.67',
                ]));
    }

    public function test_it_groups_by_variant_and_by_customer(): void
    {
        $this->order($this->rahim, 'confirmed', '2026-11-10 06:00', [[$this->panjabi, 2, '100.00'], [$this->saree, 1, '50.00']]);
        $this->order($this->karim, 'confirmed', '2026-11-11 06:00', [[$this->panjabi, 1, '100.00']]);

        $this->actingAs($this->member)
            ->get(route('reports.sales.index', ['group' => 'variant']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('rows.data', 2)
                ->where('rows.data.0.sku', 'PNJ-M-RED')
                ->where('rows.data.0.net_revenue', '300.00'));

        $this->actingAs($this->member)
            ->get(route('reports.sales.index', ['group' => 'customer']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('rows.data', 2)
                ->where('rows.data.0.customer.name', 'Rahim Traders')
                ->where('rows.data.0.orders', 1)
                ->where('rows.data.0.net_revenue', '250.00')
                ->where('rows.data.1.customer.name', 'Karim Fashion'));
    }

    public function test_dates_are_whole_days_in_the_organizations_timezone_and_can_be_open_ended(): void
    {
        // 2026-11-01 23:30 in Dhaka, which is still 2026-11-01 17:30 UTC.
        $this->order($this->rahim, 'confirmed', '2026-11-01 17:30', [[$this->panjabi, 1, '100.00']]);
        // 2026-11-02 00:30 in Dhaka, but 2026-11-01 18:30 UTC.
        $this->order($this->rahim, 'confirmed', '2026-11-01 18:30', [[$this->panjabi, 1, '200.00']]);
        $this->order($this->rahim, 'confirmed', '2025-01-01 06:00', [[$this->panjabi, 1, '400.00']]);

        $this->actingAs($this->member)
            ->get(route('reports.sales.index', ['date_from' => '2026-11-01', 'date_to' => '2026-11-01']))
            ->assertInertia(fn (Assert $page) => $page->where('summary.net_revenue', '100.00'));

        // An explicitly empty range is all time; a missing range is the last 30 days.
        $this->actingAs($this->member)
            ->get(route('reports.sales.index', ['date_from' => '', 'date_to' => '']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.net_revenue', '700.00')
                ->where('filters.date_from', null)
                // Page links keep the empty dates, so the next page stays all time.
                ->where('rows.first_page_url', fn (string $url) => str_contains($url, 'date_from=&date_to=')));

        $this->actingAs($this->member)
            ->get(route('reports.sales.index'))
            ->assertInertia(fn (Assert $page) => $page->where('summary.net_revenue', '300.00'));
    }

    public function test_it_filters_by_customer_and_search(): void
    {
        $this->order($this->rahim, 'confirmed', '2026-11-10 06:00', [[$this->panjabi, 1, '100.00']]);
        $this->order($this->karim, 'confirmed', '2026-11-10 06:00', [[$this->saree, 1, '50.00']]);

        $this->actingAs($this->member)
            ->get(route('reports.sales.index', ['customer' => $this->karim->id]))
            ->assertInertia(fn (Assert $page) => $page->has('rows.data', 1)->where('rows.data.0.product.name', 'Saree'));

        $this->actingAs($this->member)
            ->get(route('reports.sales.index', ['search' => 'rahim']))
            ->assertInertia(fn (Assert $page) => $page->has('rows.data', 1)->where('rows.data.0.product.name', 'Panjabi'));
    }

    public function test_it_exports_the_grouped_rows_as_csv(): void
    {
        $this->order($this->rahim, 'dispatched', '2026-11-10 06:00', [[$this->panjabi, 3, '100.00', 3, 1]]);

        $response = $this->actingAs($this->member)->get(route('reports.sales.export', ['group' => 'customer']));

        $response->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $lines = array_map(str_getcsv(...), explode("\n", trim(substr($response->streamedContent(), 3))));

        $this->assertSame([
            ['Customer', 'Orders', 'Units', 'Dispatched', 'Returned', 'Net units', 'Net revenue (BDT)'],
            ['Rahim Traders', '1', '3', '3', '1', '2', '200.00'],
        ], $lines);
    }
}
