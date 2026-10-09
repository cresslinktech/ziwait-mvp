<?php

namespace Database\Seeders;

use App\Domain\Billing\Models\Plan;
use Illuminate\Database\Seeder;

/** Placeholder plans and prices (NGN). Edit freely; real pricing is a business decision. */
class PlanSeeder extends Seeder
{
    public function run(): void
    {
        // [code, name, description, sort, monthly (kobo), yearly (kobo = 10 months), max_branches]
        $plans = [
            ['starter', 'Starter', 'For a single location getting started.', 1, 2500000, 25000000, '1'],
            ['professional', 'Professional', 'For growing businesses with several branches.', 2, 5000000, 50000000, '5'],
            ['business', 'Business', 'For established groups with many locations.', 3, 10000000, 100000000, '10'],
        ];

        foreach ($plans as [$code, $name, $description, $sort, $monthly, $yearly, $branches]) {
            $plan = Plan::updateOrCreate(['code' => $code], ['name' => $name, 'description' => $description, 'sort_order' => $sort, 'is_active' => true]);
            $plan->prices()->updateOrCreate(['interval' => 'month', 'currency' => 'NGN'], ['amount_minor' => $monthly, 'is_active' => true]);
            $plan->prices()->updateOrCreate(['interval' => 'year', 'currency' => 'NGN'], ['amount_minor' => $yearly, 'is_active' => true]);
            $plan->entitlements()->updateOrCreate(['key' => 'max_branches'], ['value' => $branches]);
        }
    }
}
