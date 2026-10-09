<?php

namespace App\Domain\Tenancy\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Business extends Model
{
    use HasFactory, SoftDeletes;

    public const TYPES = ['restaurant' => 'Restaurant', 'hotel' => 'Hotel', 'cafe' => 'Cafe', 'bar' => 'Bar and lounge', 'bakery' => 'Bakery', 'other' => 'Other'];

    // status, onboarding progress and logo_path are only ever set by the server.
    protected $fillable = ['name', 'slug', 'email', 'phone', 'currency', 'business_type', 'description'];

    protected function casts(): array
    {
        return ['onboarding_completed_at' => 'datetime'];
    }

    public function isOnboarded(): bool
    {
        return $this->onboarding_completed_at !== null;
    }

    public function logoUrl(): ?string
    {
        return $this->logo_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($this->logo_path) : null;
    }

    public function coverUrl(): ?string
    {
        return $this->cover_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($this->cover_path) : null;
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(BusinessMembership::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'business_memberships')->withPivot('role')->withTimestamps();
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    protected static function newFactory(): \Database\Factories\BusinessFactory
    {
        return \Database\Factories\BusinessFactory::new();
    }
}
