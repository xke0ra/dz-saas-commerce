<?php

namespace App\Notifications;

use App\Models\SubscriptionPayment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionPaymentRejectedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly SubscriptionPayment $payment) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $tenantName = $this->payment->tenant->name;
        $invoiceNumber = $this->payment->invoice?->invoice_number ?? 'the invoice';

        return (new MailMessage)
            ->subject("{$tenantName} subscription payment rejected")
            ->greeting('Subscription payment rejected')
            ->line("The subscription payment for {$invoiceNumber} was rejected.")
            ->line('Reason: '.$this->payment->rejection_reason)
            ->line('Please record a new payment with the correct proof or reference.')
            ->action('Open billing dashboard', url('/vendor/subscription-payments'));
    }
}
