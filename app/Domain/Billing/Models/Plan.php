<?php

namespace App\Domain\Billing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function prices(): HasMany
    {
        return $this->hasMany(PlanPrice::class);
    }

    public function entitlements(): HasMany
    {
        return $this->hasMany(PlanEntitlement::class);
    }

    public function entitlement(string $key): ?string
    {
        return $this->entitlements->firstWhere('key', $key)?->value;
    }

    /** null means unlimited; a plan with no max_branches entitlement allows none. */
    public function maxBranches(): ?int
    {
        $value = $this->entitlement('max_branches');

        return match (true) {
            $value === null => 0,
            $value === 'unlimited' => null,
            default => (int) $value,
        };
    }
}
