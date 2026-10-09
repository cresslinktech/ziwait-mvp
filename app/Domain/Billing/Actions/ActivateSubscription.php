<?php

namespace App\Domain\Billing\Actions;

use App\Domain\Billing\Models\Payment;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Billing\SubscriptionStatus;

/** Applies one paid billing period to the business's subscription. Call inside a transaction. */
class ActivateSubscription
{
    public function __invoke(Payment $payment): Subscription
    {
        $price = $payment->planPrice()->with('plan')->first();
        $now = now();

        $subscription = Subscription::where('business_id', $payment->business_id)->lockForUpdate()->latest('id')->first()
            ?? new Subscription;

        // Renewing the same plan before it ends adds to the remaining time; anything else starts now.
        $extending = $subscription->exists
            && $subscription->status === SubscriptionStatus::Active
            && $subscription->plan_id === $price->plan_id
            && $subscription->current_period_end?->isFuture();

        $start = $extending ? $subscription->current_period_end : $now;
        $end = $price->interval === 'year' ? $start->copy()->addYearNoOverflow() : $start->copy()->addMonthNoOverflow();

        $subscription->forceFill([
            'business_id' => $payment->business_id,
            'plan_id' => $price->plan_id,
            'plan_price_id' => $price->id,
            'status' => SubscriptionStatus::Active,
            'current_period_start' => $extending ? $subscription->current_period_start : $now,
            'current_period_end' => $end,
            'grace_ends_at' => null,
            'renewal_reminded_at' => null,
            'cancelled_at' => null,
        ])->save();

        $payment->forceFill(['subscription_id' => $subscription->id])->save();

        return $subscription;
    }
}
