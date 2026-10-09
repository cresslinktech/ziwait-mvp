<?php

namespace App\Domain\Billing\Actions;

use App\Domain\Administration\Models\AuditLog;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Billing\Notifications\SubscriptionExpiredNotification;
use App\Domain\Billing\Notifications\SubscriptionExpiringNotification;
use App\Domain\Billing\SubscriptionStatus;

/** Hourly sweep: period end -> grace -> expired, plus renewal reminders. Idempotent. */
class ProcessSubscriptionLifecycle
{
    /** @return array{grace:int, expired:int, reminded:int} */
    public function __invoke(): array
    {
        $now = now();
        $grace = max(0, (int) config('billing.grace_days'));
        $counts = ['grace' => 0, 'expired' => 0, 'reminded' => 0];

        Subscription::where('status', SubscriptionStatus::Active)->where('current_period_end', '<=', $now)->each(function (Subscription $s) use ($grace, &$counts) {
            $s->forceFill(['status' => SubscriptionStatus::PastDue, 'grace_ends_at' => $s->current_period_end->copy()->addDays($grace)])->save();
            AuditLog::record('subscription.past_due', $s, [], $s->business_id);
            $counts['grace']++;
        });

        $expiring = Subscription::where(fn ($q) => $q
            ->where(fn ($t) => $t->where('status', SubscriptionStatus::Trialing)->where('current_period_end', '<=', $now))
            ->orWhere(fn ($t) => $t->where('status', SubscriptionStatus::PastDue)->where('grace_ends_at', '<=', $now)));

        $expiring->each(function (Subscription $s) use (&$counts) {
            $s->forceFill(['status' => SubscriptionStatus::Expired])->save();
            AuditLog::record('subscription.expired', $s, [], $s->business_id);
            $this->notifyOwners($s, new SubscriptionExpiredNotification($s));
            $counts['expired']++;
        });

        Subscription::whereIn('status', [SubscriptionStatus::Active, SubscriptionStatus::Trialing])
            ->whereNull('renewal_reminded_at')
            ->whereBetween('current_period_end', [$now, $now->copy()->addDays((int) config('billing.reminder_days'))])
            ->each(function (Subscription $s) use (&$counts) {
                $s->forceFill(['renewal_reminded_at' => now()])->save();
                $this->notifyOwners($s, new SubscriptionExpiringNotification($s));
                $counts['reminded']++;
            });

        return $counts;
    }

    private function notifyOwners(Subscription $s, $notification): void
    {
        try {
            foreach ($s->business->users()->wherePivot('role', 'owner')->whereNull('users.disabled_at')->get() as $owner) {
                $owner->notify($notification);
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
