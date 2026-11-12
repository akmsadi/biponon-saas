<?php

namespace App\Notifications;

use App\Models\OrganizationInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Emails the link to accept an organization invitation. Sent on demand to the invited address, which may not
 * belong to any user yet.
 */
class OrganizationInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public OrganizationInvitation $invitation,
        public string $organizationName,
        public string $inviterName,
        public string $url,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('You are invited to join :organization', ['organization' => $this->organizationName]))
            ->line(__(':inviter invited you to join :organization on :app as :role.', [
                'inviter' => $this->inviterName,
                'organization' => $this->organizationName,
                'app' => config('app.name'),
                'role' => $this->invitation->role,
            ]))
            ->action(__('Accept invitation'), $this->url)
            ->line(__('This link expires on :date.', ['date' => $this->invitation->expires_at->toDayDateTimeString().' UTC']))
            ->line(__('If you were not expecting this invitation, you can ignore this email.'));
    }
}
