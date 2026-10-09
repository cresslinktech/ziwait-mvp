<?php

namespace App\Domain\Billing\Gateways;

final readonly class InitializedPayment
{
    public function __construct(public string $authorizationUrl, public string $reference) {}
}
