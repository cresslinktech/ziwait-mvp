<?php

namespace App\Http\Controllers\Dashboard;

use App\Domain\Administration\Models\AuditLog;
use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Billing\SubscriptionStatus;
use App\Domain\Menus\Actions\StoreMenuImage;
use App\Domain\Menus\Models\BranchMenuItem;
use App\Domain\Menus\Models\MenuCategory;
use App\Domain\Menus\Models\MenuItem;
use App\Domain\Menus\Models\QrLink;
use App\Domain\Tenancy\Actions\SaveBusinessProfile;
use App\Domain\Tenancy\Models\Branch;
use App\Http\Controllers\Controller;
use App\Http\Requests\BusinessProfileRequest;
use App\Support\MoneyFormat;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Five-step setup for a new business. Only the owner can run it; later steps cannot be skipped to. */
class OnboardingController extends Controller
{
    public const STEPS = [1 => 'Business Details', 2 => 'Branch Information', 3 => 'Menu Setup', 4 => 'Subscription', 5 => 'Complete'];

    public const SUGGESTED_CATEGORIES = ['Appetizers', 'Main Courses', 'Drinks', 'Desserts', 'Sides'];

    private const STARTER_MENU = [
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

    public function index(Request $request): RedirectResponse
    {
        $business = $this->business($request);
        $currentStep = max(1, (int) ($business->onboarding_step ?? 1));

        return $business->isOnboarded() ? redirect()->route('dashboard') : redirect()->route('onboarding.show', min($currentStep, 5));
    }

    public function show(Request $request, int $step)
    {
        $business = $this->business($request);
        $this->guard($business, $step);

        $currency = $business->currency;
        $data = ['business' => $business, 'step' => $step, 'steps' => self::STEPS, 'branches' => $business->branches()->orderBy('id')->get()];

        if ($step === 3) {
            $data['suggested'] = self::SUGGESTED_CATEGORIES;
            $data['existing'] = MenuCategory::where('business_id', $business->id)->pluck('name')->all();
            $data['menuPreview'] = collect(self::STARTER_MENU)->map(fn ($items) => collect($items)->map(fn ($item) => [
                ...$item,
                'price_display' => MoneyFormat::display(MoneyFormat::toMinor($item['price'], $currency), $currency),
                'image_url' => asset('images/stock/'.$item['image']),
            ]))->all();
            $data['previewBranch'] = $business->branches()->orderBy('id')->first();
        }
        if ($step >= 3) {
            $previewBranch = $business->branches()->orderBy('id')->first();
            $data['menuPreviewUrl'] = $previewBranch && MenuItem::where('business_id', $business->id)->exists()
                ? QrLink::currentFor($previewBranch)->url()
                : null;
        }
        if ($step >= 4) {
            $data['subscription'] = Subscription::where('business_id', $business->id)->with('plan')->latest('id')->first();
            $data['plans'] = Plan::where('is_active', true)->with(['entitlements', 'prices' => fn ($q) => $q->where('is_active', true)->where('currency', $currency)->where('interval', 'month')])->orderBy('sort_order')->get()->filter(fn ($p) => $p->prices->isNotEmpty());
        }

        return view('onboarding.step', $data);
    }

    public function store(Request $request, int $step)
    {
        $business = $this->business($request);
        $this->guard($business, $step);

        match ($step) {
            1 => $this->saveDetails($request, $business),
            2 => $this->requireBranch($business),
            3 => $this->saveMenu($request, $business),
            4 => $this->choosePlan($request, $business),
            5 => $this->finish($business),
        };

        if ($step === 5) {
            return redirect()->route('dashboard')->with('status', 'Your restaurant is ready. Welcome to ZIWAIT!');
        }

        $business->forceFill(['onboarding_step' => max((int) ($business->onboarding_step ?? 1), $step + 1)])->save();

        return redirect()->route('onboarding.show', $step + 1);
    }

    public function addBranch(Request $request, StoreMenuImage $storeImage): RedirectResponse
    {
        $business = $this->business($request);
        $this->guard($business, 2);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'address' => ['required', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:32', 'regex:/^[0-9+()\-\s]{6,32}$/'],
            'timezone' => ['required', 'timezone:all'],
            'hours_status' => ['required', 'in:open,closed'],
            'open_time' => ['required_if:hours_status,open', 'nullable', 'date_format:H:i'],
            'close_time' => ['required_if:hours_status,open', 'nullable', 'date_format:H:i'],
            'photo' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:max_width=6000,max_height=6000'],
        ]);

        // The plan's branch limit is checked when a plan is chosen (step 4), so owners can list every branch first.
        $branch = new Branch(collect($data)->only(['name', 'address', 'city', 'phone', 'timezone'])->all());
        $branch->business_id = $business->id;
        $branch->slug = $this->uniqueSlug($business->id, $data['name']);
        $branch->settings = ['hours' => ['status' => $data['hours_status'], 'open' => $data['open_time'] ?? null, 'close' => $data['close_time'] ?? null]];
        if ($request->file('photo')) {
            $branch->photo_path = $storeImage($request->file('photo'), 'branches', 1600);
        }
        $branch->save();
        AuditLog::record('branch.created', $branch, [], $business->id);

        return redirect()->route('onboarding.show', 2)->with('status', 'Branch added.');
    }

    public function removeBranch(Request $request, Branch $branch): RedirectResponse
    {
        $business = $this->business($request);
        $this->guard($business, 2);
        $this->ensureTenant($branch);
        abort_if($branch->business_id !== $business->id, 404);

        $branch->delete();
        AuditLog::record('branch.deleted', $branch, [], $business->id);

        return redirect()->route('onboarding.show', 2)->with('status', 'Branch removed.');
    }

    private function saveDetails(Request $request, $business): void
    {
        $validated = $request->validate((new BusinessProfileRequest)->rules());

        app(SaveBusinessProfile::class)(
            $business,
            collect($validated)->except(['logo', 'cover_photo'])->all(),
            $request->file('logo'),
            $request->file('cover_photo'),
        );
    }

    private function requireBranch($business): void
    {
        if (! $business->branches()->exists()) {
            throw ValidationException::withMessages(['name' => 'Add at least one branch to continue.']);
        }
    }

    private function saveMenu(Request $request, $business): void
    {
        $data = $request->validate([
            'categories' => ['nullable', 'array', 'max:12'],
            'categories.*' => ['string', 'in:'.implode(',', self::SUGGESTED_CATEGORIES)],
            'custom_category' => ['nullable', 'string', 'max:100'],
        ]);

        $names = collect($data['categories'] ?? [])->when($request->filled('custom_category'), fn ($c) => $c->push(trim($request->input('custom_category'))))->unique();
        $existing = MenuCategory::where('business_id', $business->id)->pluck('name')->map(fn ($n) => Str::lower($n));

        foreach ($names->reject(fn ($n) => $existing->contains(Str::lower($n)))->values() as $i => $name) {
            $category = new MenuCategory(['name' => $name, 'sort_order' => $i]);
            $category->business_id = $business->id;
            $category->save();
        }

        $branchIds = $business->branches()->pluck('id');
        foreach ($names as $name) {
            $starterItems = self::STARTER_MENU[$name] ?? [];
            $category = MenuCategory::where('business_id', $business->id)->where('name', $name)->first();

            if (! $category || ! $starterItems || $category->items()->exists()) {
                continue;
            }

            foreach ($starterItems as $sortOrder => $starter) {
                $item = new MenuItem([
                    'name' => $starter['name'],
                    'description' => $starter['description'],
                    'sort_order' => $sortOrder,
                ]);
                $item->business_id = $business->id;
                $item->menu_category_id = $category->id;

                $sourceImage = public_path('images/stock/'.$starter['image']);
                if (is_file($sourceImage)) {
                    $imagePath = 'menu/starter/'.Str::random(40).'.jpg';
                    Storage::disk('public')->put($imagePath, file_get_contents($sourceImage));
                    $item->image_path = $imagePath;
                }
                $item->save();

                foreach ($branchIds as $branchId) {
                    BranchMenuItem::create([
                        'branch_id' => $branchId,
                        'menu_item_id' => $item->id,
                        'price_minor' => MoneyFormat::toMinor($starter['price'], $business->currency),
                    ]);
                }
            }
        }
    }

    private function choosePlan(Request $request, $business): void
    {
        $plan = Plan::where('is_active', true)->with('entitlements')->findOrFail($request->validate(['plan_id' => ['required', 'integer', 'exists:plans,id']])['plan_id']);
        $branches = $business->branches()->count();
        $max = $plan->maxBranches();

        if ($max !== null && $branches > $max) {
            throw ValidationException::withMessages(['plan_id' => "You added {$branches} branches, but {$plan->name} allows {$max}. Pick a larger plan or remove a branch."]);
        }

        // Choosing a plan during setup selects the trial plan; payment happens later from Billing.
        $subscription = Subscription::where('business_id', $business->id)->latest('id')->first();
        if ($subscription && $subscription->status === SubscriptionStatus::Trialing) {
            $subscription->forceFill(['plan_id' => $plan->id])->save();
        } elseif (! $subscription) {
            $days = max(1, (int) config('billing.trial_days'));
            $subscription = new Subscription;
            $subscription->forceFill(['business_id' => $business->id, 'plan_id' => $plan->id, 'status' => SubscriptionStatus::Trialing, 'current_period_start' => now(), 'current_period_end' => now()->addDays($days)])->save();
        }
        // An already-paid subscription is never changed here.

        AuditLog::record('onboarding.plan_selected', $business, ['plan' => $plan->code], $business->id);
    }

    private function finish($business): void
    {
        abort_unless($business->branches()->exists(), 422);
        $business->forceFill(['onboarding_completed_at' => now(), 'onboarding_step' => 5])->save();
        AuditLog::record('onboarding.completed', $business, [], $business->id);
    }

    private function business(Request $request)
    {
        $business = $request->attributes->get('business');
        abort_unless($request->user()->isOwnerOf($business), 403);

        return $business;
    }

    private function guard($business, int $step): void
    {
        abort_unless(isset(self::STEPS[$step]), 404);
        abort_if($business->isOnboarded() && $step < 5, 404);
        abort_if($step > (int) ($business->onboarding_step ?? 1), 403, 'Finish the earlier steps first.');
    }

    private function uniqueSlug(int $businessId, string $name): string
    {
        $base = Str::slug($name) ?: 'branch';
        $slug = $base;
        $n = 2;
        while (Branch::withTrashed()->where('business_id', $businessId)->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$n++;
        }

        return $slug;
    }
}
