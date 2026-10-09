<?php

namespace App\Domain\Billing\Notifications;

use Illuminate\Notifications\Notification;

/** Billing messages are email only; push is reserved for new-order alerts. */
abstract class BillingNotification extends Notification
{
    public function via(object $notifiable): array
    {
        return ['mail'];
    }
}
