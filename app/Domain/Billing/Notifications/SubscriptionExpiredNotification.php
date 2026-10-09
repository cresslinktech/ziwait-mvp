<?php

namespace App\Domain\Billing\Notifications;

use App\Domain\Billing\Models\Subscription;
use Illuminate\Notifications\Messages\MailMessage;

class SubscriptionExpiredNotification extends BillingNotification
{
    public function __construct(public Subscription $subscription) {}

    public function toMail(object $notifiable): MailMessage
    {
        $s = $this->subscription->loadMissing('business');

        return (new MailMessage)
            ->subject('Your ZIWAIT subscription has expired')
            ->line("{$s->business->name} is no longer taking orders. Renew to switch it back on; your menus and history are safe.")
            ->action('Renew now', route('billing.index'));
    }
}
