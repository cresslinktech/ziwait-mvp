<?php

use App\Domain\Billing\Actions\SettlePayment;
use App\Domain\Billing\Gateways\GatewayException;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\ManualPaymentReview;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Billing\Notifications\PaymentReceiptNotification;
use App\Domain\Billing\PaymentStatus;
use App\Domain\Billing\SubscriptionStatus;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    billingSetup();
    Notification::fake();
});

function settle(string $reference): string
{
    return app(SettlePayment::class)($reference);
}

function currentSub($business): Subscription
{
    return Subscription::where('business_id', $business->id)->latest('id')->first();
}

it('activates a subscription from a verified matching payment', function () {
    [$business, $owner] = makeTenant(1);
    Subscription::query()->delete();
    $payment = makePayment($business);
    fakeVerify($payment);

    expect(settle($payment->reference))->toBe('settled');

    $payment->refresh();
    $sub = currentSub($business);
    expect($payment->status)->toBe(PaymentStatus::Successful)
        ->and($payment->gateway_transaction_id)->toBe('690075529')
        ->and($payment->paid_at)->not->toBeNull()->and($payment->subscription_id)->toBe($sub->id)
        ->and($sub->status)->toBe(SubscriptionStatus::Active)
        ->and($sub->current_period_end->toDateString())->toBe(now()->addMonthNoOverflow()->toDateString())
        ->and($payment->invoice->number)->toMatch('/^ZW-INV-\d{6}$/')
        ->and($payment->invoice->amount_minor)->toBe($payment->amount_minor);
    Notification::assertSentTo($owner, PaymentReceiptNotification::class);
    $this->assertDatabaseHas('audit_logs', ['action' => 'payment.succeeded', 'business_id' => $business->id]);
});

it('settling twice never duplicates the invoice or extends the period twice', function () {
    [$business] = makeTenant(1);
    $payment = makePayment($business);
    fakeVerify($payment);

    expect(settle($payment->reference))->toBe('settled');
    $end = currentSub($business)->current_period_end;
    expect(settle($payment->reference))->toBe('already_settled');

    expect(Invoice::count())->toBe(1)->and(currentSub($business)->current_period_end->equalTo($end))->toBeTrue();
    Http::assertSentCount(1); // the second call never even asks the gateway
    Notification::assertSentTimes(PaymentReceiptNotification::class, 1);
});

it('sends a yearly payment for a full year', function () {
    [$business] = makeTenant(1);
    Subscription::query()->delete();
    $payment = makePayment($business, makePlan()->prices->firstWhere('interval', 'year'));
    fakeVerify($payment);
    settle($payment->reference);

    expect(currentSub($business)->current_period_end->toDateString())->toBe(now()->addYearNoOverflow()->toDateString());
});

it('adds to the remaining time when renewing the same plan early', function () {
    [$business] = makeTenant(1);
    $sub = currentSub($business);
    $end = $sub->current_period_end->copy();
    $payment = makePayment($business);
    fakeVerify($payment);
    settle($payment->reference);

    expect(currentSub($business)->id)->toBe($sub->id)
        ->and(currentSub($business)->current_period_end->toDateString())->toBe($end->addMonthNoOverflow()->toDateString());
});

it('starts a new period immediately when the plan changes or the subscription had lapsed', function () {
    [$business] = makeTenant(1);
    $other = makePlan('other', '9');
    $payment = makePayment($business, $other->prices->firstWhere('interval', 'month'));
    fakeVerify($payment);
    settle($payment->reference);

    $sub = currentSub($business);
    expect($sub->plan_id)->toBe($other->id)->and($sub->current_period_end->toDateString())->toBe(now()->addMonthNoOverflow()->toDateString());
});

it('turns a trial into a paid subscription', function () {
    [$business] = makeTenant(1);
    Subscription::query()->delete();
    giveSubscription($business, SubscriptionStatus::Trialing, null, ['current_period_end' => now()->addDays(3)]);
    $payment = makePayment($business);
    fakeVerify($payment);
    settle($payment->reference);

    expect(currentSub($business)->status)->toBe(SubscriptionStatus::Active)
        ->and(currentSub($business)->current_period_end->toDateString())->toBe(now()->addMonthNoOverflow()->toDateString());
});

it('sends mismatches to manual review instead of activating', function (string $reason, array $verifyOverride) {
    [$business] = makeTenant(1);
    $sub = currentSub($business);
    $end = $sub->current_period_end->copy();
    $payment = makePayment($business);
    fakeVerify($payment, $verifyOverride);

    expect(settle($payment->reference))->toBe('review');

    expect($payment->fresh()->status)->toBe(PaymentStatus::Review)
        ->and(ManualPaymentReview::first()->reason)->toBe($reason)->and(ManualPaymentReview::first()->status)->toBe('open')
        ->and(currentSub($business)->current_period_end->equalTo($end))->toBeTrue()
        ->and(Invoice::count())->toBe(0);
    Notification::assertNothingSent();
    $this->assertDatabaseHas('audit_logs', ['action' => 'payment.flagged_for_review']);
})->with([
    'underpayment' => ['amount_mismatch', ['amount' => 100]],
    'overpayment' => ['amount_mismatch', ['amount' => 99999999]],
    'wrong currency' => ['currency_mismatch', ['currency' => 'USD']],
    'wrong reference' => ['reference_mismatch', ['reference' => 'SOMETHING-ELSE']],
]);

it('flags a gateway transaction id already used by another payment', function () {
    [$business] = makeTenant(1);
    $first = makePayment($business);
    fakeVerify($first);
    settle($first->reference);

    $second = makePayment($business);
    fakeVerify($second); // same transaction id 690075529
    expect(settle($second->reference))->toBe('review');
    expect(ManualPaymentReview::first()->reason)->toBe('duplicate_transaction')->and(Invoice::count())->toBe(1);
});

it('records failed, abandoned and reversed transactions without activating', function (string $gatewayStatus) {
    [$business] = makeTenant(1);
    Subscription::query()->delete();
    $payment = makePayment($business);
    fakeVerify($payment, ['status' => $gatewayStatus]);

    expect(settle($payment->reference))->toBe('failed');

    expect($payment->fresh()->status)->toBe(PaymentStatus::Failed)->and($payment->fresh()->failure_reason)->toBe($gatewayStatus)->and(Subscription::count())->toBe(0);
})->with(['failed', 'abandoned', 'reversed']);

it('leaves a pending transaction pending', function () {
    [$business] = makeTenant(1);
    $payment = makePayment($business);
    fakeVerify($payment, ['status' => 'ongoing']);

    expect(settle($payment->reference))->toBe('pending')->and($payment->fresh()->status)->toBe(PaymentStatus::Pending);
});

it('settles a payment that failed earlier when the customer retries and succeeds', function () {
    [$business] = makeTenant(1);
    $payment = makePayment($business);
    fakeVerify($payment, ['status' => 'abandoned']);
    settle($payment->reference);
    fakeVerify($payment);

    expect(settle($payment->reference))->toBe('settled')->and($payment->fresh()->status)->toBe(PaymentStatus::Successful);
});

it('keeps the payment pending and throws when the gateway is unreachable', function () {
    [$business] = makeTenant(1);
    $payment = makePayment($business);
    Http::fake(['api.paystack.co/*' => Http::response('', 503)]);

    expect(fn () => settle($payment->reference))->toThrow(GatewayException::class);
    expect($payment->fresh()->status)->toBe(PaymentStatus::Pending);
});

it('reports unknown references as unmatched', function () {
    expect(settle('ZWP-DOESNOTEXIST'))->toBe('unmatched');
    Http::assertNothingSent();
});

it('never settles a payment that is under review or refunded', function () {
    [$business] = makeTenant(1);
    foreach ([PaymentStatus::Review, PaymentStatus::Refunded] as $status) {
        $payment = makePayment($business, null, $status);
        fakeVerify($payment);
        settle($payment->reference);
        expect($payment->fresh()->status)->toBe($status);
    }
    expect(Invoice::count())->toBe(0);
});

it('still activates when the receipt email fails', function () {
    [$business] = makeTenant(1);
    $payment = makePayment($business);
    fakeVerify($payment);

    // Use the real notification manager and make sending explode.
    Notification::clearResolvedInstance(\Illuminate\Notifications\ChannelManager::class);
    app()->forgetInstance(\Illuminate\Notifications\ChannelManager::class);
    Event::listen(NotificationSending::class, fn () => throw new RuntimeException('mail down'));

    expect(settle($payment->reference))->toBe('settled')->and($payment->fresh()->status)->toBe(PaymentStatus::Successful);
    expect(Invoice::count())->toBe(1);
});
