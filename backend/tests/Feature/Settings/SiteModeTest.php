<?php

namespace Tests\Feature\Settings;

use App\Models\Organization;
use App\Models\PlatformSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SiteModeTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_site_starts_in_production_mode_and_every_page_is_told(): void
    {
        $this->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page->where('demoMode', false));
    }

    public function test_a_super_admin_switches_to_demo_mode_and_back(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        $this->actingAs($superAdmin)
            ->from(route('appearance.edit'))
            ->put(route('site-mode.update'), ['demo_mode' => true])
            ->assertRedirect(route('appearance.edit'));

        $this->assertDatabaseHas('platform_settings', ['key' => 'demo_mode', 'value' => 'true']);
        $this->assertTrue(PlatformSetting::demoMode());

        // Guests see it too, so the landing page shows the demo panel.
        auth()->logout();
        $this->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page->where('demoMode', true));

        $this->actingAs($superAdmin)
            ->put(route('site-mode.update'), ['demo_mode' => false])
            ->assertRedirect();

        $this->assertFalse(PlatformSetting::demoMode());
        $this->assertSame(1, PlatformSetting::count());
    }

    public function test_organization_admins_and_guests_cannot_change_the_site_mode(): void
    {
        $organizationAdmin = User::factory()->withOrganization(Organization::factory()->create())->create();

        $this->actingAs($organizationAdmin)
            ->put(route('site-mode.update'), ['demo_mode' => true])
            ->assertForbidden();

        auth()->logout();
        $this->put(route('site-mode.update'), ['demo_mode' => true])
            ->assertRedirect(route('login'));

        $this->assertFalse(PlatformSetting::demoMode());
        $this->assertDatabaseCount('platform_settings', 0);
    }

    public function test_the_mode_must_be_a_yes_or_no(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create())
            ->put(route('site-mode.update'), ['demo_mode' => 'sometimes'])
            ->assertSessionHasErrors('demo_mode');

        $this->assertFalse(PlatformSetting::demoMode());
    }
}
