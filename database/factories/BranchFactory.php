<?php

namespace Database\Factories;

use App\Domain\Tenancy\Models\Branch;
use App\Domain\Tenancy\Models\Business;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Branch> */
class BranchFactory extends Factory
{
    protected $model = Branch::class;

    public function definition(): array
    {
        $name = fake()->unique()->streetName();

        return [
            'business_id' => Business::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'timezone' => 'Africa/Lagos',
            'is_active' => true,
        ];
    }
}
