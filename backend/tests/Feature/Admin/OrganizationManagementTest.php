<?php

namespace Tests\Feature\Admin;

use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\User;
use App\Notifications\OrganizationInvitationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OrganizationManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        $this->superAdmin = User::factory()->superAdmin()->create();
    }

    public function test_only_super_admins_reach_the_admin_area(): void
    {
        $organization = Organization::factory()->create();
        $organizationAdmin = User::factory()->withOrganization($organization)->create();

        $this->actingAs($organizationAdmin)->get(route('admin.organizations.index'))->assertForbidden();
        $this->actingAs($organizationAdmin)
            ->post(route('admin.organizations.store'), ['name' => 'Rogue', 'admin_email' => 'x@example.com'])
            ->assertForbidden();
        $this->actingAs($organizationAdmin)
            ->put(route('admin.organizations.update', $organization), ['name' => 'X', 'slug' => 'x', 'status' => 'suspended'])
            ->assertForbidden();
        $this->actingAs($organizationAdmin)
            ->post(route('admin.organizations.admin-invitation', $organization), ['email' => 'x@example.com'])
            ->assertForbidden();

        $this->assertSame(1, Organization::count());
        $this->assertSame('active', $organization->fresh()->status);
    }

    public function test_the_list_shows_admins_pending_invitations_and_active_member_counts_without_inventory_data(): void
    {
        $acme = Organization::factory()->create(['name' => 'Acme']);
        User::factory()->withOrganization($acme)->create(['name' => 'Ada', 'email' => 'ada@example.com']);
        User::factory()->withOrganization($acme, 'member')->create();
        $suspended = User::factory()->withOrganization($acme, 'member')->create();
        $acme->users()->updateExistingPivot($suspended->id, ['status' => 'suspended']);

        $fresh = Organization::factory()->create(['name' => 'Fresh']);
        OrganizationInvitation::factory()->for($fresh)->create(['email' => 'owner@example.com', 'role' => 'admin']);

        $this->actingAs($this->superAdmin)
            ->get(route('admin.organizations.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/organizations')
                ->has('organizations.data', 2)
                ->where('organizations.data.0.name', 'Acme')
                ->where('organizations.data.0.members_count', 2)
                ->where('organizations.data.0.admins', [['name' => 'Ada', 'email' => 'ada@example.com']])
                ->where('organizations.data.0.pending_admin_invitation', null)
                ->where('organizations.data.1.admins', [])
                ->where('organizations.data.1.pending_admin_invitation.email', 'owner@example.com')
                ->missing('organizations.data.0.products')
                ->where('auth.can.administerPlatform', true));
    }

    public function test_the_list_can_be_searched_by_name_or_slug(): void
    {
        Organization::factory()->create(['name' => 'Acme', 'slug' => 'acme']);
        Organization::factory()->create(['name' => 'Other', 'slug' => 'zeta-shop']);

        $this->actingAs($this->superAdmin)
            ->get(route('admin.organizations.index', ['search' => 'zeta']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('organizations.data', 1)
                ->where('organizations.data.0.name', 'Other')
                ->where('filters.search', 'zeta'));
    }

    public function test_creating_an_organization_invites_its_first_admin_who_becomes_admin_on_accepting(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->post(route('admin.organizations.store'), [
                'name' => 'Acme Fashion',
                'slug' => '',
                'admin_email' => ' Owner@Example.com ',
            ])
            ->assertSessionHasNoErrors();

        $organization = Organization::sole();
        $invitation = OrganizationInvitation::sole();
        $link = $response->baseResponse->getSession()->get('inertia.flash_data')['invitationLink'] ?? null;

        $this->assertSame('acme-fashion', $organization->slug);
        $this->assertSame('active', $organization->status);
        $this->assertSame($this->superAdmin->id, $organization->created_by);
        $this->assertFalse($organization->users()->whereKey($this->superAdmin->id)->exists());
        $this->assertSame('owner@example.com', $invitation->email);
        $this->assertSame('admin', $invitation->role);
        $this->assertSame($organization->id, $invitation->organization_id);
        $this->assertNotNull($link);

        Notification::assertSentOnDemand(
            OrganizationInvitationNotification::class,
            fn (OrganizationInvitationNotification $notification, array $channels, AnonymousNotifiable $notifiable) => $notifiable->routes['mail'] === 'owner@example.com' && $notification->url === $link,
        );

        $owner = User::factory()->create(['email' => 'owner@example.com']);

        $this->actingAs($owner)->post(route('invitations.accept', basename($link)))->assertSessionHasNoErrors();

        $this->assertTrue($organization->hasAdmin($owner));
    }

    public function test_slugs_must_be_unique_and_well_formed(): void
    {
        Organization::factory()->create(['slug' => 'acme']);

        $this->actingAs($this->superAdmin)
            ->post(route('admin.organizations.store'), ['name' => 'Acme', 'slug' => 'ACME', 'admin_email' => 'a@example.com'])
            ->assertSessionHasErrors('slug');

        $this->actingAs($this->superAdmin)
            ->post(route('admin.organizations.store'), ['name' => 'Acme', 'slug' => 'acme shop!', 'admin_email' => 'a@example.com'])
            ->assertSessionHasErrors('slug');

        $this->assertSame(1, Organization::count());
        Notification::assertNothingSent();
    }

    public function test_suspending_an_organization_hides_it_from_its_members(): void
    {
        $organization = Organization::factory()->create(['slug' => 'acme']);
        $member = User::factory()->withOrganization($organization, 'member')->create();

        $this->actingAs($this->superAdmin)
            ->put(route('admin.organizations.update', $organization), ['name' => 'Acme Ltd', 'slug' => 'acme', 'status' => 'suspended'])
            ->assertSessionHasNoErrors();

        $this->assertSame('Acme Ltd', $organization->fresh()->name);
        $this->assertSame('suspended', $organization->fresh()->status);

        $this->actingAs($member)->get(route('dashboard'))->assertRedirect(route('no-organization'));

        $this->actingAs($this->superAdmin)
            ->put(route('admin.organizations.update', $organization), ['name' => 'Acme Ltd', 'slug' => 'acme', 'status' => 'active']);

        $this->actingAs($member->fresh())->get(route('dashboard'))->assertOk();
    }

    public function test_an_admin_can_be_invited_only_while_the_organization_has_none(): void
    {
        $organization = Organization::factory()->create();
        $pending = OrganizationInvitation::factory()->for($organization)->withToken('old-token')->create(['email' => 'owner@example.com', 'role' => 'admin']);

        $this->actingAs($this->superAdmin)
            ->post(route('admin.organizations.admin-invitation', $organization), ['email' => 'Owner@example.com'])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, OrganizationInvitation::count());
        $this->assertNull(OrganizationInvitation::findByToken('old-token'));
        $this->assertSame('admin', $pending->fresh()->role);

        User::factory()->withOrganization($organization)->create();

        $this->actingAs($this->superAdmin)
            ->post(route('admin.organizations.admin-invitation', $organization), ['email' => 'someone@example.com'])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, OrganizationInvitation::count());
        Notification::assertSentOnDemandTimes(OrganizationInvitationNotification::class, 1);
    }

    public function test_a_super_admin_without_an_organization_lands_in_the_admin_area(): void
    {
        $this->actingAs($this->superAdmin)->get(route('dashboard'))->assertRedirect(route('admin.organizations.index'));
        $this->actingAs($this->superAdmin)->get(route('no-organization'))->assertRedirect(route('admin.organizations.index'));
    }

    public function test_the_command_grants_and_revokes_super_admin_rights(): void
    {
        $user = User::factory()->create(['email' => 'ops@example.com']);

        $this->artisan('biponon:super-admin', ['email' => 'OPS@example.com'])->assertSuccessful();
        $this->assertTrue($user->fresh()->isSuperAdmin());

        $this->artisan('biponon:super-admin', ['email' => 'ops@example.com', '--revoke' => true])->assertSuccessful();
        $this->assertFalse($user->fresh()->isSuperAdmin());

        $this->artisan('biponon:super-admin', ['email' => 'nobody@example.com'])->assertFailed();
    }

    public function test_the_super_admin_flag_cannot_be_mass_assigned(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patch(route('profile.update'), [
            'name' => 'Sneaky',
            'email' => $user->email,
            'is_super_admin' => true,
        ]);

        $this->assertFalse($user->fresh()->isSuperAdmin());
    }
}
