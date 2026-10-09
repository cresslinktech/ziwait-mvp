<?php

namespace App\Models;

use App\Domain\Tenancy\Models\Branch;
use App\Domain\Tenancy\Models\Business;
use App\Domain\Tenancy\Models\BusinessMembership;
use App\Domain\Tenancy\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use NotificationChannels\WebPush\HasPushSubscriptions;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasPushSubscriptions, Notifiable;

    // is_super_admin is never mass assignable.
    protected $fillable = ['name', 'email', 'password'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'disabled_at' => 'datetime',
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
        ];
    }

    public function businessMemberships(): HasMany
    {
        return $this->hasMany(BusinessMembership::class);
    }

    public function businesses(): BelongsToMany
    {
        return $this->belongsToMany(Business::class, 'business_memberships')->withPivot('role')->withTimestamps();
    }

    public function branchAssignments(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'branch_memberships')->withPivot('permissions')->withTimestamps();
    }

    public function isSuperAdmin(): bool
    {
        return $this->is_super_admin && $this->disabled_at === null;
    }

    public function roleIn(Business $business): ?Role
    {
        return $this->businessMemberships()->where('business_id', $business->id)->first()?->role;
    }

    public function isOwnerOf(Business $business): bool
    {
        return $this->roleIn($business) === Role::Owner;
    }

    /** Owners see every branch; managers and staff only branches they are assigned to. */
    public function canAccessBranch(Branch $branch): bool
    {
        $role = $this->roleIn($branch->business);

        return match ($role) {
            Role::Owner => true,
            Role::Manager, Role::Staff => $this->branchAssignments()->whereKey($branch->id)->exists(),
            default => false,
        };
    }

    /**
     * Operational permission within one branch. Owners can do anything; managers anything in
     * their assigned branches; staff only the permissions explicitly granted on their assignment.
     */
    public function canInBranch(Branch $branch, string $permission): bool
    {
        $role = $this->roleIn($branch->business);

        if ($role === Role::Owner) {
            return true;
        }

        $assignment = $this->branchAssignments()->whereKey($branch->id)->first();

        return match ($role) {
            Role::Manager => $assignment !== null,
            Role::Staff => $assignment !== null && in_array($permission, json_decode($assignment->pivot->permissions ?? '[]', true) ?? [], true),
            default => false,
        };
    }

    /** Branches this user may operate, scoped to one business. */
    public function accessibleBranches(Business $business)
    {
        $query = $business->branches();

        return $this->isOwnerOf($business)
            ? $query
            : $query->whereIn('id', $this->branchAssignments()->select('branches.id'));
    }
}
