<?php

namespace App\Domain\Tenancy\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Branch extends Model
{
    use HasFactory, SoftDeletes;

    // business_id and is_active are deliberately not mass assignable.
    protected $fillable = ['name', 'slug', 'address', 'city', 'phone', 'email', 'timezone', 'settings'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'settings' => 'array'];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function photoUrl(): ?string
    {
        return $this->photo_path ? Storage::disk('public')->url($this->photo_path) : null;
    }

    public function staff(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'branch_memberships')->withPivot('permissions')->withTimestamps();
    }

    protected static function newFactory(): \Database\Factories\BranchFactory
    {
        return \Database\Factories\BranchFactory::new();
    }
}
