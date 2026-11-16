<?php

namespace Tests\Feature;

use App\Actions\Demo\BuildDemoCompany;
use App\Models\Organization;
use App\Models\PlatformSetting;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DemoCompanyTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_command_builds_a_demo_company_that_covers_every_order_status(): void
    {
        $this->artisan('biponon:demo')->assertSuccessful();

        $organization = BuildDemoCompany::organization();
        $this->assertNotNull($organization);

        $this->assertEqualsCanonicalizing(PurchaseOrder::STATUSES, $organization->purchaseOrders()->distinct()->pluck('status')->all());
        $this->assertEqualsCanonicalizing(SalesOrder::STATUSES, $organization->salesOrders()->distinct()->pluck('status')->all());
        $this->assertEqualsCanonicalizing(['receive', 'transfer', 'dispatch', 'return'], $organization->stockMovements()->distinct()->pluck('movement_type')->all());
        $this->assertTrue($organization->serialNumbers()->where('status', 'dispatched')->exists());
        $this->assertTrue($organization->invitations()->whereNull('accepted_at')->exists());
        $this->assertSame(0, $organization->stockMovements()->where('occurred_at', '>', now())->count());

        $roles = $organization->users()->wherePivot('status', 'active')->pluck('organization_user.role')->all();
        $this->assertEqualsCanonicalizing(['admin', 'manager', 'member'], $roles);

        $this->assertBalancesMatchMovements($organization);
    }

    public function test_fresh_rebuilds_the_demo_company_and_keeps_other_organizations(): void
    {
        $other = Organization::factory()->create();

        $this->artisan('biponon:demo')->assertSuccessful();
        $first = BuildDemoCompany::organization();
        $movements = $first->stockMovements()->count();

        // Without --fresh nothing is duplicated.
        $this->artisan('biponon:demo')->assertSuccessful();
        $this->assertSame(1, Organization::where('slug', BuildDemoCompany::SLUG)->count());

        $this->artisan('biponon:demo --fresh')->assertSuccessful();
        $rebuilt = BuildDemoCompany::organization();

        $this->assertNotSame($first->id, $rebuilt->id);
        $this->assertSame($movements, $rebuilt->stockMovements()->count());
        $this->assertSame(0, StockMovement::where('organization_id', $first->id)->count());
        $this->assertModelExists($other);
        $this->assertSame(3, User::where('is_demo', true)->whereIn('email', array_column(BuildDemoCompany::ACCOUNTS, 'email'))->count());
    }

    public function test_the_login_page_offers_the_demo_accounts_only_in_demo_mode(): void
    {
        $this->artisan('biponon:demo')->assertSuccessful();

        $this->get(route('login'))
            ->assertInertia(fn (Assert $page) => $page->where('demoAccounts', []));

        PlatformSetting::put('demo_mode', true);

        $this->get(route('login'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('demoAccounts', 3)
                ->where('demoAccounts.0.email', BuildDemoCompany::ACCOUNTS['admin']['email'])
                ->where('demoAccounts.0.password', BuildDemoCompany::PASSWORD));
    }

    public function test_demo_mode_without_a_demo_company_offers_no_accounts(): void
    {
        PlatformSetting::put('demo_mode', true);

        $this->get(route('login'))
            ->assertInertia(fn (Assert $page) => $page->where('demoAccounts', []));
    }

    public function test_demo_accounts_work_only_in_demo_mode(): void
    {
        $this->artisan('biponon:demo')->assertSuccessful();
        $credentials = ['email' => BuildDemoCompany::ACCOUNTS['manager']['email'], 'password' => BuildDemoCompany::PASSWORD];

        PlatformSetting::put('demo_mode', true);
        $this->post(route('login.store'), $credentials);
        $this->assertAuthenticated();
        $this->get(route('dashboard'))->assertOk();

        // Switching back to production logs them out on their next request.
        PlatformSetting::put('demo_mode', false);
        $this->get(route('dashboard'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('status');
        $this->assertGuest();
    }

    public function test_demo_accounts_cannot_change_their_login_or_the_demo_organization_members(): void
    {
        $this->artisan('biponon:demo')->assertSuccessful();
        PlatformSetting::put('demo_mode', true);

        $admin = User::firstWhere('email', BuildDemoCompany::ACCOUNTS['admin']['email']);
        $organization = BuildDemoCompany::organization();
        $staff = User::firstWhere('email', BuildDemoCompany::ACCOUNTS['member']['email']);

        $this->actingAs($admin)->withSession(['current_organization_id' => $organization->id]);

        $this->from(route('profile.edit'))
            ->patch(route('profile.update'), ['name' => 'Hacked', 'email' => 'hacked@example.com'])
            ->assertRedirect(route('profile.edit'))
            ->assertInertiaFlash('toast.type', 'error');

        $this->put(route('user-password.update'), [
            'current_password' => BuildDemoCompany::PASSWORD,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertRedirect();

        $this->post(route('organization.invitations.store'), ['email' => 'stranger@example.com', 'role' => 'member'])->assertRedirect();
        $this->delete(route('organization.members.destroy', $staff))->assertRedirect();

        $admin->refresh();
        $this->assertSame(BuildDemoCompany::ACCOUNTS['admin']['email'], $admin->email);
        $this->assertTrue(password_verify(BuildDemoCompany::PASSWORD, $admin->password));
        $this->assertFalse($organization->invitations()->where('email', 'stranger@example.com')->exists());
        $this->assertTrue($organization->users()->whereKey($staff->id)->exists());
    }

    public function test_other_users_are_not_restricted(): void
    {
        PlatformSetting::put('demo_mode', true);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('profile.update'), ['name' => 'New Name', 'email' => $user->email])
            ->assertSessionHasNoErrors();

        $this->assertSame('New Name', $user->fresh()->name);
    }

    /**
     * Every balance equals what came into its warehouse minus what left it.
     */
    private function assertBalancesMatchMovements(Organization $organization): void
    {
        $expected = [];

        foreach ($organization->stockMovements()->get() as $movement) {
            if ($movement->to_warehouse_id) {
                $key = "{$movement->product_variant_id}-{$movement->to_warehouse_id}";
                $expected[$key] = ($expected[$key] ?? 0) + $movement->quantity;
            }

            if ($movement->from_warehouse_id) {
                $key = "{$movement->product_variant_id}-{$movement->from_warehouse_id}";
                $expected[$key] = ($expected[$key] ?? 0) - $movement->quantity;
            }
        }

        foreach ($organization->stockBalances()->get() as $balance) {
            $this->assertSame($expected["{$balance->product_variant_id}-{$balance->warehouse_id}"] ?? 0, $balance->quantity);
        }
    }
}
