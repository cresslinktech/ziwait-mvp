<?php

namespace App\Domain\Orders;

enum OrderStatus: string
{
    case Submitted = 'submitted';
    case Acknowledged = 'acknowledged';
    case Preparing = 'preparing';
    case Ready = 'ready';
    case Completed = 'completed';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    /** @return list<self> */
    public function allowedNext(): array
    {
        return match ($this) {
            self::Submitted => [self::Acknowledged, self::Rejected, self::Cancelled],
            self::Acknowledged => [self::Preparing, self::Cancelled],
            self::Preparing => [self::Ready, self::Cancelled],
            self::Ready => [self::Completed, self::Cancelled],
            self::Completed, self::Rejected, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedNext(), true);
    }

    public function isFinal(): bool
    {
        return $this->allowedNext() === [];
    }

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
