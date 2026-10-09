<?php

namespace App\Domain\Tenancy\Actions;

use App\Domain\Administration\Models\AuditLog;
use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Billing\SubscriptionStatus;
use App\Domain\Tenancy\Models\Business;
use App\Domain\Tenancy\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RegisterBusiness
{
    /** Creates the owner, business and first branch atomically. */
    public function __invoke(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
            ]);

            $businessName = trim($data['name'])."'s Business";
            $business = Business::create([
                'name' => $businessName,
                'slug' => $this->uniqueSlug($businessName),
                'email' => $data['email'],
            ]);

            $business->memberships()->create(['user_id' => $user->id, 'role' => Role::Owner]);

            $this->startTrial($business);

            AuditLog::record('business.registered', $business, [], $business->id);

            return $user;
        });
    }

    /** New businesses start on a free trial of the configured plan, when that plan exists. */
    private function startTrial(Business $business): void
    {
        $days = (int) config('billing.trial_days');
        $plan = $days > 0 ? Plan::where('code', config('billing.trial_plan'))->where('is_active', true)->first() : null;

        if ($plan) {
            $subscription = new Subscription;
            $subscription->forceFill([
                'business_id' => $business->id,
                'plan_id' => $plan->id,
                'status' => SubscriptionStatus::Trialing,
                'current_period_start' => now(),
                'current_period_end' => now()->addDays($days),
            ])->save();
        }
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'business';
        $slug = $base;

        while (Business::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.Str::lower(Str::random(4));
        }

        return $slug;
    }
}
