<?php

namespace App\Domain\Billing\Gateways;

/** Everything subscription billing needs from a payment provider. Swap providers by binding another implementation. */
interface PaymentGateway
{
    public function name(): string;

    public function initialize(string $reference, string $email, int $amountMinor, string $currency, string $callbackUrl, array $metadata = []): InitializedPayment;

    /** Asks the provider (server to server) for the true state of a transaction. */
    public function verify(string $reference): VerifiedTransaction;

    /** Checks a webhook's signature against the exact raw request body. */
    public function isValidWebhookSignature(string $rawBody, ?string $signature): bool;
}
