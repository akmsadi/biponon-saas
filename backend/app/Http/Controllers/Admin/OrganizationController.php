<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Organizations\SendInvitation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\OrganizationRequest;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Lets super-admins create organizations, rename or suspend them, and invite an admin to one that has none.
 * Super-admins only see names, admins and member counts here, never an organization's inventory data.
 */
class OrganizationController extends Controller
{
    /**
     * List every organization with its active admins, any pending admin invitation and its member count.
     */
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

        $organizations = Organization::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")->orWhere('slug', 'like', "%{$search}%");
                });
            })
            ->withCount(['users as members_count' => fn ($query) => $query->where('organization_user.status', 'active')])
            ->with([
                'admins' => fn ($query) => $query->select('users.id', 'users.name', 'users.email')->orderBy('users.name'),
                'invitations' => fn ($query) => $query->pending()->where('role', 'admin')->latest('id'),
            ])
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Organization $organization): array => [
                'id' => $organization->id,
                'name' => $organization->name,
                'slug' => $organization->slug,
                'status' => $organization->status,
                'members_count' => $organization->members_count,
                'created_at' => $organization->created_at?->toIso8601String(),
                'admins' => $organization->admins->map(fn (User $admin): array => [
                    'name' => $admin->name,
                    'email' => $admin->email,
                ])->values(),
                'pending_admin_invitation' => $this->pendingInvitationData($organization->invitations->first()),
            ]);

        return Inertia::render('admin/organizations', [
            'organizations' => $organizations,
            'filters' => ['search' => $search],
        ]);
    }

    /**
     * Create an organization and email its first admin an invitation. The link is flashed once to copy.
     */
    public function store(OrganizationRequest $request, SendInvitation $sendInvitation): RedirectResponse
    {
        $organization = Organization::create([
            'name' => $request->validated('name'),
            'slug' => $request->validated('slug'),
            'status' => 'active',
            'created_by' => $request->user()->id,
        ]);

        // SendInvitation gives the invitation its token and saves it. If sending fails, the organization still
        // exists without an admin and can be given one with inviteAdmin().
        $invitation = $organization->invitations()->make(['email' => $request->validated('admin_email'), 'role' => 'admin']);
        $invitation->setRelation('organization', $organization);

        Inertia::flash('invitationLink', $sendInvitation->handle($invitation, $request->user()));
        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name created. Invitation sent to :email.', [
            'name' => $organization->name,
            'email' => $invitation->email,
        ])]);

        return back();
    }

    /**
     * Rename an organization, change its slug or suspend / reactivate it. Suspending hides it from all members.
     */
    public function update(OrganizationRequest $request, Organization $organization): RedirectResponse
    {
        $organization->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Organization updated.')]);

        return back();
    }

    /**
     * Invite an admin to an organization that has no active admin, e.g. when the first invitation expired or
     * went to the wrong address. Inviting the address of the pending admin invitation resends it.
     */
    public function inviteAdmin(Request $request, Organization $organization, SendInvitation $sendInvitation): RedirectResponse
    {
        if (is_string($request->input('email'))) {
            $request->merge(['email' => mb_strtolower(trim($request->input('email')))]);
        }

        $validated = $request->validate(['email' => ['required', 'string', 'email', 'max:255']]);

        if ($organization->admins()->exists()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('This organization already has an admin. They can invite people from Members.')]);

            return back();
        }

        if ($organization->users()->where('email', $validated['email'])->exists()) {
            return back()->withErrors(['email' => __('This person is already a member of the organization.')]);
        }

        $invitation = $organization->invitations()->pending()->firstWhere('email', $validated['email'])
            ?? $organization->invitations()->make(['email' => $validated['email']]);

        $invitation->role = 'admin';
        $invitation->setRelation('organization', $organization);

        Inertia::flash('invitationLink', $sendInvitation->handle($invitation, $request->user()));
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation sent to :email.', ['email' => $invitation->email])]);

        return back();
    }

    /**
     * @return array{email: string, expires_at: string, is_expired: bool}|null
     */
    private function pendingInvitationData(?OrganizationInvitation $invitation): ?array
    {
        if ($invitation === null) {
            return null;
        }

        return [
            'email' => $invitation->email,
            'expires_at' => $invitation->expires_at->toIso8601String(),
            'is_expired' => $invitation->expires_at->isPast(),
        ];
    }
}
