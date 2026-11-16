<?php

namespace Tests\Feature;

use App\Models\AccessRequest;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AccessRequestTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, string>
     */
    private function form(array $overrides = []): array
    {
        return [
            'name' => 'Nadia Rahman',
            'company' => 'Rahman Textiles',
            'email' => ' Nadia@Example.com ',
            'phone' => '+880 1711 000000',
            'message' => 'Three shops and a warehouse.',
            'language' => 'bn',
            'website' => '',
            ...$overrides,
        ];
    }

    public function test_a_visitor_sends_a_request_from_the_landing_page(): void
    {
        $this->from(route('home'))
            ->post(route('access-requests.store'), $this->form())
            ->assertRedirect(route('home'))
            ->assertSessionHasNoErrors()
            ->assertInertiaFlash('accessRequested', true);

        $this->assertDatabaseHas('access_requests', [
            'name' => 'Nadia Rahman',
            'company' => 'Rahman Textiles',
            'email' => 'nadia@example.com',
            'language' => 'bn',
            'status' => 'new',
        ]);
    }

    public function test_name_company_and_a_valid_email_are_required(): void
    {
        $this->post(route('access-requests.store'), $this->form(['name' => '', 'company' => '', 'email' => 'not-an-email']))
            ->assertSessionHasErrors(['name', 'company', 'email']);

        $this->assertDatabaseCount('access_requests', 0);
    }

    public function test_bots_that_fill_the_hidden_field_are_thanked_but_not_saved(): void
    {
        $this->post(route('access-requests.store'), $this->form(['website' => 'https://spam.example']))
            ->assertSessionHasNoErrors()
            ->assertInertiaFlash('accessRequested', true);

        $this->assertDatabaseCount('access_requests', 0);
    }

    public function test_the_same_email_is_saved_once_a_day(): void
    {
        $this->post(route('access-requests.store'), $this->form());
        $this->post(route('access-requests.store'), $this->form(['email' => 'nadia@example.com']))
            ->assertInertiaFlash('accessRequested', true);

        $this->assertDatabaseCount('access_requests', 1);

        $this->travel(AccessRequest::REPEAT_AFTER_HOURS + 1)->hours();
        $this->post(route('access-requests.store'), $this->form());

        $this->assertDatabaseCount('access_requests', 2);
    }

    public function test_a_visitor_can_send_five_requests_a_minute(): void
    {
        foreach (range(1, 5) as $attempt) {
            $this->post(route('access-requests.store'), $this->form(['email' => "person{$attempt}@example.com"]))
                ->assertSessionHasNoErrors();
        }

        $this->post(route('access-requests.store'), $this->form(['email' => 'person6@example.com']))
            ->assertSessionHasErrors(['form' => 'too_many_attempts']);

        $this->assertDatabaseCount('access_requests', 5);
    }

    public function test_super_admins_list_filter_and_count_new_requests(): void
    {
        AccessRequest::factory()->create(['company' => 'Rahman Textiles']);
        AccessRequest::factory()->create(['company' => 'Dhaka Shoes']);
        AccessRequest::factory()->contacted()->create(['company' => 'Old Lead']);

        $this->actingAs(User::factory()->superAdmin()->create())
            ->get(route('admin.access-requests.index', ['status' => 'new', 'search' => 'rahman']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/access-requests')
                ->has('accessRequests.data', 1)
                ->where('accessRequests.data.0.company', 'Rahman Textiles')
                ->where('statusCounts', ['contacted' => 1, 'new' => 2])
                ->where('newAccessRequests', 2));
    }

    public function test_super_admins_change_the_status_and_delete_requests(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $accessRequest = AccessRequest::factory()->create();

        $this->actingAs($superAdmin)
            ->put(route('admin.access-requests.update', $accessRequest), ['status' => 'contacted'])
            ->assertRedirect();
        $this->assertSame('contacted', $accessRequest->fresh()->status);

        $this->actingAs($superAdmin)
            ->put(route('admin.access-requests.update', $accessRequest), ['status' => 'archived'])
            ->assertSessionHasErrors('status');

        $this->actingAs($superAdmin)
            ->delete(route('admin.access-requests.destroy', $accessRequest))
            ->assertRedirect();
        $this->assertModelMissing($accessRequest);
    }

    public function test_only_super_admins_see_or_change_requests(): void
    {
        $accessRequest = AccessRequest::factory()->create();
        $organizationAdmin = User::factory()->withOrganization(Organization::factory()->create())->create();

        $this->actingAs($organizationAdmin)->get(route('admin.access-requests.index'))->assertForbidden();
        $this->actingAs($organizationAdmin)
            ->put(route('admin.access-requests.update', $accessRequest), ['status' => 'closed'])
            ->assertForbidden();
        $this->actingAs($organizationAdmin)->delete(route('admin.access-requests.destroy', $accessRequest))->assertForbidden();

        $this->actingAs($organizationAdmin)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('newAccessRequests', 0));

        $this->assertSame('new', $accessRequest->fresh()->status);
    }

    public function test_creating_an_organization_from_a_request_fills_the_form_and_closes_the_request(): void
    {
        Notification::fake();
        $superAdmin = User::factory()->superAdmin()->create();
        $accessRequest = AccessRequest::factory()->create(['company' => 'Rahman Textiles', 'email' => 'nadia@example.com']);

        $this->actingAs($superAdmin)
            ->get(route('admin.organizations.index', ['from_request' => $accessRequest->id]))
            ->assertInertia(fn (Assert $page) => $page->where('prefill', [
                'access_request_id' => $accessRequest->id,
                'name' => 'Rahman Textiles',
                'admin_email' => 'nadia@example.com',
            ]));

        $this->actingAs($superAdmin)
            ->post(route('admin.organizations.store'), [
                'name' => 'Rahman Textiles',
                'slug' => 'rahman-textiles',
                'admin_email' => 'nadia@example.com',
                'access_request_id' => $accessRequest->id,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('organizations', ['slug' => 'rahman-textiles']);
        $this->assertSame('closed', $accessRequest->fresh()->status);
    }
}
