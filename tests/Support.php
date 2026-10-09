<?php

use App\Domain\Tenancy\Models\Branch;
use App\Domain\Tenancy\Models\Business;
use App\Domain\Tenancy\Role;
use App\Models\User;
use Illuminate\Support\Str;

/** A business with an owner and two branches. */
function makeTenant(int $branches = 2): array
{
    $business = Business::factory()->create();
    $owner = User::factory()->create();
    $business->memberships()->create(['user_id' => $owner->id, 'role' => Role::Owner]);
    $list = Branch::factory()->count($branches)->create(['business_id' => $business->id]);
    giveSubscription($business);

    return [$business, $owner, $list];
}

function makeMember(Business $business, Role $role, array $branches = []): User
{
    $user = User::factory()->create();
    $business->memberships()->create(['user_id' => $user->id, 'role' => $role]);
    foreach ($branches as $branch) {
        $user->branchAssignments()->attach($branch->id);
    }

    return $user;
}

use App\Domain\Menus\Models\BranchMenuItem;
use App\Domain\Menus\Models\MenuCategory;
use App\Domain\Menus\Models\MenuItem;
use App\Domain\Menus\Models\QrLink;

/** Creates an item offered at the given branch(es) at the given price (minor units). */
function makeOffer(Business $business, Branch $branch, int $priceMinor, array $itemAttrs = [], ?MenuCategory $category = null): BranchMenuItem
{
    $category ??= MenuCategory::factory()->create(['business_id' => $business->id]);
    $item = MenuItem::factory()->create(['menu_category_id' => $category->id, 'business_id' => $business->id, ...$itemAttrs]);

    return BranchMenuItem::create(['branch_id' => $branch->id, 'menu_item_id' => $item->id, 'price_minor' => $priceMinor]);
}

function qrFor(Branch $branch): QrLink
{
    return QrLink::currentFor($branch);
}

function orderPayload(array $qty, array $extra = []): array
{
    return ['table_number' => '12', 'qty' => $qty, ...$extra];
}


use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Billing\SubscriptionStatus;

/** A plan with a monthly and a yearly NGN price. $maxBranches: number as string, or 'unlimited'. */
function makePlan(string $code = 'testplan', string $maxBranches = 'unlimited', int $monthly = 1500000): Plan
{
    $plan = Plan::firstOrCreate(['code' => $code], ['name' => ucfirst($code), 'sort_order' => 1, 'is_active' => true]);
    $plan->prices()->updateOrCreate(['interval' => 'month', 'currency' => 'NGN'], ['amount_minor' => $monthly, 'is_active' => true]);
    $plan->prices()->updateOrCreate(['interval' => 'year', 'currency' => 'NGN'], ['amount_minor' => $monthly * 10, 'is_active' => true]);
    $plan->entitlements()->updateOrCreate(['key' => 'max_branches'], ['value' => $maxBranches]);

    return $plan->fresh(['prices', 'entitlements']);
}

function giveSubscription(Business $business, SubscriptionStatus $status = SubscriptionStatus::Active, ?Plan $plan = null, array $attrs = []): Subscription
{
    $plan ??= makePlan();
    $subscription = new Subscription;
    $subscription->forceFill([
        'business_id' => $business->id,
        'plan_id' => $plan->id,
        'status' => $status,
        'current_period_start' => now()->subDays(5),
        'current_period_end' => now()->addDays(25),
        ...$attrs,
    ])->save();

    return $subscription;
}

use App\Domain\Billing\Models\Payment;
use App\Domain\Billing\Models\PlanPrice;
use App\Domain\Billing\PaymentStatus;
use Illuminate\Support\Facades\Http;

function billingSetup(): void
{
    config(['billing.paystack.secret_key' => 'sk_test_secret', 'billing.paystack.base_url' => 'https://api.paystack.co']);
}

function makePayment(Business $business, ?PlanPrice $price = null, PaymentStatus $status = PaymentStatus::Pending, array $attrs = []): Payment
{
    $price ??= makePlan()->prices->firstWhere('interval', 'month');
    $payment = new Payment;
    $payment->forceFill([
        'business_id' => $business->id,
        'plan_price_id' => $price->id,
        'gateway' => 'paystack',
        'reference' => 'ZWP-'.strtoupper(Str::random(20)),
        'amount_minor' => $price->amount_minor,
        'currency' => $price->currency,
        'status' => $status,
        ...$attrs,
    ])->save();

    return $payment;
}

/** Fakes Paystack's verify endpoint for one payment; $over overrides fields of data. */
function fakeVerify(Payment $payment, array $over = [], int $httpStatus = 200): void
{
    // Http::fake() stubs accumulate and the first match wins, so start from a clean factory each time.
    Http::clearResolvedInstance(\Illuminate\Http\Client\Factory::class);
    app()->forgetInstance(\Illuminate\Http\Client\Factory::class);
    Http::fake(['api.paystack.co/transaction/verify/*' => Http::response([
        'status' => true,
        'message' => 'Verification successful',
        'data' => [
            'id' => 690075529, 'status' => 'success', 'reference' => $payment->reference,
            'amount' => $payment->amount_minor, 'currency' => $payment->currency, 'paid_at' => now()->toIso8601String(),
            ...$over,
        ],
    ], $httpStatus)]);
}

/** POSTs a webhook signed the way Paystack signs it (HMAC-SHA512 of the raw body). */
function postWebhook($test, array|string $payload, ?string $signature = 'valid')
{
    $raw = is_string($payload) ? $payload : json_encode($payload);
    $signature = $signature === 'valid' ? hash_hmac('sha512', $raw, 'sk_test_secret') : $signature;
    $server = ['CONTENT_TYPE' => 'application/json'] + ($signature !== null ? ['HTTP_X_PAYSTACK_SIGNATURE' => $signature] : []);

    return $test->call('POST', '/webhooks/paystack', [], [], [], $server, $raw);
}

function chargeSuccess(Payment $payment, int $eventId = 690075529): array
{
    return ['event' => 'charge.success', 'data' => ['id' => $eventId, 'status' => 'success', 'reference' => $payment->reference, 'amount' => $payment->amount_minor, 'currency' => $payment->currency]];
}
