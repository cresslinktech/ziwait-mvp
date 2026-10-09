<?php

namespace App\Domain\Billing;

enum SubscriptionStatus: string
{
    case Trialing = 'trialing';
    case Active = 'active';
    case PastDue = 'past_due'; // period ended, inside the grace window
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    /** Whether the business may take orders and use paid features. */
    public function isOperational(): bool
    {
        return in_array($this, [self::Trialing, self::Active, self::PastDue], true);
    }
}
