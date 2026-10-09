<?php

namespace Database\Factories;

use App\Domain\Tenancy\Models\Business;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Business> */
class BusinessFactory extends Factory
{
    protected $model = Business::class;

    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'email' => fake()->companyEmail(),
            'currency' => 'NGN',
            'business_type' => 'restaurant',
            'onboarding_step' => 5,
            'onboarding_completed_at' => now(),
        ];
    }
}
