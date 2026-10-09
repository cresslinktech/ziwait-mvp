<?php

namespace App\Domain\Billing\Actions;

use App\Domain\Billing\Gateways\GatewayException;
use App\Domain\Billing\Gateways\PaymentGateway;
use App\Domain\Billing\Models\Payment;
use App\Domain\Billing\Models\PlanPrice;
use App\Domain\Billing\PaymentStatus;
use App\Domain\Tenancy\Models\Business;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StartCheckout
{
    public function __construct(private PaymentGateway $gateway) {}

    /** Returns the hosted checkout URL. The amount always comes from the stored price, never the browser. */
    public function __invoke(Business $business, User $by, PlanPrice $price): string
    {
        $price->loadMissing('plan.entitlements');

        if (! $price->is_active || ! $price->plan->is_active) {
            throw ValidationException::withMessages(['plan_price_id' => 'That plan is not available.']);
        }
        if ($price->currency !== $business->currency) {
            throw ValidationException::withMessages(['plan_price_id' => "That price is not offered in {$business->currency}."]);
        }

        $max = $price->plan->maxBranches();
        $branches = $business->branches()->count();
        if ($max !== null && $branches > $max) {
            throw ValidationException::withMessages(['plan_price_id' => "You have {$branches} branches, but the {$price->plan->name} plan allows {$max}. Remove or choose a larger plan."]);
        }

        $payment = new Payment;
        $payment->forceFill([
            'business_id' => $business->id,
            'plan_price_id' => $price->id,
            'gateway' => $this->gateway->name(),
            'reference' => 'ZWP-'.Str::upper((string) Str::ulid()),
            'amount_minor' => $price->amount_minor,
            'currency' => $price->currency,
            'status' => PaymentStatus::Pending,
        ])->save();

        try {
            return $this->gateway->initialize(
                $payment->reference, $by->email, $payment->amount_minor, $payment->currency,
                route('billing.callback'), ['payment_id' => $payment->id, 'business_id' => $business->id],
            )->authorizationUrl;
        } catch (GatewayException $e) {
            report($e);
            $payment->forceFill(['status' => PaymentStatus::Failed, 'failure_reason' => 'initialize_failed'])->save();

            throw ValidationException::withMessages(['plan_price_id' => 'We could not start the payment. Please try again in a moment.']);
        }
    }
}
