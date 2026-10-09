<?php

use App\Domain\Administration\Models\AuditLog;
use App\Domain\Billing\PaymentStatus;
use App\Domain\Billing\SubscriptionStatus;
use App\Domain\Marketing\Advertisement;
use App\Domain\Marketing\Enquiry;
use App\Domain\Reporting\PlatformOverview;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    billingSetup();
    Notification::fake();
});

function admin(): User
{
    $a = User::factory()->create(['name' => 'Root Admin']);
    $a->forceFill(['is_super_admin' => true])->save();

    return $a;
}

it('keeps every admin page away from guests and business users', function (string $path) {
    [, $owner] = makeTenant(1);

    $this->get($path)->assertRedirect('/login');
    $this->actingAs($owner)->get($path)->assertForbidden();
})->with(['/admin', '/admin/businesses', '/admin/branches', '/admin/users', '/admin/subscriptions', '/admin/audit-logs', '/admin/enquiries', '/admin/payments', '/admin/advertisements']);

it('renders every admin page for a super admin, even on an empty database', function (string $path) {
    $this->actingAs(admin())->get($path)->assertOk();
})->with(['/admin', '/admin/businesses', '/admin/branches', '/admin/users', '/admin/subscriptions', '/admin/audit-logs', '/admin/enquiries', '/admin/payments', '/admin/advertisements']);

it('lets super admins create, pause and delete menu advertisements', function () {
    Storage::fake('public');
    $this->actingAs(admin())->post('/admin/advertisements', [
        'placement' => 'home_banner',
        'title' => 'Friday Lunch Special',
        'body' => 'A real admin-written promotion.',
        'label' => 'Sponsored',
        'cta_label' => 'See offer',
        'target_url' => 'https://example.com/lunch',
        'image' => UploadedFile::fake()->image('lunch.jpg', 1200, 600),
        'sort_order' => 1,
        'is_active' => 1,
    ])->assertRedirect(route('admin.advertisements'));

    $advertisement = Advertisement::firstOrFail();
    expect($advertisement->image_path)->toEndWith('.webp');
    Storage::disk('public')->assertExists($advertisement->image_path);
    $this->get('/admin/advertisements')->assertOk()->assertSee('Friday Lunch Special');

    $this->post('/admin/advertisements/'.$advertisement->id.'/toggle')->assertSessionHas('status');
    expect($advertisement->fresh()->is_active)->toBeFalse();
    $this->delete('/admin/advertisements/'.$advertisement->id)->assertRedirect(route('admin.advertisements'));
    Storage::disk('public')->assertMissing($advertisement->image_path);
    expect(Advertisement::count())->toBe(0);
});

it('shows real counts, revenue and plan split', function () {
    [$a, , ] = makeTenant(2);
    [$b] = makeTenant(1);
    giveSubscription($b, SubscriptionStatus::Trialing, makePlan('trialplan', '1'));
    $price = makePlan('testplan')->prices->firstWhere('interval', 'month'); // 15,000 NGN
    makePayment($a, $price, PaymentStatus::Successful, ['paid_at' => now()->subDays(2)]);
    makePayment($a, $price, PaymentStatus::Failed);   // must not count as revenue
    makePayment($a, $price, PaymentStatus::Pending);  // must not count as revenue

    $o = new PlatformOverview(30);
    $k = $o->kpis();
    expect($k['businesses']['value'])->toBe(2)->and($k['branches']['value'])->toBe(3)
        ->and($k['subscriptions']['value'])->toBe(2)->and($k['subscriptions']['trialing'])->toBe(1)
        ->and($k['revenue']['value'])->toBe(1500000)->and($k['revenue']['growth'])->toBeNull()
        ->and($o->planBreakdown()['total'])->toBe(2);

    $this->actingAs(admin())->get('/admin')->assertOk()->assertSee('₦15,000.00')->assertSee('Super Admin Dashboard')->assertSee('Active Subscriptions');
});

it('only counts revenue inside the selected period and in the reporting currency', function () {
    [$a] = makeTenant(1);
    $price = makePlan()->prices->firstWhere('interval', 'month');
    makePayment($a, $price, PaymentStatus::Successful, ['paid_at' => now()->subDays(40)]);
    makePayment($a, $price, PaymentStatus::Successful, ['paid_at' => now()->subDays(3)]);
    $usd = $price->plan->prices()->create(['interval' => 'month', 'currency' => 'USD', 'amount_minor' => 9900, 'is_active' => true]);
    makePayment($a, $usd, PaymentStatus::Successful, ['paid_at' => now()->subDay()]);

    $k30 = (new PlatformOverview(30))->kpis()['revenue'];
    $k7 = (new PlatformOverview(7))->kpis()['revenue'];
    expect($k30['value'])->toBe(1500000)->and($k7['value'])->toBe(1500000)->and((new PlatformOverview(90))->kpis()['revenue']['value'])->toBe(3000000);
    expect($k30['growth'])->toBe(0.0); // the 40-day-old payment lands in the previous 30-day window, so revenue is flat
});

it('builds a chart whose total matches the payments', function () {
    [$a] = makeTenant(1);
    $price = makePlan()->prices->firstWhere('interval', 'month');
    makePayment($a, $price, PaymentStatus::Successful, ['paid_at' => now()->subDays(1)]);

    $chart = (new PlatformOverview(30))->revenueChart();
    expect($chart['total'])->toBe(1500000)->and($chart['empty'])->toBeFalse()->and(substr_count($chart['line'], ' '))->toBe(29);
    expect((new PlatformOverview(30))->revenueChart()['empty'])->toBeFalse();
});

it('reports an empty chart when there is no revenue', function () {
    expect((new PlatformOverview(30))->revenueChart()['empty'])->toBeTrue();
    $this->actingAs(admin())->get('/admin')->assertSee('No payments in this period yet');
});

it('rejects unsupported period values', function () {
    $this->actingAs(admin())->get('/admin?days=9999')->assertSessionHasErrors('days');
    $this->get('/admin?days=7')->assertOk();
});

it('reports honest system health', function () {
    config(['billing.paystack.secret_key' => null, 'webpush.vapid.public_key' => null]);
    cache()->forget('scheduler.heartbeat');
    $health = collect((new PlatformOverview)->health())->mapWithKeys(fn ($h) => [$h[0] => [$h[1], $h[2]]]);

    expect($health['Scheduler'])->toBe(['warn', 'Not running'])->and($health['Payment gateway'])->toBe(['warn', 'Not configured'])->and($health['Database'][0])->toBe('ok');

    cache()->put('scheduler.heartbeat', now()->timestamp, 600);
    config(['billing.paystack.secret_key' => 'sk_x']);
    $health = collect((new PlatformOverview)->health())->mapWithKeys(fn ($h) => [$h[0] => [$h[1], $h[2]]]);
    expect($health['Scheduler'])->toBe(['ok', 'Running'])->and($health['Payment gateway'])->toBe(['ok', 'Configured']);
});

it('records the scheduler heartbeat', function () {
    $this->artisan('schedule:run');
    expect(cache()->get('scheduler.heartbeat'))->not->toBeNull();
});

it('suspends and reactivates a business with a reason and audit trail', function () {
    [$business, $owner] = makeTenant(1);
    $admin = admin();

    $this->actingAs($admin)->post("/admin/businesses/{$business->id}/toggle", [])->assertSessionHasErrors('reason');
    $this->post("/admin/businesses/{$business->id}/toggle", ['reason' => 'Chargeback investigation']);
    expect($business->fresh()->status)->toBe('suspended');
    $this->actingAs($owner)->get('/dashboard')->assertForbidden();

    $this->actingAs($admin)->post("/admin/businesses/{$business->id}/toggle", ['reason' => 'Resolved, all clear']);
    expect($business->fresh()->status)->toBe('active');
    $log = AuditLog::where('action', 'admin.business_suspended')->first();
    expect($log->user_id)->toBe($admin->id)->and($log->changes['reason'])->toBe('Chargeback investigation')->and(AuditLog::where('action', 'admin.business_reactivated')->exists())->toBeTrue();
});

it('stops customers ordering from a suspended business', function () {
    [$business, , $branches] = makeTenant(1);
    $offer = makeOffer($business, $branches[0], 1000);
    $business->forceFill(['status' => 'suspended'])->save();

    $this->post('/m/'.qrFor($branches[0])->token.'/order', orderPayload([$offer->menu_item_id => 1]))->assertSessionHasErrors('branch');
});

it('forbids business users from suspending a business', function () {
    [$business, $owner] = makeTenant(1);

    $this->actingAs($owner)->post("/admin/businesses/{$business->id}/toggle", ['reason' => 'trying my luck'])->assertForbidden();
    expect($business->fresh()->status)->toBe('active');
});

it('disables a user, signs them out and lets an admin re-enable them', function () {
    [, $owner] = makeTenant(1);
    $admin = admin();
    \Illuminate\Support\Facades\DB::table('sessions')->insert(['id' => 'abc', 'user_id' => $owner->id, 'payload' => '', 'last_activity' => time()]);

    $this->actingAs($admin)->post("/admin/users/{$owner->id}/toggle", ['reason' => 'Compromised account'])->assertSessionHasNoErrors();

    expect($owner->fresh()->disabled_at)->not->toBeNull()->and(\Illuminate\Support\Facades\DB::table('sessions')->where('user_id', $owner->id)->count())->toBe(0);
    $this->post('/logout');
    $this->post('/login', ['email' => $owner->email, 'password' => 'password'])->assertSessionHasErrors('email');

    $this->actingAs($admin)->post("/admin/users/{$owner->id}/toggle", ['reason' => 'Verified owner']);
    expect($owner->fresh()->disabled_at)->toBeNull();
    $this->assertDatabaseHas('audit_logs', ['action' => 'admin.user_disabled', 'user_id' => $admin->id]);
});

it('does not let an admin disable their own account', function () {
    $admin = admin();

    $this->actingAs($admin)->post("/admin/users/{$admin->id}/toggle", ['reason' => 'oops, my mistake'])->assertStatus(422);
    expect($admin->fresh()->disabled_at)->toBeNull();
});

it('searches businesses and filters subscriptions and audit logs', function () {
    [$a] = makeTenant(1);
    [$b] = makeTenant(1);
    $a->update(['name' => 'Zebra Grill']);
    AuditLog::record('payment.succeeded', $a, [], $a->id);
    AuditLog::record('branch.created', $a, [], $a->id);

    $this->actingAs(admin());
    $this->get('/admin/businesses?q=Zebra')->assertSee('Zebra Grill')->assertDontSee($b->name);
    $this->get('/admin/subscriptions?status=expired')->assertDontSee('Zebra Grill');
    $this->get('/admin/subscriptions?status=active')->assertSee('Zebra Grill');
    $this->get('/admin/audit-logs?action=payment')->assertSee('Payment received')->assertDontSee('Branch created');
});

it('marks enquiries handled', function () {
    $e = Enquiry::create(['name' => 'Ada', 'email' => 'a@b.co', 'message' => 'Hello, a longer message']);

    $this->actingAs(admin())->post("/admin/enquiries/{$e->id}/handled")->assertSessionHas('status');
    expect($e->fresh()->status)->toBe('handled');
    $this->post("/admin/enquiries/{$e->id}/handled");
});

it('creates a super admin from the command line only', function () {
    $this->artisan('ziwait:make-admin', ['email' => 'boss@example.com', '--name' => 'Boss', '--password' => 'Strongpass123'])->assertSuccessful();
    expect(User::firstWhere('email', 'boss@example.com')->isSuperAdmin())->toBeTrue();

    $this->artisan('ziwait:make-admin', ['email' => 'weak@example.com', '--password' => 'short'])->assertFailed();
    expect(User::where('email', 'weak@example.com')->exists())->toBeFalse();

    $this->post('/register', ['name' => 'X', 'email' => 'x@example.com', 'password' => 'Strongpass123', 'password_confirmation' => 'Strongpass123', 'business_name' => 'X Co', 'is_super_admin' => 1]);
    expect(User::firstWhere('email', 'x@example.com')->is_super_admin)->toBeFalse();
});

it('formats compact money for chart labels', function () {
    expect(\App\Support\MoneyFormat::compact(1250000000, 'NGN'))->toBe('₦12.5M')->and(\App\Support\MoneyFormat::compact(85000000, 'NGN'))->toBe('₦850K')
        ->and(\App\Support\MoneyFormat::compact(90000, 'NGN'))->toBe('₦900')->and(\App\Support\MoneyFormat::compact(0, 'NGN'))->toBe('₦0');
});

it('sends super admins to the admin area after login and business users to their dashboard', function () {
    $admin = admin();
    $admin->forceFill(['password' => 'Secretpass12'])->save();
    $this->post('/login', ['email' => $admin->email, 'password' => 'Secretpass12'])->assertRedirect(route('admin.index'));

    auth()->logout();
    [, $owner] = makeTenant(1);
    $owner->forceFill(['password' => 'Secretpass12'])->save();
    $this->post('/login', ['email' => $owner->email, 'password' => 'Secretpass12'])->assertRedirect(route('dashboard'));
});
