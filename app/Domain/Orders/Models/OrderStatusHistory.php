<?php

namespace App\Domain\Orders\Models;

use App\Domain\Orders\OrderStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderStatusHistory extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'order_status_history';

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['from_status' => OrderStatus::class, 'to_status' => OrderStatus::class];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
