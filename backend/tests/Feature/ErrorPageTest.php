<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\TestCase;

class ErrorPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_member_opening_a_manager_page_sees_the_styled_error_page(): void
    {
        $member = User::factory()->withOrganization(Organization::factory()->create(), 'member')->create();

        $this->actingAs($member)
            ->get(route('reports.stock-value.index'))
            ->assertForbidden()
            ->assertInertia(fn (Assert $page) => $page->component('error')->where('status', 403));
    }

    public function test_json_requests_still_get_a_json_error(): void
    {
        $member = User::factory()->withOrganization(Organization::factory()->create(), 'member')->create();

        $this->actingAs($member)
            ->getJson(route('reports.stock-value.index'))
            ->assertForbidden()
            ->assertJsonStructure(['message']);
    }

    public function test_an_unknown_url_shows_the_not_found_page(): void
    {
        $this->get('/this-page-does-not-exist')
            ->assertNotFound()
            ->assertInertia(fn (Assert $page) => $page->component('error')->where('status', 404));
    }

    public function test_server_errors_show_the_error_page_only_when_not_debugging(): void
    {
        Route::get('/_test/boom', fn () => throw new RuntimeException('Boom'))->middleware('web');

        config(['app.debug' => false]);
        $this->get('/_test/boom')
            ->assertInternalServerError()
            ->assertInertia(fn (Assert $page) => $page->component('error')->where('status', 500));

        config(['app.debug' => true]);
        $this->get('/_test/boom')
            ->assertInternalServerError()
            ->assertSee('Boom');
    }
}
