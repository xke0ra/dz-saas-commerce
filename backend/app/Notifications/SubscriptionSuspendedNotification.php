<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionSuspendedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Subscription $subscription) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $tenantName = $this->subscription->tenant->name;

        return (new MailMessage)
            ->subject("{$tenantName} subscription suspended")
            ->greeting('Subscription suspended')
            ->line("The subscription for {$tenantName} has been suspended because the grace period ended.")
            ->line('Renewal is required before the store can be restored.')
            ->action('Open billing dashboard', url('/vendor'));
    }
}
