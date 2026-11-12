<?php

namespace App\Http\Controllers\Organization;

use App\Actions\Organizations\SendInvitation;
use App\Concerns\ScopesToOrganization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\InviteMemberRequest;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * Lets admins invite people to the current organization, resend a link and revoke a pending invitation. The
 * link is flashed once as `invitationLink` so it can be copied; only its hash is stored.
 */
class InvitationController extends Controller
{
    use ScopesToOrganization;

    /**
     * Invite an email address. Inviting an address that already has a pending invitation resends it with the
     * newly chosen role.
     */
    public function store(InviteMemberRequest $request, SendInvitation $sendInvitation): RedirectResponse
    {
        $organization = $this->authorizedOrganization($request);

        $invitation = $organization->invitations()->pending()->firstWhere('email', $request->validated('email'))
            ?? $organization->invitations()->make(['email' => $request->validated('email')]);

        $invitation->role = $request->validated('role');
        $invitation->setRelation('organization', $organization);

        $url = $sendInvitation->handle($invitation, $request->user());

        Inertia::flash('invitationLink', $url);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation sent to :email.', ['email' => $invitation->email])]);

        return back();
    }

    /**
     * Send a pending invitation again with a new link and expiry date; the old link stops working.
     */
    public function resend(Request $request, OrganizationInvitation $invitation, SendInvitation $sendInvitation): RedirectResponse
    {
        $organization = $this->authorizedOrganization($request);
        $this->ensurePendingInOrganization($organization, $invitation);

        $invitation->setRelation('organization', $organization);

        $url = $sendInvitation->handle($invitation, $request->user());

        Inertia::flash('invitationLink', $url);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation sent to :email again.', ['email' => $invitation->email])]);

        return back();
    }

    /**
     * Revoke a pending invitation so its link no longer works.
     */
    public function destroy(Request $request, OrganizationInvitation $invitation): RedirectResponse
    {
        $organization = $this->authorizedOrganization($request);
        $this->ensurePendingInOrganization($organization, $invitation);

        $invitation->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation revoked.')]);

        return back();
    }

    private function authorizedOrganization(Request $request): Organization
    {
        $organization = $this->organization($request);

        Gate::authorize('manage-members', $organization);

        return $organization;
    }

    private function ensurePendingInOrganization(Organization $organization, OrganizationInvitation $invitation): void
    {
        abort_unless($invitation->organization_id === $organization->id && $invitation->accepted_at === null, 404);
    }
}
