<?php

namespace App\Domain\Billing\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentEvent extends Model
{
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'processed_at' => 'datetime'];
    }
}
