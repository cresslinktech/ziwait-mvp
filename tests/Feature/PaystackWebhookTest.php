<?php

use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\ManualPaymentReview;
use App\Domain\Billing\Models\PaymentEvent;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Billing\PaymentStatus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    billingSetup();
    Notification::fake();
});

it('rejects requests with a missing, wrong or malformed signature', function (?string $signature) {
    [$business] = makeTenant(1);
    $payment = makePayment($business);
    fakeVerify($payment);

    postWebhook($this, chargeSuccess($payment), $signature)->assertUnauthorized();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Pending)->and(PaymentEvent::count())->toBe(0);
    Http::assertNothingSent();
})->with([
    'missing' => [null],
    'wrong' => [str_repeat('a', 128)],
    'empty' => [''],
    'sha256 instead of sha512' => [hash_hmac('sha256', 'x', 'sk_test_secret')],
]);

it('rejects a body that was altered after signing', function () {
    [$business] = makeTenant(1);
    $payment = makePayment($business);
    $raw = json_encode(chargeSuccess($payment));
    $signature = hash_hmac('sha512', $raw, 'sk_test_secret');

    postWebhook($this, str_replace('"amount":', '"amount":1,"x":', $raw), $signature)->assertUnauthorized();
    expect($payment->fresh()->status)->toBe(PaymentStatus::Pending);
});

it('rejects everything when no secret key is configured', function () {
    config(['billing.paystack.secret_key' => null]);
    [$business] = makeTenant(1);
    $payment = makePayment($business);
    $raw = json_encode(chargeSuccess($payment));

    postWebhook($this, $raw, hash_hmac('sha512', $raw, ''))->assertUnauthorized();
});

it('settles a payment from a correctly signed charge.success event', function () {
    [$business] = makeTenant(1);
    Subscription::query()->delete();
    $payment = makePayment($business);
    fakeVerify($payment);

    postWebhook($this, chargeSuccess($payment))->assertOk()->assertJson(['outcome' => 'settled']);

    expect($payment->fresh()->status)->toBe(PaymentStatus::Successful)
        ->and(PaymentEvent::first()->outcome)->toBe('settled')->and(PaymentEvent::first()->processed_at)->not->toBeNull()
        ->and(Invoice::count())->toBe(1);
});

it('is idempotent: duplicate deliveries change nothing', function () {
    [$business] = makeTenant(1);
    $payment = makePayment($business);
    fakeVerify($payment);

    postWebhook($this, chargeSuccess($payment))->assertJson(['outcome' => 'settled']);
    $end = Subscription::where('business_id', $business->id)->latest('id')->first()->current_period_end;

    foreach (range(1, 3) as $_) {
        postWebhook($this, chargeSuccess($payment))->assertOk()->assertJson(['outcome' => 'duplicate']);
    }

    expect(Invoice::count())->toBe(1)->and(PaymentEvent::count())->toBe(1)
        ->and(Subscription::where('business_id', $business->id)->latest('id')->first()->current_period_end->equalTo($end))->toBeTrue();
    Http::assertSentCount(1);
    Notification::assertSentTimes(\App\Domain\Billing\Notifications\PaymentReceiptNotification::class, 1);
});

it('does not trust the webhook body: a signed success claim is checked with the gateway', function () {
    [$business] = makeTenant(1);
    $payment = makePayment($business);
    fakeVerify($payment, ['status' => 'failed']);

    postWebhook($this, chargeSuccess($payment))->assertOk()->assertJson(['outcome' => 'failed']);

    expect($payment->fresh()->status)->toBe(PaymentStatus::Failed)->and(Invoice::count())->toBe(0);
});

it('flags a webhook whose verified amount differs from what was expected', function () {
    [$business] = makeTenant(1);
    $payment = makePayment($business);
    fakeVerify($payment, ['amount' => 5000]);

    postWebhook($this, chargeSuccess($payment))->assertOk()->assertJson(['outcome' => 'review']);

    expect($payment->fresh()->status)->toBe(PaymentStatus::Review)->and(ManualPaymentReview::first()->reason)->toBe('amount_mismatch');
});

it('acknowledges events for unknown references without error', function () {
    $raw = ['event' => 'charge.success', 'data' => ['id' => 1, 'reference' => 'NOT-OURS', 'amount' => 1, 'currency' => 'NGN']];

    postWebhook($this, $raw)->assertOk()->assertJson(['outcome' => 'unmatched']);
    Http::assertNothingSent();
});

it('acknowledges and ignores other and malformed events', function () {
    postWebhook($this, ['event' => 'transfer.success', 'data' => ['id' => 5]])->assertOk()->assertJson(['outcome' => 'ignored']);
    postWebhook($this, 'not json at all')->assertOk()->assertJson(['outcome' => 'ignored_malformed']);
    postWebhook($this, ['data' => []])->assertOk()->assertJson(['outcome' => 'ignored_malformed']);
});

it('answers 500 when the gateway is down so Paystack retries, and the retry then succeeds', function () {
    [$business] = makeTenant(1);
    $payment = makePayment($business);
    fakeVerify($payment, [], 503);

    postWebhook($this, chargeSuccess($payment))->assertStatus(500);
    expect($payment->fresh()->status)->toBe(PaymentStatus::Pending)->and(PaymentEvent::first()->processed_at)->toBeNull();

    fakeVerify($payment);
    postWebhook($this, chargeSuccess($payment))->assertOk()->assertJson(['outcome' => 'settled']);
    expect($payment->fresh()->status)->toBe(PaymentStatus::Successful)->and(Invoice::count())->toBe(1)->and(PaymentEvent::count())->toBe(1);
});

it('queues refund events for human review once', function () {
    [$business] = makeTenant(1);
    $payment = makePayment($business, null, PaymentStatus::Successful);
    $event = ['event' => 'refund.processed', 'data' => ['id' => 77, 'transaction_reference' => $payment->reference]];

    postWebhook($this, $event)->assertJson(['outcome' => 'review']);
    postWebhook($this, $event)->assertJson(['outcome' => 'duplicate']);
    postWebhook($this, ['event' => 'refund.failed', 'data' => ['id' => 78, 'transaction_reference' => $payment->reference]]);

    expect(ManualPaymentReview::where('reason', 'refund_event')->count())->toBe(1)->and($payment->fresh()->status)->toBe(PaymentStatus::Successful);
});

it('can optionally restrict webhooks to Paystack source IPs', function () {
    config(['billing.paystack.enforce_ip_allowlist' => true]);
    [$business] = makeTenant(1);
    $payment = makePayment($business);
    fakeVerify($payment);

    postWebhook($this, chargeSuccess($payment))->assertForbidden();
    $this->withServerVariables(['REMOTE_ADDR' => '52.31.139.75']);
    postWebhook($this, chargeSuccess($payment))->assertOk();
});

it('does not require a CSRF token or a session', function () {
    $this->withMiddleware();
    postWebhook($this, ['event' => 'ping', 'data' => ['id' => 1]])->assertOk();
});
