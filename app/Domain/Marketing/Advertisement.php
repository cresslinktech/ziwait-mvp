<?php

namespace App\Domain\Marketing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Advertisement extends Model
{
    public const PLACEMENTS = [
        'home_banner' => 'Restaurant home banner',
        'meal_detail' => 'Meal detail banner',
        'discover_primary' => 'Discover primary banner',
        'discover_secondary' => 'Discover secondary banner',
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'sort_order' => 'integer', 'starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }

    public function imageUrl(): ?string
    {
        return $this->image_path ? Storage::disk('public')->url($this->image_path) : null;
    }
}