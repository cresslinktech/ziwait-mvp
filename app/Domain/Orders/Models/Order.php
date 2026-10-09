<?php

namespace App\Domain\Orders\Models;

use App\Domain\Orders\OrderStatus;
use App\Domain\Tenancy\Models\Branch;
use App\Domain\Tenancy\Models\Business;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'total_minor' => 'integer',
            'placed_at' => 'datetime',
            'anonymised_at' => 'datetime',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function history(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('id');
    }
}
