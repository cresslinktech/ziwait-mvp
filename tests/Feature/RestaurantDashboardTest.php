<?php

use App\Domain\Orders\Actions\PlaceOrder;
use App\Domain\Orders\OrderStatus;
use App\Domain\Reporting\BusinessOverview;
use App\Domain\Tenancy\Role;
use Illuminate\Support\Facades\Notification;

beforeEach(fn () => Notification::fake());

function order($branch, $business, array $attrs = [])
{
    $offer = makeOffer($business, $branch, 100000);
    $order = app(PlaceOrder::class)(qrFor($branch), ['table_number' => $attrs['table_number'] ?? '1'], [$offer->menu_item_id => 2]);
    if (isset($attrs['placed_at']) || isset($attrs['status'])) {
        $order->forceFill(array_filter(['placed_at' => $attrs['placed_at'] ?? null, 'status' => $attrs['status'] ?? null]))->save();
    }

    return $order;
}

it('greets the user and shows real figures', function () {
    [$business, $owner, $branches] = makeTenant(2);
    $branches[1]->forceFill(['is_active' => false])->save();
    order($branches[0], $business);
    order($branches[0], $business, ['placed_at' => now()->subDay()]);
    order($branches[0], $business, ['placed_at' => now()->subDays(2)]);

    $res = $this->actingAs($owner)->get('/dashboard')->assertOk();
    $res->assertSee('👋')->assertSee($business->name)->assertSee("Today's orders")->assertSee('1 / 2', false)->assertSee('inactive')->assertSee('Orders today');

    $k = (new BusinessOverview($business, $owner))->kpis();
    expect($k['orders_today'])->toBe(1)->and($k['orders_growth'])->toBe(0.0)->and($k['branches_active'])->toBe(1)->and($k['branches_total'])->toBe(2)->and($k['items'])->toBe(3);
});

it('shows only the signed-in business data', function () {
    [$a, $ownerA, $branchesA] = makeTenant(1);
    [$b, , $branchesB] = makeTenant(1);
    $mine = order($branchesA[0], $a);
    $theirs = order($branchesB[0], $b, ['table_number' => 'Foreign-2']);

    $this->actingAs($ownerA)->get('/dashboard')->assertSee($mine->reference)->assertDontSee($theirs->reference)->assertDontSee('Table Foreign-2');
});

it('scopes managers to their branches and the branch switcher to the selection', function () {
    [$business, $owner, $branches] = makeTenant(2);
    $o1 = order($branches[0], $business);
    $o2 = order($branches[1], $business);
    $manager = makeMember($business, Role::Manager, [$branches[0]]);

    $this->actingAs($manager)->get('/dashboard')->assertSee($o1->reference)->assertDontSee($o2->reference);

    $this->actingAs($owner)->get('/dashboard')->assertSee($o1->reference)->assertSee($o2->reference);
    $this->post('/dashboard/switch-branch', ['branch_id' => $branches[1]->id])->assertRedirect();
    $this->get('/dashboard')->assertSee($o2->reference)->assertDontSee($o1->reference);
    $this->post('/dashboard/switch-branch', ['branch_id' => null]);
    $this->get('/dashboard')->assertSee($o1->reference);
});

it('refuses to switch to a branch the user cannot access', function () {
    [$business, , $branches] = makeTenant(2);
    [, , $foreign] = makeTenant(1);
    $manager = makeMember($business, Role::Manager, [$branches[0]]);

    $this->actingAs($manager)->post('/dashboard/switch-branch', ['branch_id' => $branches[1]->id])->assertForbidden();
    $this->post('/dashboard/switch-branch', ['branch_id' => $foreign[0]->id])->assertForbidden();
    $this->post('/dashboard/switch-branch', ['branch_id' => 'abc'])->assertSessionHasErrors('branch_id');
});

it('drops a stale branch selection when access is lost', function () {
    [$business, , $branches] = makeTenant(2);
    $manager = makeMember($business, Role::Manager, [$branches[0], $branches[1]]);
    $this->actingAs($manager)->post('/dashboard/switch-branch', ['branch_id' => $branches[1]->id]);
    $manager->branchAssignments()->detach($branches[1]->id);

    $this->get('/dashboard')->assertOk()->assertDontSee($branches[1]->name);
    expect(session('current_branch_id'))->toBeNull();
});

it('shows the new-order badge and hides sections the role cannot use', function () {
    [$business, $owner, $branches] = makeTenant(1);
    order($branches[0], $business);
    order($branches[0], $business);
    $staff = makeMember($business, Role::Staff, [$branches[0]]);

    $this->actingAs($owner)->get('/dashboard')->assertSee('2 new orders')->assertSee('Customers')->assertSee('Settings')->assertSee('Manage Plan');
    $this->actingAs($staff)->get('/dashboard')->assertOk()->assertDontSee('Customers')->assertDontSee('Settings')->assertDontSee('Manage Plan')->assertDontSee('Subscription &amp; billing', false)
        ->assertSee('Orders are not enabled for your role');
});

it('keeps customer contact details to owners and managers', function () {
    [$business, $owner, $branches] = makeTenant(2);
    $contactOrder = function ($branch, string $name, string $phone) use ($business) {
        $saved = order($branch, $business);
        $saved->forceFill(['customer_name' => $name, 'customer_phone' => $phone])->save();
    };
    $contactOrder($branches[0], 'Chidi Okoro', '0801111111');
    $contactOrder($branches[0], 'Chidi Okoro', '0801111111');
    $contactOrder($branches[1], 'Branch Two Guy', '0802222222');
    $manager = makeMember($business, Role::Manager, [$branches[0]]);
    $staff = makeMember($business, Role::Staff, [$branches[0]]);

    $this->actingAs($owner)->get('/dashboard/customers')->assertOk()->assertSee('Chidi Okoro')->assertSee('0801111111')->assertSee('Branch Two Guy');
    $this->actingAs($manager)->get('/dashboard/customers')->assertSee('Chidi Okoro')->assertDontSee('Branch Two Guy');
    $this->actingAs($staff)->get('/dashboard/customers')->assertForbidden();
    $this->actingAs($owner)->get('/dashboard/customers?q=0802')->assertSee('Branch Two Guy')->assertDontSee('Chidi Okoro');
});

it('does not list customers of other businesses', function () {
    [, $ownerA] = makeTenant(1);
    [$b, , $branchesB] = makeTenant(1);
    $foreignOrder = order($branchesB[0], $b);
    $foreignOrder->forceFill(['customer_name' => 'Foreign Person', 'customer_phone' => '0809999999'])->save();

    $this->actingAs($ownerA)->get('/dashboard/customers')->assertDontSee('Foreign Person')->assertDontSee('0809999999');
});

it('shows only the signed-in users own delivery attempts', function () {
    [$business, $owner, $branches] = makeTenant(1);
    $manager = makeMember($business, Role::Manager, [$branches[0]]);
    $o = order($branches[0], $business);
    \App\Domain\Notifications\NotificationLog::create(['order_id' => $o->id, 'user_id' => $owner->id, 'channel' => 'webpush', 'status' => 'sent']);
    \App\Domain\Notifications\NotificationLog::create(['order_id' => $o->id, 'user_id' => $manager->id, 'channel' => 'mail', 'status' => 'failed', 'error' => 'secret smtp detail']);

    $this->actingAs($owner)->get('/dashboard/notifications')->assertOk()->assertSee('Push')->assertDontSee('secret smtp detail')->assertDontSee('Failed');
});

it('builds a 7-day orders chart from real orders', function () {
    [$business, $owner, $branches] = makeTenant(1);
    expect((new BusinessOverview($business, $owner))->ordersChart()['empty'])->toBeTrue();
    order($branches[0], $business);
    order($branches[0], $business, ['placed_at' => now()->subDays(3)]);

    $chart = (new BusinessOverview($business, $owner))->ordersChart();
    expect($chart['empty'])->toBeFalse()->and($chart['total'])->toBe(2)->and(substr_count($chart['line'], ' '))->toBe(6);
});

it('builds a real activity feed without leaking other businesses', function () {
    [$business, $owner, $branches] = makeTenant(1);
    [$other, , $otherBranches] = makeTenant(1);
    $mine = order($branches[0], $business);
    order($otherBranches[0], $other);
    \App\Domain\Administration\Models\AuditLog::record('menu_item.created', null, [], $other->id);

    $feed = (new BusinessOverview($business, $owner))->activity();
    expect($feed->pluck('title')->all())->toBe(["New order {$mine->reference}"]);
});

it('picks category photos by name and prefers uploaded item photos', function () {
    expect(BusinessOverview::foodPhoto('Cold Drinks'))->toBe('drinks')->and(BusinessOverview::foodPhoto('Desserts'))->toBe('desserts')
        ->and(BusinessOverview::foodPhoto('Starters'))->toBe('appetizers')->and(BusinessOverview::foodPhoto('Grill'))->toBe('mains');
});

it('serves the stock photos the pages rely on', function () {
    foreach (['hero-waiter', 'cta-kitchen', 'onboarding-banner', 'branch-1', 'branch-2', 'food-appetizers', 'food-mains', 'food-desserts', 'food-drinks'] as $name) {
        expect(file_exists(public_path("images/stock/{$name}.jpg")))->toBeTrue("missing {$name}");
    }
});
