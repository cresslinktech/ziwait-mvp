<?php

namespace App\Domain\Billing\Notifications;

use App\Domain\Billing\Models\Payment;
use App\Support\MoneyFormat;
use Illuminate\Notifications\Messages\MailMessage;

class PaymentReceiptNotification extends BillingNotification
{
    public function __construct(public Payment $payment) {}

    public function toMail(object $notifiable): MailMessage
    {
        $p = $this->payment->loadMissing('invoice', 'planPrice.plan', 'business');

        return (new MailMessage)
            ->subject("ZIWAIT receipt {$p->invoice?->number}")
            ->line("Thank you. We received your payment of ".MoneyFormat::display($p->amount_minor, $p->currency)." for the {$p->planPrice->plan->name} plan ({$p->business->name}).")
            ->line("Reference: {$p->reference}")
            ->action('View billing', route('billing.index'));
    }
}
