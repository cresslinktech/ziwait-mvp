<?php

namespace App\Domain\Billing\Actions;

use App\Domain\Billing\Models\Subscription;
use App\Domain\Tenancy\Models\Business;

/** Answers "what may this business do?" from its current subscription and plan. */
class EntitlementService
{
    public function subscription(Business $business): ?Subscription
    {
        return Subscription::where('business_id', $business->id)->with('plan.entitlements')->latest('id')->first();
    }

    public function isOperational(Business $business): bool
    {
        return (bool) $this->subscription($business)?->status->isOperational();
    }

    /** null = unlimited, 0 = none. */
    public function maxBranches(Business $business): ?int
    {
        $subscription = $this->subscription($business);

        return ($subscription && $subscription->status->isOperational()) ? $subscription->plan->maxBranches() : 0;
    }

    public function canAddBranch(Business $business): bool
    {
        $max = $this->maxBranches($business);

        return $max === null || $business->branches()->count() < $max;
    }
}
