<?php

namespace Tests\Feature\Organization;

use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\User;
use App\Notifications\OrganizationInvitationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InvitationTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        $this->organization = Organization::factory()->create(['name' => 'Acme']);
        $this->admin = User::factory()->withOrganization($this->organization)->create();
    }

    public function test_inviting_stores_a_hashed_token_emails_the_link_and_flashes_it_once(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('organization.invitations.store'), ['email' => ' New@Example.com ', 'role' => 'manager'])
            ->assertSessionHasNoErrors();

        $invitation = OrganizationInvitation::sole();
        $link = $response->baseResponse->getSession()->get('inertia.flash_data')['invitationLink'] ?? null;

        $this->assertSame('new@example.com', $invitation->email);
        $this->assertSame('manager', $invitation->role);
        $this->assertSame($this->admin->id, $invitation->invited_by);
        $this->assertTrue($invitation->expires_at->between(now()->addDays(7)->subMinute(), now()->addDays(7)->addMinute()));
        $this->assertNotNull($link);
        $this->assertSame($invitation->token, OrganizationInvitation::hashToken(basename($link)));

        Notification::assertSentOnDemand(
            OrganizationInvitationNotification::class,
            fn (OrganizationInvitationNotification $notification, array $channels, AnonymousNotifiable $notifiable) => $notifiable->routes['mail'] === 'new@example.com' && $notification->url === $link,
        );
    }

    public function test_inviting_an_address_with_a_pending_invitation_resends_it(): void
    {
        $invitation = OrganizationInvitation::factory()->for($this->organization)->withToken('old-token')->create(['email' => 'new@example.com']);

        $this->actingAs($this->admin)
            ->post(route('organization.invitations.store'), ['email' => 'new@example.com', 'role' => 'admin'])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, OrganizationInvitation::count());
        $this->assertSame('admin', $invitation->fresh()->role);
        $this->assertNull(OrganizationInvitation::findByToken('old-token'));
    }

    public function test_existing_members_cannot_be_invited(): void
    {
        User::factory()->withOrganization($this->organization, 'member')->create(['email' => 'member@example.com']);

        $this->actingAs($this->admin)
            ->post(route('organization.invitations.store'), ['email' => 'MEMBER@example.com', 'role' => 'member'])
            ->assertSessionHasErrors(['email' => 'This person is already a member of the organization.']);

        $this->assertSame(0, OrganizationInvitation::count());
        Notification::assertNothingSent();
    }

    public function test_resending_replaces_the_link_and_revoking_deletes_the_invitation(): void
    {
        $invitation = OrganizationInvitation::factory()->for($this->organization)->expired()->withToken('old-token')->create();

        $this->actingAs($this->admin)->post(route('organization.invitations.resend', $invitation))->assertSessionHasNoErrors();

        $this->assertNull(OrganizationInvitation::findByToken('old-token'));
        $this->assertTrue($invitation->fresh()->isUsable());
        Notification::assertSentOnDemandTimes(OrganizationInvitationNotification::class, 1);

        $this->actingAs($this->admin)->delete(route('organization.invitations.destroy', $invitation));

        $this->assertModelMissing($invitation);
    }

    public function test_invitations_of_another_organization_cannot_be_resent_or_revoked(): void
    {
        $foreign = OrganizationInvitation::factory()->create();

        $this->actingAs($this->admin)->post(route('organization.invitations.resend', $foreign))->assertNotFound();
        $this->actingAs($this->admin)->delete(route('organization.invitations.destroy', $foreign))->assertNotFound();

        $this->assertModelExists($foreign);
    }

    public function test_invalid_expired_and_used_links_show_the_invalid_state(): void
    {
        OrganizationInvitation::factory()->expired()->withToken('expired')->create();
        OrganizationInvitation::factory()->accepted()->withToken('used')->create();

        foreach (['unknown', 'expired', 'used'] as $token) {
            $this->get(route('invitations.show', $token))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('auth/accept-invitation')
                    ->where('state', 'invalid')
                    ->missing('invitation'));
        }
    }

    public function test_a_new_person_creates_an_account_and_joins(): void
    {
        $this->invitation('new@example.com', 'manager');

        $this->get(route('invitations.show', 'secret'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('state', 'register')
                ->where('invitation.organization', 'Acme')
                ->where('invitation.email', 'new@example.com'));

        $this->post(route('invitations.register', 'secret'), [
            'name' => 'New Person',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('dashboard'));

        $user = User::firstWhere('email', 'new@example.com');

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->email_verified_at);
        $this->assertSame('manager', $user->activeOrganizations->sole()->pivot->role);
        $this->assertSame($this->organization->id, session('current_organization_id'));
        $this->assertNotNull(OrganizationInvitation::findByToken('secret')->accepted_at);
        $this->get(route('invitations.show', 'secret'))->assertInertia(fn (Assert $page) => $page->where('state', 'invalid'));
    }

    public function test_someone_with_an_account_is_asked_to_log_in_and_returns_to_the_link(): void
    {
        User::factory()->withOrganization()->create(['email' => 'existing@example.com']);
        $this->invitation('existing@example.com');

        $this->get(route('invitations.show', 'secret'))
            ->assertInertia(fn (Assert $page) => $page->where('state', 'login'));

        $this->assertSame(route('invitations.show', 'secret'), session('url.intended'));

        $this->post(route('invitations.register', 'secret'), [
            'name' => 'Imposter',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasErrors('name');
    }

    public function test_the_invited_user_accepts_while_logged_in(): void
    {
        $user = User::factory()->withOrganization()->unverified()->create(['email' => 'Existing@Example.com']);
        $this->invitation('existing@example.com', 'member');

        $this->actingAs($user)
            ->get(route('invitations.show', 'secret'))
            ->assertInertia(fn (Assert $page) => $page->where('state', 'accept'));

        $this->actingAs($user)
            ->post(route('invitations.accept', 'secret'))
            ->assertRedirect(route('dashboard'));

        $this->assertTrue($user->activeOrganizations()->whereKey($this->organization->id)->exists());
        $this->assertSame($this->organization->id, session('current_organization_id'));
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_another_logged_in_user_cannot_accept(): void
    {
        $other = User::factory()->withOrganization()->create();
        $this->invitation('invited@example.com');

        $this->actingAs($other)
            ->get(route('invitations.show', 'secret'))
            ->assertInertia(fn (Assert $page) => $page->where('state', 'wrong_account')->where('signedInAs', $other->email));

        $this->actingAs($other)->post(route('invitations.accept', 'secret'))->assertForbidden();

        $this->assertFalse($other->organizations()->whereKey($this->organization->id)->exists());
    }

    public function test_expired_invitations_cannot_be_accepted(): void
    {
        $user = User::factory()->create(['email' => 'late@example.com']);
        OrganizationInvitation::factory()->for($this->organization)->expired()->withToken('secret')->create(['email' => 'late@example.com']);

        $this->actingAs($user)->post(route('invitations.accept', 'secret'))->assertNotFound();

        $this->assertFalse($user->organizations()->exists());
    }

    private function invitation(string $email, string $role = 'member'): OrganizationInvitation
    {
        return OrganizationInvitation::factory()
            ->for($this->organization)
            ->withToken('secret')
            ->create(['email' => $email, 'role' => $role, 'invited_by' => $this->admin->id]);
    }
}
