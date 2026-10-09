<?php

namespace App\Domain\Billing\Actions;

use App\Domain\Administration\Models\AuditLog;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\Payment;
use App\Domain\Billing\PaymentStatus;
use Illuminate\Support\Str;

/** Marks a payment successful, issues its invoice and activates the subscription. Call inside a transaction. */
class CompletePayment
{
    public function __construct(private ActivateSubscription $activate) {}

    public function __invoke(Payment $payment, ?string $transactionId, ?string $paidAt = null, ?int $approvedBy = null): Payment
    {
        $payment->forceFill([
            'status' => PaymentStatus::Successful,
            'failure_reason' => null,
            'gateway_transaction_id' => $transactionId ?? $payment->gateway_transaction_id,
            'paid_at' => $paidAt ? \Illuminate\Support\Carbon::parse($paidAt) : now(),
            'verified_at' => now(),
        ])->save();

        $subscription = ($this->activate)($payment);

        $plan = $payment->planPrice->plan;
        $invoice = new Invoice;
        $invoice->forceFill([
            'business_id' => $payment->business_id,
            'payment_id' => $payment->id,
            'number' => 'TMP-'.Str::uuid(),
            'amount_minor' => $payment->amount_minor,
            'currency' => $payment->currency,
            'description' => "{$plan->name} plan, 1 {$payment->planPrice->interval}",
            'issued_at' => now(),
        ])->save();
        $invoice->forceFill(['number' => 'ZW-INV-'.str_pad((string) $invoice->id, 6, '0', STR_PAD_LEFT)])->save();

        AuditLog::record('payment.succeeded', $payment, ['subscription_id' => $subscription->id, 'manual_approval' => $approvedBy !== null], $payment->business_id, $approvedBy);

        return $payment;
    }
}
