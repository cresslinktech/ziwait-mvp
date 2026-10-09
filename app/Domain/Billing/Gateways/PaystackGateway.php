<?php

namespace App\Domain\Billing\Gateways;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Paystack adapter. Endpoints and fields follow Paystack's documentation:
 * POST /transaction/initialize, GET /transaction/verify/:reference, and webhooks signed with
 * HMAC-SHA512 of the raw body keyed by the secret key (x-paystack-signature).
 * Amounts are in the currency subunit (kobo for NGN), which matches our minor units.
 */
class PaystackGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'paystack';
    }

    public function initialize(string $reference, string $email, int $amountMinor, string $currency, string $callbackUrl, array $metadata = []): InitializedPayment
    {
        $response = $this->http()->post('/transaction/initialize', [
            'email' => $email,
            'amount' => $amountMinor,
            'currency' => $currency,
            'reference' => $reference,
            'callback_url' => $callbackUrl,
            'metadata' => $metadata,
        ]);

        $url = $response->json('data.authorization_url');
        if (! $response->successful() || $response->json('status') !== true || ! is_string($url)) {
            throw new GatewayException('Paystack could not start the transaction (HTTP '.$response->status().').');
        }

        return new InitializedPayment($url, (string) ($response->json('data.reference') ?? $reference));
    }

    public function verify(string $reference): VerifiedTransaction
    {
        try {
            $response = $this->http()->retry(2, 200, throw: false)->get('/transaction/verify/'.rawurlencode($reference));
        } catch (ConnectionException $e) {
            throw new GatewayException('Could not reach Paystack to verify the transaction.', 0, $e);
        }

        if (! $response->successful() || $response->json('status') !== true) {
            throw new GatewayException('Paystack could not verify the transaction (HTTP '.$response->status().').');
        }

        // The transaction status is data.status; the top-level status only says the API call worked.
        $raw = (string) $response->json('data.status');

        return new VerifiedTransaction(
            state: match ($raw) {
                'success' => 'success',
                'failed', 'abandoned', 'reversed' => 'failed',
                default => 'pending', // ongoing, pending, processing, queued
            },
            reference: (string) $response->json('data.reference'),
            amountMinor: (int) $response->json('data.amount'),
            currency: strtoupper((string) $response->json('data.currency')),
            transactionId: $response->json('data.id') !== null ? (string) $response->json('data.id') : null,
            paidAt: $response->json('data.paid_at'),
            rawStatus: $raw,
        );
    }

    public function isValidWebhookSignature(string $rawBody, ?string $signature): bool
    {
        $secret = (string) config('billing.paystack.secret_key');
        if ($secret === '' || ! $signature) {
            return false;
        }

        return hash_equals(hash_hmac('sha512', $rawBody, $secret), strtolower($signature));
    }

    private function http(): PendingRequest
    {
        $secret = (string) config('billing.paystack.secret_key');
        if ($secret === '') {
            throw new GatewayException('Paystack is not configured.');
        }

        return Http::baseUrl(rtrim(config('billing.paystack.base_url'), '/'))->withToken($secret)->acceptJson()->asJson()->timeout(15);
    }
}
