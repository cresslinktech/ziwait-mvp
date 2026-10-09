<?php

use App\Domain\Billing\Actions\EntitlementService;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Billing\Notifications\SubscriptionExpiredNotification;
use App\Domain\Billing\Notifications\SubscriptionExpiringNotification;
use App\Domain\Billing\SubscriptionStatus;
use App\Domain\Orders\Models\Order;
use App\Domain\Tenancy\Models\Branch;
use Illuminate\Support\Facades\Notification;

beforeEach(fn () => Notification::fake());

function sub($business): Subscription
{
    return Subscription::where('business_id', $business->id)->latest('id')->first();
}

it('moves an ended paid period into grace, then expires it after the grace window', function () {
    config(['billing.grace_days' => 7]);
    [$business, $owner] = makeTenant(1);
    sub($business)->forceFill(['current_period_end' => now()->subHour()])->save();

    $this->artisan('billing:process')->assertSuccessful();
    expect(sub($business)->status)->toBe(SubscriptionStatus::PastDue)
        ->and(sub($business)->grace_ends_at->toDateString())->toBe(now()->subHour()->addDays(7)->toDateString());

    sub($business)->forceFill(['grace_ends_at' => now()->subMinute()])->save();
    $this->artisan('billing:process');
    expect(sub($business)->status)->toBe(SubscriptionStatus::Expired);
    Notification::assertSentTo($owner, SubscriptionExpiredNotification::class);
});

it('keeps taking orders during grace and stops once expired', function () {
    [$business, , $branches] = makeTenant(1);
    $offer = makeOffer($business, $branches[0], 1000);
    $url = '/m/'.qrFor($branches[0])->token.'/order';
    sub($business)->forceFill(['status' => SubscriptionStatus::PastDue, 'grace_ends_at' => now()->addDays(2), 'current_period_end' => now()->subDays(5)])->save();

    $this->post($url, orderPayload([$offer->menu_item_id => 1]))->assertSessionHasNoErrors();
    expect(Order::count())->toBe(1);

    sub($business)->forceFill(['status' => SubscriptionStatus::Expired])->save();
    $this->post($url, orderPayload([$offer->menu_item_id => 1]))->assertSessionHasErrors('branch');
    expect(Order::count())->toBe(1);
});

it('expires a finished free trial immediately, without grace', function () {
    [$business] = makeTenant(1);
    sub($business)->forceFill(['status' => SubscriptionStatus::Trialing, 'current_period_end' => now()->subMinute()])->save();

    $this->artisan('billing:process');
    expect(sub($business)->status)->toBe(SubscriptionStatus::Expired);
});

it('expires straight through when the grace period is zero days', function () {
    config(['billing.grace_days' => 0]);
    [$business] = makeTenant(1);
    sub($business)->forceFill(['current_period_end' => now()->subMinute()])->save();

    $this->artisan('billing:process');
    expect(sub($business)->status)->toBe(SubscriptionStatus::Expired);
});

it('is idempotent and leaves healthy subscriptions alone', function () {
    [$business] = makeTenant(1);
    $this->artisan('billing:process');
    $this->artisan('billing:process');

    expect(sub($business)->status)->toBe(SubscriptionStatus::Active);
    Notification::assertNothingSent();
});

it('sends one renewal reminder to owners ahead of the end date', function () {
    config(['billing.reminder_days' => 3]);
    [$business, $owner] = makeTenant(1);
    sub($business)->forceFill(['current_period_end' => now()->addDays(2)])->save();

    $this->artisan('billing:process');
    $this->artisan('billing:process');

    Notification::assertSentToTimes($owner, SubscriptionExpiringNotification::class, 1);
    expect(sub($business)->renewal_reminded_at)->not->toBeNull();
});

it('limits branch creation to the plan allowance', function () {
    [$business, $owner, $branches] = makeTenant(1);
    sub($business)->forceFill(['plan_id' => makePlan('solo', '1')->id])->save();

    $this->actingAs($owner)->post('/dashboard/branches', ['name' => 'Second', 'slug' => 'second', 'timezone' => 'UTC'])->assertSessionHasErrors('name');
    expect(Branch::where('business_id', $business->id)->count())->toBe(1);

    sub($business)->forceFill(['plan_id' => makePlan('duo', '2')->id])->save();
    $this->post('/dashboard/branches', ['name' => 'Second', 'slug' => 'second', 'timezone' => 'UTC'])->assertSessionHasNoErrors();
    $this->post('/dashboard/branches', ['name' => 'Third', 'slug' => 'third', 'timezone' => 'UTC'])->assertSessionHasErrors('name');
});

it('allows unlimited branches on an unlimited plan', function () {
    [$business, $owner] = makeTenant(5);

    $this->actingAs($owner)->post('/dashboard/branches', ['name' => 'Sixth', 'slug' => 'sixth', 'timezone' => 'UTC'])->assertSessionHasNoErrors();
    expect(app(EntitlementService::class)->maxBranches($business))->toBeNull();
});

it('blocks branch creation when the subscription is not operational', function () {
    [$business, $owner] = makeTenant(1);
    sub($business)->forceFill(['status' => SubscriptionStatus::Expired])->save();

    $this->actingAs($owner)->post('/dashboard/branches', ['name' => 'New', 'slug' => 'new', 'timezone' => 'UTC'])->assertSessionHasErrors('name');
    expect(app(EntitlementService::class)->isOperational($business))->toBeFalse();
});

it('treats a business with no subscription as not operational', function () {
    [$business, , $branches] = makeTenant(1);
    Subscription::query()->delete();
    $offer = makeOffer($business, $branches[0], 1000);

    $this->post('/m/'.qrFor($branches[0])->token.'/order', orderPayload([$offer->menu_item_id => 1]))->assertSessionHasErrors('branch');
});

it('starts new registrations on a free trial when the trial plan exists', function () {
    makePlan('starter', '1');
    config(['billing.trial_days' => 14, 'billing.trial_plan' => 'starter']);

    $this->post('/register', ['name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'Secretpass12', 'password_confirmation' => 'Secretpass12', 'business_name' => 'Ada Kitchen']);

    $sub = Subscription::first();
    expect($sub->status)->toBe(SubscriptionStatus::Trialing)->and($sub->current_period_end->toDateString())->toBe(now()->addDays(14)->toDateString())
        ->and($sub->plan->code)->toBe('starter');
});

it('registers fine without any plan configured, leaving the business without a subscription', function () {
    $this->post('/register', ['name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'Secretpass12', 'password_confirmation' => 'Secretpass12', 'business_name' => 'Ada Kitchen'])->assertRedirect();

    expect(Subscription::count())->toBe(0);
});

it('shows a banner when the subscription needs attention', function () {
    [$business, $owner] = makeTenant(1);
    sub($business)->forceFill(['status' => SubscriptionStatus::PastDue, 'grace_ends_at' => now()->addDays(3), 'current_period_end' => now()->subDay()])->save();

    $this->actingAs($owner)->get('/dashboard')->assertSee('Renew by');
});

it('seeds the placeholder plans', function () {
    $this->seed(\Database\Seeders\PlanSeeder::class);
    $this->seed(\Database\Seeders\PlanSeeder::class); // re-running must not duplicate

    expect(\App\Domain\Billing\Models\Plan::count())->toBe(3)->and(\App\Domain\Billing\Models\PlanPrice::count())->toBe(6)
        ->and(\App\Domain\Billing\Models\Plan::firstWhere('code', 'business')->maxBranches())->toBe(10)
        ->and(\App\Domain\Billing\Models\Plan::firstWhere('code', 'professional')->maxBranches())->toBe(5)
        ->and(\App\Domain\Billing\Models\PlanPrice::whereRelation('plan', 'code', 'starter')->where('interval', 'month')->value('amount_minor'))->toBe(2500000);
});
