<?php

namespace App\Actions\Organizations;

use App\Models\OrganizationInvitation;
use App\Models\User;
use App\Notifications\OrganizationInvitationNotification;
use Illuminate\Support\Facades\Notification;

/**
 * Gives an invitation a fresh token and expiry date, saves it and emails the link. Any earlier link for the
 * same invitation stops working.
 */
class SendInvitation
{
    /**
     * @return string the link to accept the invitation
     */
    public function handle(OrganizationInvitation $invitation, User $inviter): string
    {
        $token = $invitation->refreshToken();
        $invitation->invited_by = $inviter->id;
        $invitation->save();

        $url = route('invitations.show', $token);

        Notification::route('mail', $invitation->email)->notify(new OrganizationInvitationNotification(
            invitation: $invitation,
            organizationName: $invitation->organization->name,
            inviterName: $inviter->name,
            url: $url,
        ));

        return $url;
    }
}
