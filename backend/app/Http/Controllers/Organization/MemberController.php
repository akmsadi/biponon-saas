<?php

namespace App\Http\Controllers\Organization;

use App\Concerns\ScopesToOrganization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\UpdateMemberRequest;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Lets admins see, change and remove the members of the current organization. Admins cannot change or remove
 * their own membership, which also means an organization never loses its last active admin this way.
 */
class MemberController extends Controller
{
    use ScopesToOrganization;

    /**
     * List the members and the invitations that have not been accepted yet.
     */
    public function index(Request $request): Response
    {
        $organization = $this->authorizedOrganization($request);

        $members = $organization->users()
            ->orderBy('name')
            ->get(['users.id', 'users.name', 'users.email'])
            ->map(fn (User $member): array => [
                'id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
                'role' => $member->pivot->role,
                'status' => $member->pivot->status,
                'joined_at' => $member->pivot->joined_at ? Carbon::parse($member->pivot->joined_at)->toIso8601String() : null,
                'is_self' => $member->id === $request->user()->id,
            ]);

        $invitations = $organization->invitations()
            ->pending()
            ->with('inviter:id,name')
            ->latest()
            ->get()
            ->map(fn (OrganizationInvitation $invitation): array => [
                'id' => $invitation->id,
                'email' => $invitation->email,
                'role' => $invitation->role,
                'invited_by' => $invitation->inviter?->name,
                'expires_at' => $invitation->expires_at->toIso8601String(),
                'is_expired' => $invitation->expires_at->isPast(),
            ]);

        return Inertia::render('organization/members', [
            'members' => $members,
            'invitations' => $invitations,
            'roles' => Organization::ROLES,
        ]);
    }

    /**
     * Change another member's role or suspend / reactivate them.
     */
    public function update(UpdateMemberRequest $request, User $member): RedirectResponse
    {
        $organization = $this->authorizedOrganization($request);
        $this->ensureIsMember($organization, $member);

        if ($request->user()->is($member)) {
            return $this->refuseSelf();
        }

        $organization->users()->updateExistingPivot($member->id, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Member updated.')]);

        return back();
    }

    /**
     * Remove another member from the organization. Their past stock movements keep pointing at them.
     */
    public function destroy(Request $request, User $member): RedirectResponse
    {
        $organization = $this->authorizedOrganization($request);
        $this->ensureIsMember($organization, $member);

        if ($request->user()->is($member)) {
            return $this->refuseSelf();
        }

        $organization->users()->detach($member->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name was removed from the organization.', ['name' => $member->name])]);

        return back();
    }

    private function authorizedOrganization(Request $request): Organization
    {
        $organization = $this->organization($request);

        Gate::authorize('manage-members', $organization);

        return $organization;
    }

    /**
     * Abort with a 404 unless the user is a member (in any status) of the organization.
     */
    private function ensureIsMember(Organization $organization, User $member): void
    {
        abort_unless($organization->users()->whereKey($member->id)->exists(), 404);
    }

    private function refuseSelf(): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'error', 'message' => __('You cannot change or remove your own membership. Ask another admin.')]);

        return back();
    }
}
