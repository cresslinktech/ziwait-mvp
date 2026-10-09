<?php

namespace App\Domain\Menus\Models;

use App\Domain\Tenancy\Models\Branch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchMenuItem extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['price_minor' => 'integer', 'is_available' => 'boolean', 'is_visible' => 'boolean'];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }
}
