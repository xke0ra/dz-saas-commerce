<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionRenewalReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Subscription $subscription,
        private readonly int $daysUntilRenewal,
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
        $tenantName = $this->subscription->tenant->name;
        $planName = $this->subscription->plan->name;

        return (new MailMessage)
            ->subject("{$tenantName} subscription renewal reminder")
            ->greeting('Subscription renewal reminder')
            ->line("The {$planName} subscription for {$tenantName} renews in {$this->daysUntilRenewal} day(s).")
            ->line('Please make sure the renewal payment is confirmed before the current period ends.')
            ->action('Open billing dashboard', url('/vendor'));
    }
}
