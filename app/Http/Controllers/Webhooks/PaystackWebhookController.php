<?php

namespace App\Http\Controllers\Webhooks;

use App\Domain\Billing\Actions\ProcessWebhook;
use App\Domain\Billing\Gateways\PaystackGateway;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaystackWebhookController extends Controller
{
    public function __invoke(Request $request, PaystackGateway $gateway, ProcessWebhook $process)
    {
        if (config('billing.paystack.enforce_ip_allowlist') && ! in_array($request->ip(), config('billing.paystack.webhook_ips'), true)) {
            return response('', 403);
        }

        // The signature must be computed over the exact bytes received.
        $raw = $request->getContent();

        if (! $gateway->isValidWebhookSignature($raw, $request->header('x-paystack-signature'))) {
            Log::warning('Rejected Paystack webhook with invalid signature', ['ip' => $request->ip()]);

            return response('', 401);
        }

        // Exceptions propagate as 500 so Paystack retries; successful and ignored events answer 200.
        $outcome = $process($gateway->name(), $raw);

        return response()->json(['status' => 'ok', 'outcome' => $outcome]);
    }
}
