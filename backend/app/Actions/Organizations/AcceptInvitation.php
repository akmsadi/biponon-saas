<?php

namespace App\Actions\Organizations;

use App\Models\OrganizationInvitation;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Turns a usable invitation into an active membership with the invited role and marks it accepted, so the link
 * cannot be used again. Opening the link proves the user owns the email, so it is marked verified too.
 */
class AcceptInvitation
{
    /**
     * @throws DomainException when the invitation was used, expired or the user is already a member meanwhile
     */
    public function handle(OrganizationInvitation $invitation, User $user): void
    {
        DB::transaction(function () use ($invitation, $user): void {
            $invitation = OrganizationInvitation::whereKey($invitation->id)->lockForUpdate()->firstOrFail();

            if (! $invitation->isUsable()) {
                throw new DomainException(__('This invitation has expired or was already used.'));
            }

            if ($user->organizations()->whereKey($invitation->organization_id)->exists()) {
                throw new DomainException(__('You are already a member of this organization.'));
            }

            $user->organizations()->attach($invitation->organization_id, [
                'role' => $invitation->role,
                'status' => 'active',
                'joined_at' => now(),
            ]);

            $invitation->update(['accepted_at' => now()]);

            if ($user->email_verified_at === null) {
                $user->forceFill(['email_verified_at' => now()])->save();
            }
        });
    }
}
