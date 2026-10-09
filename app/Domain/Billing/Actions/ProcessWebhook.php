<?php

namespace App\Domain\Billing\Actions;

use App\Domain\Billing\Models\ManualPaymentReview;
use App\Domain\Billing\Models\Payment;
use App\Domain\Billing\Models\PaymentEvent;
use App\Domain\Billing\PaymentStatus;
use Illuminate\Support\Facades\DB;

/**
 * Handles an already signature-verified webhook. Each distinct event is recorded once; a repeat
 * delivery returns "duplicate" without touching payments or subscriptions. If processing throws,
 * the event stays unprocessed so the gateway's retry is handled normally.
 */
class ProcessWebhook
{
    public function __construct(private SettlePayment $settle) {}

    public function __invoke(string $gateway, string $rawBody): string
    {
        $payload = json_decode($rawBody, true);
        $type = is_array($payload) ? (string) ($payload['event'] ?? '') : '';
        $data = is_array($payload) ? ($payload['data'] ?? null) : null;

        if ($type === '' || ! is_array($data)) {
            return 'ignored_malformed';
        }

        // Paystack events carry no event id of their own, so identity is event name + transaction id.
        $key = $type.':'.($data['id'] ?? hash('sha256', $rawBody));
        $reference = isset($data['reference']) ? (string) $data['reference'] : (isset($data['transaction_reference']) ? (string) $data['transaction_reference'] : null);

        $inserted = DB::table('payment_events')->insertOrIgnore([
            'gateway' => $gateway, 'event_type' => $type, 'dedupe_key' => $key, 'reference' => $reference,
            'payload' => $rawBody, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $event = PaymentEvent::where('gateway', $gateway)->where('dedupe_key', $key)->firstOrFail();
        if (! $inserted && $event->processed_at) {
            return 'duplicate';
        }

        $outcome = match (true) {
            $type === 'charge.success' && $reference !== null => ($this->settle)($reference),
            str_starts_with($type, 'refund.') && $reference !== null => $this->flagRefund($reference, $type),
            default => 'ignored',
        };

        $event->forceFill(['outcome' => $outcome, 'processed_at' => now()])->save();

        return $outcome;
    }

    /** Refund payloads are not auto-applied; a person decides what a refund means for the subscription. */
    private function flagRefund(string $reference, string $type): string
    {
        $payment = Payment::where('reference', $reference)->first();
        if (! $payment) {
            return 'unmatched';
        }

        $exists = ManualPaymentReview::where('payment_id', $payment->id)->where('reason', 'refund_event')->where('status', 'open')->exists();
        if (! $exists) {
            $review = new ManualPaymentReview;
            $review->forceFill(['payment_id' => $payment->id, 'reason' => 'refund_event', 'details' => "Gateway sent {$type}."])->save();
        }

        return 'review';
    }
}
