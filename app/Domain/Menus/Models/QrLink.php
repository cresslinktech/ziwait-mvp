<?php

namespace App\Domain\Menus\Models;

use App\Domain\Tenancy\Models\Branch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class QrLink extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['revoked_at' => 'datetime'];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function url(): string
    {
        return route('menu.show', $this->token);
    }

    /** The branch's live link, created on first use. */
    public static function currentFor(Branch $branch): self
    {
        return static::where('branch_id', $branch->id)->whereNull('revoked_at')->latest('id')->first()
            ?? static::create(['branch_id' => $branch->id, 'token' => Str::random(40)]);
    }

    /** Revokes every live link and issues a new one. */
    public static function rotate(Branch $branch): self
    {
        static::where('branch_id', $branch->id)->whereNull('revoked_at')->update(['revoked_at' => now()]);

        return static::create(['branch_id' => $branch->id, 'token' => Str::random(40)]);
    }
}
