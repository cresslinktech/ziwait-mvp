<?php

use App\Models\User;

$payload = fn (array $o = []) => ['endpoint' => 'https://fcm.googleapis.com/fcm/send/abc123', 'keys' => ['p256dh' => 'BPubKey', 'auth' => 'AuthSecret'], 'content_encoding' => 'aes128gcm', ...$o];

it('requires login to subscribe', function () use ($payload) {
    $this->postJson('/dashboard/push-subscription', $payload())->assertUnauthorized();
});

it('stores a subscription for the signed-in member', function () use ($payload) {
    [, $owner] = makeTenant(1);

    $this->actingAs($owner)->postJson('/dashboard/push-subscription', $payload())->assertOk();

    expect($owner->pushSubscriptions()->count())->toBe(1)
        ->and($owner->pushSubscriptions()->first()->endpoint)->toBe('https://fcm.googleapis.com/fcm/send/abc123');
});

it('rejects insecure or malformed subscriptions', function () use ($payload) {
    [, $owner] = makeTenant(1);
    $this->actingAs($owner);

    $this->postJson('/dashboard/push-subscription', $payload(['endpoint' => 'http://insecure.example.com/x']))->assertJsonValidationErrors('endpoint');
    $this->postJson('/dashboard/push-subscription', $payload(['endpoint' => 'javascript:alert(1)']))->assertJsonValidationErrors('endpoint');
    $this->postJson('/dashboard/push-subscription', $payload(['keys' => []]))->assertJsonValidationErrors(['keys.p256dh', 'keys.auth']);
    expect($owner->pushSubscriptions()->count())->toBe(0);
});

it('unsubscribes only the callers own subscription', function () use ($payload) {
    [, $owner] = makeTenant(1);
    $other = User::factory()->create();
    $other->updatePushSubscription('https://fcm.googleapis.com/fcm/send/theirs', 'k', 't', 'aes128gcm');

    $this->actingAs($owner)->postJson('/dashboard/push-subscription', $payload())->assertOk();
    $this->deleteJson('/dashboard/push-subscription', ['endpoint' => 'https://fcm.googleapis.com/fcm/send/theirs'])->assertOk();
    expect($other->pushSubscriptions()->count())->toBe(1);

    $this->deleteJson('/dashboard/push-subscription', ['endpoint' => 'https://fcm.googleapis.com/fcm/send/abc123'])->assertOk();
    expect($owner->pushSubscriptions()->count())->toBe(0);
});

it('shows the alerts card on the notifications page with the public VAPID key and serves the service worker', function () {
    [, $owner] = makeTenant(1);
    config(['webpush.vapid.public_key' => 'TESTPUBLICKEY']);

    $this->actingAs($owner)->get('/dashboard/notifications')->assertSee('data-push', false)->assertSee('TESTPUBLICKEY', false)->assertDontSee(config('webpush.vapid.private_key') ?: 'no-private-key-set');
    expect(file_exists(public_path('sw.js')))->toBeTrue();
});
