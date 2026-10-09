<?php

namespace App\Http\Controllers\Dashboard;

use App\Domain\Billing\Actions\EntitlementService;
use App\Domain\Billing\Actions\SettlePayment;
use App\Domain\Billing\Actions\StartCheckout;
use App\Domain\Billing\Gateways\GatewayException;
use App\Domain\Billing\Models\Payment;
use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\PlanPrice;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BillingController extends Controller
{
    public function index(Request $request, EntitlementService $entitlements)
    {
        $business = $request->attributes->get('business');
        $this->authorize('manageBilling', $business);

        return view('dashboard.billing.index', [
            'business' => $business,
            'subscription' => $entitlements->subscription($business),
            'plans' => Plan::where('is_active', true)->with(['prices' => fn ($q) => $q->where('is_active', true)->where('currency', $business->currency)->orderBy('interval'), 'entitlements'])->orderBy('sort_order')->get(),
            'payments' => Payment::where('business_id', $business->id)->with('invoice', 'planPrice.plan')->latest('id')->limit(50)->get(),
            'branchCount' => $business->branches()->count(),
        ]);
    }

    public function checkout(Request $request, StartCheckout $start)
    {
        $business = $request->attributes->get('business');
        $this->authorize('manageBilling', $business);

        $data = $request->validate(['plan_price_id' => ['required', 'integer', 'exists:plan_prices,id']]);

        return redirect()->away($start($business, $request->user(), PlanPrice::findOrFail($data['plan_price_id'])));
    }

    /**
     * Where the customer lands after Paystack. The query string proves nothing: we look the
     * payment up among this business's own payments and ask the gateway for the truth.
     */
    public function callback(Request $request, SettlePayment $settle): RedirectResponse
    {
        $business = $request->attributes->get('business');
        $this->authorize('manageBilling', $business);

        $reference = (string) $request->query('reference', $request->query('trxref', ''));
        abort_unless(Payment::where('reference', $reference)->where('business_id', $business->id)->exists(), 404);

        try {
            $outcome = $settle($reference);
        } catch (GatewayException $e) {
            report($e);
            $outcome = 'unverified';
        }

        $message = match ($outcome) {
            'settled', 'already_settled' => ['status', 'Payment confirmed. Your subscription is active.'],
            'pending', 'unverified' => ['status', 'We are still confirming your payment. This page will show it as soon as it is confirmed.'],
            'review', 'in_review' => ['status', 'Your payment is being reviewed by our team. We will email you once it is resolved.'],
            default => ['error', 'The payment was not completed. You have not been charged for a plan.'],
        };

        return redirect()->route('billing.index')->with(...$message);
    }
}
