<?php

use App\Domain\Administration\Models\AuditLog;
use App\Domain\Billing\Actions\SettlePayment;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\ManualPaymentReview;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Billing\PaymentStatus;
use App\Models\User;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    billingSetup();
    Notification::fake();
});

function superAdmin(): User
{
    $admin = User::factory()->create();
    $admin->forceFill(['is_super_admin' => true])->save();

    return $admin;
}

function flagged(string $reasonOverride = 'amount'): array
{
    [$business, $owner] = makeTenant(1);
    Subscription::query()->delete();
    $payment = makePayment($business);
    fakeVerify($payment, ['amount' => 100]);
    app(SettlePayment::class)($payment->reference);

    return [$business, $payment->fresh(), ManualPaymentReview::first()];
}

it('keeps the reconciliation queue away from business users', function () {
    [$business, $owner] = makeTenant(1);
    $payment = makePayment($business);
    $review = new ManualPaymentReview;
    $review->forceFill(['payment_id' => $payment->id, 'reason' => 'other'])->save();

    $this->actingAs($owner)->get('/admin/payments')->assertForbidden();
    $this->post("/admin/payments/reviews/{$review->id}", ['decision' => 'approve', 'note' => 'sneaky'])->assertForbidden();
    $this->post("/admin/payments/{$payment->id}/recheck")->assertForbidden();
    expect($review->fresh()->status)->toBe('open');
});

it('lists open cases for a super admin', function () {
    [, $payment] = flagged();

    $this->actingAs(superAdmin())->get('/admin/payments')->assertOk()->assertSee($payment->reference)->assertSee('amount mismatch');
});

it('lets an admin approve a flagged payment, activating it with a full audit trail', function () {
    [$business, $payment, $review] = flagged();
    $admin = superAdmin();

    $this->actingAs($admin)->post("/admin/payments/reviews/{$review->id}", ['decision' => 'approve', 'note' => 'Customer sent proof of transfer for the balance'])->assertSessionHasNoErrors();

    $review->refresh();
    expect($payment->fresh()->status)->toBe(PaymentStatus::Successful)->and(Invoice::count())->toBe(1)
        ->and($review->status)->toBe('approved')->and($review->resolved_by)->toBe($admin->id)->and($review->resolution_note)->toContain('proof of transfer')
        ->and(Subscription::where('business_id', $business->id)->latest('id')->first()->status->value)->toBe('active');
    $log = AuditLog::where('action', 'payment.review.approved')->first();
    expect($log->user_id)->toBe($admin->id)->and($log->business_id)->toBe($business->id)->and($log->changes['note'])->toContain('proof');
    expect(AuditLog::where('action', 'payment.succeeded')->first()->changes['manual_approval'])->toBeTrue();
});

it('lets an admin reject a flagged payment without activating anything', function () {
    [$business, $payment, $review] = flagged();

    $this->actingAs(superAdmin())->post("/admin/payments/reviews/{$review->id}", ['decision' => 'reject', 'note' => 'Amount does not match any invoice'])->assertSessionHasNoErrors();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Failed)->and($review->fresh()->status)->toBe('rejected')
        ->and(Invoice::count())->toBe(0)->and(Subscription::count())->toBe(0);
    $this->assertDatabaseHas('audit_logs', ['action' => 'payment.review.rejected']);
});

it('requires a meaningful note and a valid decision', function () {
    [, , $review] = flagged();
    $this->actingAs(superAdmin());

    $this->post("/admin/payments/reviews/{$review->id}", ['decision' => 'approve'])->assertSessionHasErrors('note');
    $this->post("/admin/payments/reviews/{$review->id}", ['decision' => 'approve', 'note' => 'ok'])->assertSessionHasErrors('note');
    $this->post("/admin/payments/reviews/{$review->id}", ['decision' => 'maybe', 'note' => 'long enough note'])->assertSessionHasErrors('decision');
    expect($review->fresh()->status)->toBe('open');
});

it('refuses to resolve the same case twice', function () {
    [, , $review] = flagged();
    $this->actingAs(superAdmin());

    $this->post("/admin/payments/reviews/{$review->id}", ['decision' => 'approve', 'note' => 'first decision']);
    $this->post("/admin/payments/reviews/{$review->id}", ['decision' => 'approve', 'note' => 'second decision'])->assertSessionHasErrors('review');

    expect(Invoice::count())->toBe(1);
});

it('marks a payment refunded when an admin approves a refund case', function () {
    [$business] = makeTenant(1);
    $payment = makePayment($business, null, PaymentStatus::Successful);
    $review = new ManualPaymentReview;
    $review->forceFill(['payment_id' => $payment->id, 'reason' => 'refund_event'])->save();

    $this->actingAs(superAdmin())->post("/admin/payments/reviews/{$review->id}", ['decision' => 'approve', 'note' => 'Refund confirmed in dashboard']);

    expect($payment->fresh()->status)->toBe(PaymentStatus::Refunded);
});

it('lets an admin re-check a stuck pending payment', function () {
    [$business] = makeTenant(1);
    $payment = makePayment($business);
    fakeVerify($payment);

    $this->actingAs(superAdmin())->post("/admin/payments/{$payment->id}/recheck")->assertSessionHas('status');
    expect($payment->fresh()->status)->toBe(PaymentStatus::Successful);
});

it('does not allow a disabled super admin to resolve cases', function () {
    [, , $review] = flagged();
    $admin = superAdmin();
    $admin->forceFill(['disabled_at' => now()])->save();

    $this->actingAs($admin)->post("/admin/payments/reviews/{$review->id}", ['decision' => 'approve', 'note' => 'should not work'])->assertForbidden();
    expect($review->fresh()->status)->toBe('open');
});
