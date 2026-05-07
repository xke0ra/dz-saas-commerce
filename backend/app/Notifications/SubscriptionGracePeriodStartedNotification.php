<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionGracePeriodStartedNotification extends Notification implements ShouldQueue
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
        $graceEndsAt = $this->subscription->grace_ends_at?->toDateTimeString() ?? 'soon';

        return (new MailMessage)
            ->subject("{$tenantName} subscription grace period started")
            ->greeting('Subscription grace period started')
            ->line("The subscription for {$tenantName} has entered a grace period.")
            ->line("The grace period ends at {$graceEndsAt}.")
            ->line('Please renew the subscription to avoid store suspension.')
            ->action('Open billing dashboard', url('/vendor'));
    }
}
