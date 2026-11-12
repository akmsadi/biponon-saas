<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard()
    {
        $user = User::factory()->withOrganization()->create();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
    }

    public function test_users_without_an_active_organization_are_sent_to_the_no_organization_page()
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('dashboard'))->assertRedirect(route('no-organization'));

        $this->get(route('no-organization'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('auth/no-organization'));
    }

    public function test_members_of_an_active_organization_skip_the_no_organization_page()
    {
        $this->actingAs(User::factory()->withOrganization()->create());

        $this->get(route('no-organization'))->assertRedirect(route('dashboard'));
    }
}
