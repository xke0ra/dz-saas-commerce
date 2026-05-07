<?php

namespace App\Notifications;

use App\Models\TenantInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TenantInvitationNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly TenantInvitation $invitation,
        private readonly string $plainToken,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $tenantName = $this->invitation->tenant->name;

        return (new MailMessage)
            ->subject("Invitation to join {$tenantName}")
            ->greeting('You have been invited to join a store team.')
            ->line("You were invited to join {$tenantName} as {$this->invitation->role->getLabel()}.")
            ->line('This invitation expires on '.$this->invitation->expires_at->toDateTimeString().'.')
            ->action('Accept invitation', $this->acceptUrl())
            ->line('If you did not expect this invitation, you can ignore this email.');
    }

    private function acceptUrl(): string
    {
        return url('/vendor/invitations/'.$this->plainToken.'/accept');
    }
}
