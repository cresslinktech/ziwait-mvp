<?php

namespace App\Domain\Orders\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['unit_price_minor' => 'integer', 'quantity' => 'integer', 'line_total_minor' => 'integer'];
    }
}
