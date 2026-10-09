<?php

namespace App\Domain\Billing\Actions;

use App\Domain\Administration\Models\AuditLog;
use App\Domain\Billing\Models\ManualPaymentReview;
use App\Domain\Billing\Models\Payment;
use App\Domain\Billing\PaymentStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** A super admin's decision on a flagged payment. Always requires a note and always leaves an audit entry. */
class ResolveReview
{
    public function __construct(private CompletePayment $complete) {}

    public function __invoke(ManualPaymentReview $review, bool $approve, string $note, User $admin): ManualPaymentReview
    {
        abort_unless($admin->isSuperAdmin(), 403);

        return DB::transaction(function () use ($review, $approve, $note, $admin) {
            $review = ManualPaymentReview::lockForUpdate()->findOrFail($review->id);
            if ($review->status !== 'open') {
                throw ValidationException::withMessages(['review' => 'This case has already been resolved.']);
            }

            $payment = Payment::lockForUpdate()->findOrFail($review->payment_id);

            if ($review->reason === 'refund_event') {
                if ($approve) {
                    $payment->forceFill(['status' => PaymentStatus::Refunded])->save();
                }
            } elseif ($approve) {
                ($this->complete)($payment, $payment->gateway_transaction_id, null, $admin->id);
            } else {
                $payment->forceFill(['status' => PaymentStatus::Failed, 'failure_reason' => 'rejected_by_admin'])->save();
            }

            $review->forceFill([
                'status' => $approve ? 'approved' : 'rejected',
                'resolved_by' => $admin->id,
                'resolution_note' => $note,
                'resolved_at' => now(),
            ])->save();

            AuditLog::record('payment.review.'.($approve ? 'approved' : 'rejected'), $payment, ['reason' => $review->reason, 'note' => $note], $payment->business_id, $admin->id);

            return $review;
        });
    }
}
