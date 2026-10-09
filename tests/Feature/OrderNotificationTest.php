<?php

use App\Domain\Notifications\NotificationLog;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Notifications\OrderPlacedNotification;
use App\Domain\Tenancy\Role;
use Illuminate\Support\Facades\Notification;
use NotificationChannels\WebPush\WebPushChannel;

function submitOrder($branch, $business)
{
    $offer = makeOffer($business, $branch, 1000);

    return test()->post('/m/'.qrFor($branch)->token.'/order', orderPayload([$offer->menu_item_id => 1]));
}

it('alerts the owner and authorised team of the right branch only', function () {
    Notification::fake();
    [$business, $owner, $branches] = makeTenant(2);
    $manager = makeMember($business, Role::Manager, [$branches[0]]);
    $otherManager = makeMember($business, Role::Manager, [$branches[1]]);
    $staffNo = makeMember($business, Role::Staff, [$branches[0]]);
    $staffYes = makeMember($business, Role::Staff, [$branches[0]]);
    $staffYes->branchAssignments()->updateExistingPivot($branches[0]->id, ['permissions' => json_encode(['orders'])]);

    submitOrder($branches[0], $business);

    Notification::assertSentTo([$owner, $manager, $staffYes], OrderPlacedNotification::class);
    Notification::assertNotSentTo([$otherManager, $staffNo], OrderPlacedNotification::class);
});

it('sends push only to members with a subscription and email only to those without', function () {
    Notification::fake();
    [$business, $owner, $branches] = makeTenant(1);
    $manager = makeMember($business, Role::Manager, [$branches[0]]);
    $owner->updatePushSubscription('https://push.example.com/abc', 'pubkey', 'authtoken', 'aes128gcm');

    submitOrder($branches[0], $business);

    Notification::assertSentTo($owner, OrderPlacedNotification::class, fn ($n, $channels) => $channels === [WebPushChannel::class]);
    Notification::assertSentTo($manager, OrderPlacedNotification::class, fn ($n, $channels) => $channels === ['mail']);
});

it('logs each delivery attempt', function () {
    [$business, $owner, $branches] = makeTenant(1);
    submitOrder($branches[0], $business);

    expect(NotificationLog::where(['user_id' => $owner->id, 'channel' => 'mail', 'status' => 'sent'])->count())->toBe(1);
});

it('keeps the order and records a failure when notification delivery breaks', function () {
    [$business, $owner, $branches] = makeTenant(1);
    Notification::partialMock()->shouldReceive('sendNow')->andThrow(new RuntimeException('smtp down'));

    submitOrder($branches[0], $business)->assertRedirect();

    expect(Order::count())->toBe(1);
    $log = NotificationLog::first();
    expect($log->status)->toBe('failed')->and($log->error)->toContain('smtp down')->and($log->user_id)->toBe($owner->id);
});

it('does not alert disabled users', function () {
    Notification::fake();
    [$business, $owner, $branches] = makeTenant(1);
    $owner->forceFill(['disabled_at' => now()])->save();

    submitOrder($branches[0], $business);

    Notification::assertNothingSent();
});

it('renders the email with order details and no payment claim', function () {
    [$business, $owner, $branches] = makeTenant(1);
    submitOrder($branches[0], $business);
    $order = Order::first();

    $mail = (new OrderPlacedNotification($order))->toMail($owner);
    $text = implode("\n", [$mail->subject, ...array_map(fn ($l) => (string) $l, $mail->introLines)]);

    expect($text)->toContain($order->reference)->toContain('Table 12')->toContain('payment is handled directly');
});
