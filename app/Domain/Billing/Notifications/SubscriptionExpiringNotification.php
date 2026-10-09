<?php

namespace App\Domain\Billing\Notifications;

use App\Domain\Billing\Models\Subscription;
use Illuminate\Notifications\Messages\MailMessage;

class SubscriptionExpiringNotification extends BillingNotification
{
    public function __construct(public Subscription $subscription) {}

    public function toMail(object $notifiable): MailMessage
    {
        $s = $this->subscription->loadMissing('business', 'plan');

        return (new MailMessage)
            ->subject("Your ZIWAIT {$s->plan->name} plan ends on {$s->current_period_end->format('j M Y')}")
            ->line("{$s->business->name} will stop taking orders after the grace period unless you renew.")
            ->action('Renew now', route('billing.index'));
    }
}
