<?php

namespace Database\Factories;

use App\Domain\Menus\Models\MenuCategory;
use App\Domain\Tenancy\Models\Business;
use Illuminate\Database\Eloquent\Factories\Factory;

class MenuCategoryFactory extends Factory
{
    protected $model = MenuCategory::class;

    public function definition(): array
    {
        return ['business_id' => Business::factory(), 'name' => fake()->unique()->word().' mains', 'sort_order' => 0, 'is_active' => true];
    }
}
