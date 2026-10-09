<?php

namespace App\Domain\Menus\Models;

use App\Domain\Tenancy\Models\Business;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class MenuItem extends Model
{
    use HasFactory, SoftDeletes;

    // business_id, menu_category_id, image_path and is_active are set explicitly by the server.
    protected $fillable = ['name', 'description', 'sort_order'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(MenuCategory::class, 'menu_category_id');
    }

    public function branchItems(): HasMany
    {
        return $this->hasMany(BranchMenuItem::class);
    }

    public function imageUrl(): ?string
    {
        return $this->image_path ? Storage::disk('public')->url($this->image_path) : null;
    }

    protected static function newFactory(): \Database\Factories\MenuItemFactory
    {
        return \Database\Factories\MenuItemFactory::new();
    }
}
