<?php

namespace Tests\Feature\Organization;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MemberTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private User $admin;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();
        $this->admin = User::factory()->withOrganization($this->organization)->create(['name' => 'Ada Admin']);
        $this->member = User::factory()->withOrganization($this->organization, 'member')->create(['name' => 'Mo Member']);
    }

    public function test_admins_see_members_and_pending_invitations(): void
    {
        $this->organization->invitations()->create([
            'email' => 'new@example.com',
            'role' => 'manager',
            'token' => str_repeat('a', 64),
            'expires_at' => now()->addDay(),
        ]);
        $this->organization->invitations()->create([
            'email' => 'done@example.com',
            'role' => 'member',
            'token' => str_repeat('b', 64),
            'expires_at' => now()->addDay(),
            'accepted_at' => now(),
        ]);
        User::factory()->withOrganization()->create();

        $this->actingAs($this->admin)
            ->get(route('organization.members.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('organization/members')
                ->has('members', 2)
                ->where('members.0.name', 'Ada Admin')
                ->where('members.0.is_self', true)
                ->where('members.1.role', 'member')
                ->has('invitations', 1)
                ->where('invitations.0.email', 'new@example.com'));
    }

    public function test_only_admins_can_manage_members(): void
    {
        foreach (['manager', 'member'] as $role) {
            $user = User::factory()->withOrganization($this->organization, $role)->create();

            $this->actingAs($user)->get(route('organization.members.index'))->assertForbidden();
            $this->actingAs($user)->put(route('organization.members.update', $this->member), ['role' => 'admin', 'status' => 'active'])->assertForbidden();
            $this->actingAs($user)->delete(route('organization.members.destroy', $this->member))->assertForbidden();
            $this->actingAs($user)->post(route('organization.invitations.store'), ['email' => 'x@example.com', 'role' => 'admin'])->assertForbidden();
        }

        $this->assertSame('member', $this->roleOf($this->member));
    }

    public function test_admins_can_change_role_and_suspend_a_member(): void
    {
        $this->actingAs($this->admin)
            ->put(route('organization.members.update', $this->member), ['role' => 'manager', 'status' => 'suspended'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('organization_user', [
            'organization_id' => $this->organization->id,
            'user_id' => $this->member->id,
            'role' => 'manager',
            'status' => 'suspended',
        ]);

        $this->actingAs($this->member)->get(route('dashboard'))->assertRedirect(route('no-organization'));
    }

    public function test_role_and_status_must_be_known_values(): void
    {
        $this->actingAs($this->admin)
            ->put(route('organization.members.update', $this->member), ['role' => 'owner', 'status' => 'gone'])
            ->assertSessionHasErrors(['role', 'status']);
    }

    public function test_admins_can_remove_a_member(): void
    {
        $this->actingAs($this->admin)
            ->delete(route('organization.members.destroy', $this->member))
            ->assertRedirect();

        $this->assertDatabaseMissing('organization_user', ['user_id' => $this->member->id]);
        $this->assertModelExists($this->member);
    }

    public function test_admins_cannot_change_or_remove_themselves(): void
    {
        $this->actingAs($this->admin)
            ->put(route('organization.members.update', $this->admin), ['role' => 'member', 'status' => 'suspended']);

        $this->actingAs($this->admin)->delete(route('organization.members.destroy', $this->admin));

        $this->assertSame('admin', $this->roleOf($this->admin));
        $this->assertDatabaseHas('organization_user', ['user_id' => $this->admin->id, 'status' => 'active']);
    }

    public function test_users_outside_the_organization_cannot_be_managed(): void
    {
        $outsider = User::factory()->withOrganization()->create();

        $this->actingAs($this->admin)
            ->put(route('organization.members.update', $outsider), ['role' => 'member', 'status' => 'suspended'])
            ->assertNotFound();

        $this->actingAs($this->admin)
            ->delete(route('organization.members.destroy', $outsider))
            ->assertNotFound();
    }

    private function roleOf(User $user): ?string
    {
        return $this->organization->users()->whereKey($user->id)->first()?->pivot->role;
    }
}
