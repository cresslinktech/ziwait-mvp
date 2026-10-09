<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Billing\Actions\ResolveReview;
use App\Domain\Billing\Actions\SettlePayment;
use App\Domain\Billing\Gateways\GatewayException;
use App\Domain\Billing\Models\ManualPaymentReview;
use App\Domain\Billing\Models\Payment;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Super-admin payments and reconciliation. The route group enforces super-admin on the server. */
class PaymentReviewController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status');

        return view('admin.payments.index', [
            'reviews' => ManualPaymentReview::with('payment.business', 'payment.planPrice.plan')->where('status', 'open')->oldest()->get(),
            'payments' => Payment::with('business', 'planPrice.plan')
                ->when($status, fn ($q) => $q->where('status', $status))
                ->latest('id')->paginate(25)->withQueryString(),
        ]);
    }

    public function resolve(Request $request, ManualPaymentReview $review, ResolveReview $resolve): RedirectResponse
    {
        $data = $request->validate(['decision' => ['required', 'in:approve,reject'], 'note' => ['required', 'string', 'min:5', 'max:1000']]);
        $resolve($review, $data['decision'] === 'approve', $data['note'], $request->user());

        return back()->with('status', 'Case resolved.');
    }

    /** Asks the gateway again about a pending or failed payment (for example after a missed webhook). */
    public function recheck(Payment $payment, SettlePayment $settle): RedirectResponse
    {
        try {
            $outcome = $settle($payment->reference);
        } catch (GatewayException $e) {
            report($e);

            return back()->with('error', 'The gateway could not be reached. Try again shortly.');
        }

        return back()->with('status', "Re-check result: {$outcome}.");
    }
}
