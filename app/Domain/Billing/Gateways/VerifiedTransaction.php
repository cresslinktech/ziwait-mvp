<?php

namespace App\Domain\Billing\Gateways;

final readonly class VerifiedTransaction
{
    /** @param 'success'|'failed'|'pending' $state normalised provider state */
    public function __construct(
        public string $state,
        public string $reference,
        public int $amountMinor,
        public string $currency,
        public ?string $transactionId,
        public ?string $paidAt = null,
        public ?string $rawStatus = null,
    ) {}
}
