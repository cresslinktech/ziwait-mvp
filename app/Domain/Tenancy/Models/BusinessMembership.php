<?php

namespace App\Domain\Tenancy\Models;

use App\Domain\Tenancy\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessMembership extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['role' => Role::class];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
