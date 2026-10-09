<?php

use App\Domain\Billing\Models\Payment;
use App\Domain\Billing\PaymentStatus;
use App\Domain\Billing\SubscriptionStatus;
use App\Domain\Tenancy\Role;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    billingSetup();
    Notification::fake();
});

it('starts a hosted checkout using the server-side price, never a browser amount', function () {
    [$business, $owner] = makeTenant(1);
    $price = makePlan('pro', '5', 2500000)->prices->firstWhere('interval', 'month');
    Http::fake(['api.paystack.co/transaction/initialize' => Http::response(['status' => true, 'data' => ['authorization_url' => 'https://checkout.paystack.com/abc', 'access_code' => 'abc', 'reference' => 'x']])]);

    $this->actingAs($owner)->post('/dashboard/billing/checkout', ['plan_price_id' => $price->id, 'amount' => 1, 'amount_minor' => 1, 'currency' => 'USD'])
        ->assertRedirect('https://checkout.paystack.com/abc');

    $payment = Payment::first();
    expect($payment->amount_minor)->toBe(2500000)->and($payment->currency)->toBe('NGN')
        ->and($payment->status)->toBe(PaymentStatus::Pending)->and($payment->business_id)->toBe($business->id)
        ->and($payment->reference)->toStartWith('ZWP-');

    Http::assertSent(fn (Request $r) => $r->url() === 'https://api.paystack.co/transaction/initialize'
        && $r->hasHeader('Authorization', 'Bearer sk_test_secret')
        && $r['amount'] === 2500000 && $r['currency'] === 'NGN' && $r['email'] === $owner->email
        && $r['reference'] === $payment->reference && str_ends_with($r['callback_url'], '/dashboard/billing/callback'));
});

it('lets only the owner use billing', function () {
    [$business, , $branches] = makeTenant(1);
    $price = makePlan()->prices->first();
    $manager = makeMember($business, Role::Manager, [$branches[0]]);

    $this->actingAs($manager)->get('/dashboard/billing')->assertForbidden();
    $this->post('/dashboard/billing/checkout', ['plan_price_id' => $price->id])->assertForbidden();
    expect(Payment::count())->toBe(0);
});

it('rejects inactive prices, other currencies and unknown ids', function () {
    [, $owner] = makeTenant(1);
    $plan = makePlan();
    $monthly = $plan->prices->firstWhere('interval', 'month');
    $usd = $plan->prices()->create(['interval' => 'month', 'currency' => 'USD', 'amount_minor' => 1000, 'is_active' => true]);
    $this->actingAs($owner);

    $monthly->update(['is_active' => false]);
    $this->post('/dashboard/billing/checkout', ['plan_price_id' => $monthly->id])->assertSessionHasErrors('plan_price_id');
    $this->post('/dashboard/billing/checkout', ['plan_price_id' => $usd->id])->assertSessionHasErrors('plan_price_id');
    $this->post('/dashboard/billing/checkout', ['plan_price_id' => 99999])->assertSessionHasErrors('plan_price_id');
    expect(Payment::count())->toBe(0);
});

it('blocks moving to a plan with fewer branches than the business has', function () {
    [, $owner] = makeTenant(3);
    $small = makePlan('small', '1')->prices->first();

    $this->actingAs($owner)->post('/dashboard/billing/checkout', ['plan_price_id' => $small->id])->assertSessionHasErrors('plan_price_id');
    expect(Payment::count())->toBe(0);
});

it('fails safely when the gateway cannot start the transaction', function () {
    [, $owner] = makeTenant(1);
    $price = makePlan()->prices->first();
    Http::fake(['api.paystack.co/*' => Http::response(['status' => false, 'message' => 'Invalid key sk_test_secret'], 401)]);

    $this->actingAs($owner)->post('/dashboard/billing/checkout', ['plan_price_id' => $price->id])
        ->assertSessionHasErrors('plan_price_id')
        ->assertSessionMissing('error');

    expect(Payment::first()->status)->toBe(PaymentStatus::Failed);
    $this->get('/dashboard/billing')->assertDontSee('sk_test_secret');
});

it('does not treat the return redirect as proof of payment', function () {
    [$business, $owner] = makeTenant(1);
    $sub = giveSubscription($business, SubscriptionStatus::Expired, null, ['current_period_end' => now()->subDays(40)]);
    $payment = makePayment($business);
    fakeVerify($payment, ['status' => 'abandoned']);

    $this->actingAs($owner)->get('/dashboard/billing/callback?reference='.$payment->reference.'&trxref='.$payment->reference.'&status=success')
        ->assertRedirect('/dashboard/billing')->assertSessionHas('error');

    expect($payment->fresh()->status)->toBe(PaymentStatus::Failed)->and($sub->fresh()->status)->toBe(SubscriptionStatus::Expired);
});

it('activates after the return redirect only once the gateway confirms', function () {
    [$business, $owner] = makeTenant(1);
    giveSubscription($business, SubscriptionStatus::Expired, null, ['current_period_end' => now()->subDays(40)]);
    $payment = makePayment($business);
    fakeVerify($payment);

    $this->actingAs($owner)->get('/dashboard/billing/callback?reference='.$payment->reference)->assertRedirect('/dashboard/billing')->assertSessionHas('status');

    expect($payment->fresh()->status)->toBe(PaymentStatus::Successful)
        ->and(\App\Domain\Billing\Models\Subscription::where('business_id', $business->id)->latest('id')->first()->status)->toBe(SubscriptionStatus::Active);
});

it('returns 404 for the callback of another business payment', function () {
    [, $ownerA] = makeTenant(1);
    [$businessB] = makeTenant(1);
    $payment = makePayment($businessB);
    fakeVerify($payment);

    $this->actingAs($ownerA)->get('/dashboard/billing/callback?reference='.$payment->reference)->assertNotFound();
    $this->get('/dashboard/billing/callback?reference=nonsense')->assertNotFound();
    expect($payment->fresh()->status)->toBe(PaymentStatus::Pending);
});

it('shows plans and payment history to the owner', function () {
    [$business, $owner] = makeTenant(1);
    makePlan('growth', '5');
    makePayment($business, null, PaymentStatus::Failed);

    $this->actingAs($owner)->get('/dashboard/billing')->assertOk()->assertSee('Growth')->assertSee('Pay with Paystack')->assertSee('Failed');
});
