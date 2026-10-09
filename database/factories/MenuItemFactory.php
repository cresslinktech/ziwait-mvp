<?php

namespace Database\Factories;

use App\Domain\Menus\Models\MenuCategory;
use App\Domain\Menus\Models\MenuItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class MenuItemFactory extends Factory
{
    protected $model = MenuItem::class;

    public function definition(): array
    {
        return [
            'business_id' => fn (array $a) => MenuCategory::find($a['menu_category_id'])->business_id,
            'menu_category_id' => MenuCategory::factory(),
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }
}
