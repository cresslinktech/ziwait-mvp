<?php

namespace Database\Seeders;

use App\Domain\Menus\Models\MenuCategory;
use App\Domain\Menus\Models\MenuItem;
use App\Domain\Menus\Models\BranchMenuItem;
use App\Domain\Tenancy\Models\Branch;
use App\Domain\Tenancy\Models\Business;
use App\Domain\Tenancy\Models\BusinessMembership;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class MenuSeeder extends Seeder
{
    public function run(): void
    {
        // Get or create a test user
        $user = User::firstOrCreate(
            ['email' => 'demo@ziwait.test'],
            ['name' => 'Demo User', 'password' => bcrypt('password123'), 'is_super_admin' => false]
        );

        // Get or create a test business
        $business = Business::firstOrCreate(
            ['slug' => 'demo-restaurant'],
            [
                'name' => 'Demo Restaurant',
                'email' => 'demo@ziwait.test',
                'phone' => '+234800000000',
                'currency' => 'NGN',
                'status' => 'active',
                'business_type' => 'restaurant',
                'description' => 'A demo restaurant for testing',
                'onboarding_step' => 5,
                'onboarding_completed_at' => now(),
            ]
        );

        // Create membership
        BusinessMembership::firstOrCreate(
            ['business_id' => $business->id, 'user_id' => $user->id],
            ['role' => 'owner']
        );

        // Create branch
        $branch = Branch::firstOrCreate(
            ['business_id' => $business->id, 'slug' => 'main-branch'],
            [
                'name' => 'Main Branch',
                'address' => '123 Demo Street',
                'city' => 'Lagos',
                'phone' => '+234800000001',
                'email' => 'branch@ziwait.test',
                'timezone' => 'Africa/Lagos',
                'is_active' => true,
                'settings' => ['hours' => ['status' => 'open', 'open' => '08:00', 'close' => '22:00']],
            ]
        );

        // Stock images to copy
        $stockImages = [
            'food-appetizers.jpg',
            'food-mains.jpg',
            'food-drinks.jpg',
            'food-desserts.jpg',
        ];

        // Copy stock images to volume
        foreach ($stockImages as $image) {
            $source = public_path('images/stock/' . $image);
            if (is_file($source)) {
                $dest = 'menu/stock/' . $image;
                Storage::disk('public')->put($dest, file_get_contents($source));
            }
        }

        // Starter menu data
        $starterMenu = [
            'Appetizers' => [
                ['name' => 'Crispy Plantain Bites', 'description' => 'Sweet plantain served with house pepper sauce.', 'price' => '3500', 'image' => 'food-appetizers.jpg'],
                ['name' => 'Grilled Suya Skewers', 'description' => 'Tender beef with onions and a warm suya spice blend.', 'price' => '6500', 'image' => 'food-appetizers.jpg'],
            ],
            'Main Courses' => [
                ['name' => 'Smoky Jollof Rice', 'description' => 'Party-style jollof rice with grilled chicken and slaw.', 'price' => '12500', 'image' => 'food-mains.jpg'],
                ['name' => 'Grilled Chicken Bowl', 'description' => 'Herb-marinated chicken, rice and fresh house salad.', 'price' => '15500', 'image' => 'food-mains.jpg'],
            ],
            'Drinks' => [
                ['name' => 'Zobo Cooler', 'description' => 'Chilled hibiscus, pineapple and a hint of ginger.', 'price' => '2500', 'image' => 'food-drinks.jpg'],
                ['name' => 'Fresh Citrus Lemonade', 'description' => 'Freshly squeezed citrus with mint.', 'price' => '3000', 'image' => 'food-drinks.jpg'],
            ],
            'Desserts' => [
                ['name' => 'Chocolate Celebration Cake', 'description' => 'Rich chocolate cake with smooth vanilla cream.', 'price' => '5500', 'image' => 'food-desserts.jpg'],
                ['name' => 'Vanilla Bean Cheesecake', 'description' => 'Creamy baked cheesecake with a crisp biscuit base.', 'price' => '6500', 'image' => 'food-desserts.jpg'],
            ],
            'Sides' => [
                ['name' => 'Fried Plantain', 'description' => 'Golden ripe plantain, fried to order.', 'price' => '3000', 'image' => 'food-appetizers.jpg'],
                ['name' => 'Seasoned Fries', 'description' => 'Crispy fries tossed with house seasoning.', 'price' => '3500', 'image' => 'food-appetizers.jpg'],
            ],
        ];

        $currency = $business->currency;
        $sortOrder = 0;

        foreach ($starterMenu as $categoryName => $items) {
            $category = MenuCategory::firstOrCreate(
                ['business_id' => $business->id, 'name' => $categoryName],
                ['sort_order' => $sortOrder++, 'is_active' => true]
            );

            foreach ($items as $itemIndex => $itemData) {
                $imagePath = 'menu/stock/' . $itemData['image'];

                $menuItem = MenuItem::firstOrCreate(
                    ['business_id' => $business->id, 'menu_category_id' => $category->id, 'name' => $itemData['name']],
                    [
                        'description' => $itemData['description'],
                        'sort_order' => $itemIndex,
                        'is_active' => true,
                        'image_path' => $imagePath,
                    ]
                );

                // Ensure branch menu item exists with price
                BranchMenuItem::firstOrCreate(
                    ['branch_id' => $branch->id, 'menu_item_id' => $menuItem->id],
                    [
                        'price_minor' => (int) (floatval($itemData['price']) * 100), // Convert to kobo
                        'is_available' => true,
                        'is_visible' => true,
                    ]
                );
            }
        }

        $this->command->info('Menu seeded for ' . $business->name);
    }
}