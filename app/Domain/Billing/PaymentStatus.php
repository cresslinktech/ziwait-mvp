<?php

namespace App\Domain\Billing;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Successful = 'successful';
    case Failed = 'failed';
    case Refunded = 'refunded';
    case Review = 'review'; // needs a human: never activates a subscription by itself
}
