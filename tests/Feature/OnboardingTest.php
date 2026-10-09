<?php

use App\Domain\Billing\Models\Subscription;
use App\Domain\Billing\SubscriptionStatus;
use App\Domain\Menus\Models\BranchMenuItem;
use App\Domain\Menus\Models\MenuCategory;
use App\Domain\Menus\Models\MenuItem;
use App\Domain\Menus\Models\QrLink;
use App\Domain\Tenancy\Models\Branch;
use App\Domain\Tenancy\Models\Business;
use App\Domain\Tenancy\Role;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function newOwner(array $plans = ['starter' => '1', 'professional' => '5']): array
{
    foreach ($plans as $code => $max) {
        makePlan($code, $max, $code === 'starter' ? 2500000 : 5000000);
    }
    config(['billing.trial_plan' => 'starter', 'billing.trial_days' => 14]);
    $owner = User::factory()->create();
    $business = Business::factory()->create(['business_type' => null, 'onboarding_step' => 1, 'onboarding_completed_at' => null]);
    $business->memberships()->create(['user_id' => $owner->id, 'role' => Role::Owner]);
    giveSubscription($business, SubscriptionStatus::Trialing, \App\Domain\Billing\Models\Plan::firstWhere('code', 'starter'), ['current_period_end' => now()->addDays(14)]);

    return [$business, $owner];
}

function branchPayload(array $o = []): array
{
    return ['name' => 'Victoria Island Branch', 'address' => '123 Adeola Odeku Street, Lagos', 'phone' => '+234 801 234 5678', 'timezone' => 'Africa/Lagos', 'hours_status' => 'open', 'open_time' => '08:00', 'close_time' => '23:00', ...$o];
}

it('sends an un-onboarded owner from the dashboard to the wizard', function () {
    [, $owner] = newOwner();

    $this->actingAs($owner)->get('/dashboard')->assertRedirect(route('onboarding.index'));
    $this->get('/dashboard/orders')->assertRedirect(route('onboarding.index'));
    $this->get('/onboarding')->assertRedirect(route('onboarding.show', 1));
});

it('walks the whole wizard and marks the business ready', function () {
    Storage::fake('public');
    [$business, $owner] = newOwner();
    $this->actingAs($owner);

    $this->get('/onboarding/1')->assertOk()->assertSee('Welcome to ZIWAIT')->assertSee('Step 1 of 5')->assertSee('Business Details')->assertSee('name="cover_photo"', false)->assertSee('id="business-logo-preview"', false)->assertSee('M9 5l7 7-7 7', false);
    $this->post('/onboarding/1', ['name' => 'The Green Bistro', 'business_type' => 'restaurant', 'description' => 'Fresh meals.', 'logo' => UploadedFile::fake()->image('logo.png', 300, 300), 'cover_photo' => UploadedFile::fake()->image('cover.jpg', 1200, 800)])->assertRedirect('/onboarding/2');
    $business->refresh();
    expect($business->name)->toBe('The Green Bistro')->and($business->slug)->toBe('the-green-bistro')->and($business->logo_path)->toEndWith('.webp')->and($business->cover_path)->toEndWith('.webp')->and($business->onboarding_step)->toBe(2);
    Storage::disk('public')->assertExists($business->logo_path);
    Storage::disk('public')->assertExists($business->cover_path);

    $this->get('/onboarding/2')->assertOk()->assertSee('Branch photo')->assertSee('name="photo"', false);
    $this->post('/onboarding/branches', branchPayload(['photo' => UploadedFile::fake()->image('branch.jpg', 800, 600)]))->assertRedirect('/onboarding/2');
    $branch = $business->branches()->first();
    expect($branch->photo_path)->toEndWith('.webp');
    Storage::disk('public')->assertExists($branch->photo_path);
    $this->post('/onboarding/2')->assertRedirect('/onboarding/3');
    $this->get('/onboarding/3')->assertOk()->assertSee('Customer view')->assertSee('Smoky Jollof Rice')->assertSee('₦12,500');
    $this->post('/onboarding/3', ['categories' => ['Appetizers', 'Drinks'], 'custom_category' => 'Specials'])->assertRedirect('/onboarding/4');
    expect(MenuCategory::where('business_id', $business->id)->pluck('name')->sort()->values()->all())->toBe(['Appetizers', 'Drinks', 'Specials']);
    expect(MenuItem::where('business_id', $business->id)->count())->toBe(4)
        ->and(BranchMenuItem::whereIn('menu_item_id', MenuItem::where('business_id', $business->id)->select('id'))->count())->toBe(4);
    expect(MenuItem::where('business_id', $business->id)->whereNotNull('image_path')->count())->toBe(4);
    $menuLink = QrLink::currentFor($business->branches()->first());
    expect($menuLink->url())->toBe(route('menu.show', $menuLink->token));
    $this->get('/onboarding/4')->assertOk()->assertSee($menuLink->url(), false)->assertSee('Preview your customer menu');
    $this->get($menuLink->url())->assertOk()->assertSee('Crispy Plantain Bites')->assertSee('Zobo Cooler')->assertSee('action="'.$menuLink->url().'/order"', false);

    $starter = \App\Domain\Billing\Models\Plan::firstWhere('code', 'starter');
    $this->post('/onboarding/4', ['plan_id' => $starter->id])->assertRedirect('/onboarding/5');
    $this->get('/onboarding/5')->assertOk()->assertSee('The Green Bistro')->assertSee('Free trial until');
    $this->post('/onboarding/5')->assertRedirect('/dashboard');

    expect($business->fresh()->isOnboarded())->toBeTrue();
    $this->get('/dashboard')->assertOk()->assertSee('Total Orders');
    $this->get('/onboarding/1')->assertNotFound(); // setup cannot be re-run
    $this->assertDatabaseHas('audit_logs', ['action' => 'onboarding.completed', 'business_id' => $business->id]);
});

it('does not let steps be skipped', function () {
    [, $owner] = newOwner();
    $this->actingAs($owner);

    $this->get('/onboarding/3')->assertForbidden();
    $this->post('/onboarding/5')->assertForbidden();
    $this->get('/onboarding/9')->assertNotFound();
});

it('validates business details and branches', function () {
    [$business, $owner] = newOwner();
    $this->actingAs($owner);

    $this->post('/onboarding/1', ['name' => '', 'business_type' => 'spaceship'])->assertSessionHasErrors(['name', 'business_type']);
    $this->post('/onboarding/1', ['name' => 'Ok', 'business_type' => 'cafe', 'logo' => UploadedFile::fake()->create('x.php', 5, 'application/x-php'), 'cover_photo' => UploadedFile::fake()->create('cover.php', 5, 'application/x-php')])->assertSessionHasErrors(['logo', 'cover_photo']);

    $business->forceFill(['onboarding_step' => 2])->save();
    $this->post('/onboarding/branches', branchPayload(['phone' => 'abc', 'timezone' => 'Mars/Base', 'photo' => UploadedFile::fake()->create('branch.php', 5, 'application/x-php')]))->assertSessionHasErrors(['phone', 'timezone', 'photo']);
    $this->post('/onboarding/branches', branchPayload(['open_time' => '25:99']))->assertSessionHasErrors('open_time');
    $this->post('/onboarding/2')->assertSessionHasErrors('name'); // needs at least one branch
    expect(Branch::where('business_id', $business->id)->count())->toBe(0);
});

it('stores operating hours and makes branch slugs unique', function () {
    [$business, $owner] = newOwner();
    $business->forceFill(['onboarding_step' => 2])->save();
    $this->actingAs($owner);

    $this->post('/onboarding/branches', branchPayload());
    $this->post('/onboarding/branches', branchPayload());
    $this->post('/onboarding/branches', branchPayload(['name' => 'Closed one', 'hours_status' => 'closed', 'open_time' => null, 'close_time' => null]));

    $branches = Branch::where('business_id', $business->id)->orderBy('id')->get();
    expect($branches->pluck('slug')->all())->toBe(['victoria-island-branch', 'victoria-island-branch-2', 'closed-one'])
        ->and($branches[0]->settings['hours'])->toBe(['status' => 'open', 'open' => '08:00', 'close' => '23:00'])
        ->and($branches[2]->settings['hours']['status'])->toBe('closed');
});

it('blocks a plan that allows fewer branches than were added', function () {
    [$business, $owner] = newOwner();
    $business->forceFill(['onboarding_step' => 4])->save();
    Branch::factory()->count(2)->create(['business_id' => $business->id]);
    $starter = \App\Domain\Billing\Models\Plan::firstWhere('code', 'starter');
    $pro = \App\Domain\Billing\Models\Plan::firstWhere('code', 'professional');

    $this->actingAs($owner)->post('/onboarding/4', ['plan_id' => $starter->id])->assertSessionHasErrors('plan_id');
    $this->post('/onboarding/4', ['plan_id' => $pro->id])->assertRedirect('/onboarding/5');
    expect(Subscription::where('business_id', $business->id)->latest('id')->first()->plan_id)->toBe($pro->id);
    $this->get('/onboarding/4')->assertSee('Allows 1 branch');
});

it('never alters an already paid subscription when a plan is picked in the wizard', function () {
    [$business, $owner] = newOwner();
    $business->forceFill(['onboarding_step' => 4])->save();
    $sub = Subscription::where('business_id', $business->id)->first();
    $sub->forceFill(['status' => SubscriptionStatus::Active])->save();
    $pro = \App\Domain\Billing\Models\Plan::firstWhere('code', 'professional');

    $this->actingAs($owner)->post('/onboarding/4', ['plan_id' => $pro->id])->assertRedirect('/onboarding/5');
    expect($sub->fresh()->plan->code)->toBe('starter');
});

it('rejects invented plan and category values', function () {
    [$business, $owner] = newOwner();
    $business->forceFill(['onboarding_step' => 4])->save();
    $this->actingAs($owner);

    $this->post('/onboarding/4', ['plan_id' => 99999])->assertSessionHasErrors('plan_id');
    $this->post('/onboarding/3', ['categories' => ['<script>']])->assertSessionHasErrors('categories.0');
});

it('keeps onboarding to the owner and to their own business', function () {
    [$business, $owner] = newOwner();
    $manager = makeMember($business, Role::Manager);
    $business->forceFill(['onboarding_step' => 2])->save();

    $this->actingAs($manager)->get('/onboarding/1')->assertForbidden();
    $this->post('/onboarding/branches', branchPayload())->assertForbidden();

    // a foreign branch id cannot be removed through the wizard
    [, , $others] = makeTenant(1);
    $this->actingAs($owner)->delete("/onboarding/branches/{$others[0]->id}")->assertNotFound();
    expect($others[0]->fresh())->not->toBeNull();
});

it('does not trap managers or staff of an unfinished business in the wizard', function () {
    [$business] = newOwner();
    $manager = makeMember($business, Role::Manager);

    $this->actingAs($manager)->get('/dashboard')->assertOk();
});

it('lets the owner edit the business profile later from Settings, and nobody else', function () {
    [$business, $owner, $branches] = makeTenant(1);
    $manager = makeMember($business, Role::Manager, [$branches[0]]);

    $this->actingAs($manager)->get('/dashboard/settings')->assertForbidden();
    $this->put('/dashboard/settings', ['name' => 'Hijacked', 'business_type' => 'bar'])->assertForbidden();

    $this->actingAs($owner)->put('/dashboard/settings', ['name' => 'Renamed Bistro', 'business_type' => 'cafe', 'description' => 'New text', 'status' => 'suspended', 'currency' => 'USD'])->assertSessionHasNoErrors();
    $business->refresh();
    expect($business->name)->toBe('Renamed Bistro')->and($business->status)->toBe('active')->and($business->currency)->toBe('NGN');
});
