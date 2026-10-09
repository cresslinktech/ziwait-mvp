<?php

namespace App\Domain\Billing\Actions;

use App\Domain\Administration\Models\AuditLog;
use App\Domain\Billing\Gateways\PaymentGateway;
use App\Domain\Billing\Models\ManualPaymentReview;
use App\Domain\Billing\Models\Payment;
use App\Domain\Billing\Notifications\PaymentReceiptNotification;
use App\Domain\Billing\PaymentStatus;
use App\Domain\Tenancy\Models\Business;
use Illuminate\Support\Facades\DB;

/**
 * The single place a payment becomes "paid". Neither a browser redirect nor a webhook body is
 * believed: the gateway is asked server-side, and reference, amount and currency must match what
 * the server expected. Safe to call any number of times for the same reference.
 *
 * @return string unmatched|already_settled|in_review|pending|failed|review|settled
 */
class SettlePayment
{
    public function __construct(private PaymentGateway $gateway, private CompletePayment $complete) {}

    public function __invoke(string $reference): string
    {
        $payment = Payment::where('reference', $reference)->first();

        if (! $payment) {
            return 'unmatched';
        }
        if ($payment->status === PaymentStatus::Review) {
            return 'in_review';
        }
        if (! in_array($payment->status, [PaymentStatus::Pending, PaymentStatus::Failed], true)) {
            return 'already_settled';
        }

        $verified = $this->gateway->verify($reference); // network call outside any lock

        $outcome = DB::transaction(function () use ($reference, $verified) {
            $payment = Payment::where('reference', $reference)->lockForUpdate()->first();

            // Another request may have settled it while we were waiting on the gateway.
            if (! in_array($payment->status, [PaymentStatus::Pending, PaymentStatus::Failed], true)) {
                return $payment->status === PaymentStatus::Review ? 'in_review' : 'already_settled';
            }

            if ($verified->state === 'pending') {
                return 'pending';
            }

            if ($verified->state === 'failed') {
                $payment->forceFill(['status' => PaymentStatus::Failed, 'failure_reason' => $verified->rawStatus, 'verified_at' => now()])->save();

                return 'failed';
            }

            $mismatch = match (true) {
                strcasecmp($verified->reference, $payment->reference) !== 0 => ['reference_mismatch', "Gateway reported reference {$verified->reference}."],
                $verified->currency !== $payment->currency => ['currency_mismatch', "Expected {$payment->currency}, gateway reported {$verified->currency}."],
                $verified->amountMinor !== $payment->amount_minor => ['amount_mismatch', "Expected {$payment->amount_minor}, gateway reported {$verified->amountMinor} (minor units)."],
                $verified->transactionId !== null && Payment::where('gateway_transaction_id', $verified->transactionId)->where('id', '!=', $payment->id)->exists()
                    => ['duplicate_transaction', "Transaction {$verified->transactionId} is already attached to another payment."],
                default => null,
            };

            if ($mismatch) {
                [$reason, $details] = $mismatch;
                $payment->forceFill(['status' => PaymentStatus::Review, 'failure_reason' => $reason, 'verified_at' => now()])->save();
                $review = new ManualPaymentReview;
                $review->forceFill(['payment_id' => $payment->id, 'reason' => $reason, 'details' => $details])->save();
                AuditLog::record('payment.flagged_for_review', $payment, ['reason' => $reason], $payment->business_id);

                return 'review';
            }

            ($this->complete)($payment, $verified->transactionId, $verified->paidAt);

            return 'settled';
        });

        if ($outcome === 'settled') {
            $this->sendReceipt($reference);
        }

        return $outcome;
    }

    private function sendReceipt(string $reference): void
    {
        try {
            $payment = Payment::with('invoice', 'planPrice.plan', 'business')->where('reference', $reference)->first();
            foreach ($this->owners($payment->business) as $owner) {
                $owner->notify(new PaymentReceiptNotification($payment));
            }
        } catch (\Throwable $e) {
            report($e); // a failed receipt email must never undo a verified payment
        }
    }

    /** @return \Illuminate\Support\Collection<int, \App\Models\User> */
    private function owners(Business $business)
    {
        return $business->users()->wherePivot('role', 'owner')->whereNull('users.disabled_at')->get();
    }
}
