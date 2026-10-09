<?php

use App\Domain\Orders\Actions\PlaceOrder;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\OrderStatus;
use App\Domain\Tenancy\Role;
use Illuminate\Support\Facades\Notification;

beforeEach(fn () => Notification::fake());

function placeOrder($branch, $business, int $price = 1000): Order
{
    $offer = makeOffer($business, $branch, $price);

    return app(PlaceOrder::class)(qrFor($branch), ['table_number' => '1'], [$offer->menu_item_id => 1]);
}

it('walks an order through its lifecycle and records who did what', function () {
    [$business, $owner, $branches] = makeTenant(1);
    $order = placeOrder($branches[0], $business);

    $this->actingAs($owner);
    foreach (['acknowledged', 'preparing', 'ready', 'completed'] as $status) {
        $this->post("/dashboard/orders/{$order->id}/status", ['status' => $status])->assertSessionHasNoErrors();
    }

    $order->refresh();
    expect($order->status)->toBe(OrderStatus::Completed)
        ->and($order->history->pluck('to_status')->map->value->all())->toBe(['submitted', 'acknowledged', 'preparing', 'ready', 'completed'])
        ->and($order->history->last()->user_id)->toBe($owner->id)
        ->and($order->history->last()->created_at)->not->toBeNull();
});

it('rejects invalid transitions', function () {
    [$business, $owner, $branches] = makeTenant(1);
    $order = placeOrder($branches[0], $business);
    $this->actingAs($owner);

    $this->post("/dashboard/orders/{$order->id}/status", ['status' => 'completed'])->assertSessionHasErrors('status');
    $this->post("/dashboard/orders/{$order->id}/status", ['status' => 'ready'])->assertSessionHasErrors('status');
    $this->post("/dashboard/orders/{$order->id}/status", ['status' => 'bogus'])->assertSessionHasErrors('status');
    expect($order->fresh()->status)->toBe(OrderStatus::Submitted)->and($order->history()->count())->toBe(1);
});

it('treats rejected, cancelled and completed as final', function () {
    [$business, $owner, $branches] = makeTenant(1);
    $order = placeOrder($branches[0], $business);
    $this->actingAs($owner)->post("/dashboard/orders/{$order->id}/status", ['status' => 'rejected', 'note' => 'Out of stock']);

    foreach (OrderStatus::cases() as $status) {
        $this->post("/dashboard/orders/{$order->id}/status", ['status' => $status->value])->assertSessionHasErrors('status');
    }
    expect($order->fresh()->status)->toBe(OrderStatus::Rejected)->and($order->history()->reorder('id', 'desc')->first()->note)->toBe('Out of stock');
});

it('defines the full transition table', function () {
    expect(OrderStatus::Submitted->allowedNext())->toBe([OrderStatus::Acknowledged, OrderStatus::Rejected, OrderStatus::Cancelled])
        ->and(OrderStatus::Ready->canTransitionTo(OrderStatus::Preparing))->toBeFalse()
        ->and(OrderStatus::Completed->isFinal())->toBeTrue();
});

it('returns 404 for another tenant order (IDOR)', function () {
    [, $ownerA] = makeTenant(1);
    [$businessB, , $branchesB] = makeTenant(1);
    $order = placeOrder($branchesB[0], $businessB);

    $this->actingAs($ownerA)->get("/dashboard/orders/{$order->id}")->assertNotFound();
    $this->post("/dashboard/orders/{$order->id}/status", ['status' => 'acknowledged'])->assertNotFound();
    expect($order->fresh()->status)->toBe(OrderStatus::Submitted);
});

it('hides other tenants orders and unassigned branches from the list', function () {
    [$business, $owner, $branches] = makeTenant(2);
    [$businessB, , $branchesB] = makeTenant(1);
    $mine = placeOrder($branches[0], $business);
    $second = placeOrder($branches[1], $business);
    $foreign = placeOrder($branchesB[0], $businessB);
    $manager = makeMember($business, Role::Manager, [$branches[0]]);

    $this->actingAs($owner)->get('/dashboard/orders')->assertSee($mine->reference)->assertSee($second->reference)->assertDontSee($foreign->reference);
    $this->actingAs($manager)->get('/dashboard/orders')->assertSee($mine->reference)->assertDontSee($second->reference);
    $this->get("/dashboard/orders/{$second->id}")->assertForbidden();
    $this->post("/dashboard/orders/{$second->id}/status", ['status' => 'acknowledged'])->assertForbidden();
    $this->get("/dashboard/orders?branch_id={$branches[1]->id}")->assertDontSee($second->reference);
});

it('needs the orders permission for staff', function () {
    [$business, , $branches] = makeTenant(1);
    $order = placeOrder($branches[0], $business);
    $staff = makeMember($business, Role::Staff, [$branches[0]]);

    $this->actingAs($staff)->get("/dashboard/orders/{$order->id}")->assertForbidden();

    $staff->branchAssignments()->updateExistingPivot($branches[0]->id, ['permissions' => json_encode(['orders'])]);
    $this->get("/dashboard/orders/{$order->id}")->assertOk()->assertSee($order->reference);
    $this->post("/dashboard/orders/{$order->id}/status", ['status' => 'acknowledged'])->assertSessionHasNoErrors();
    expect($order->fresh()->status)->toBe(OrderStatus::Acknowledged);
});

it('filters by status, search term and date', function () {
    [$business, $owner, $branches] = makeTenant(1);
    $a = placeOrder($branches[0], $business);
    $b = placeOrder($branches[0], $business);
    $b->forceFill(['status' => OrderStatus::Ready, 'customer_name' => 'Zainab', 'placed_at' => now()->subDays(10)])->save();

    $this->actingAs($owner)->get('/dashboard/orders?status=ready')->assertSee($b->reference)->assertDontSee($a->reference);
    $this->get('/dashboard/orders?q=Zainab')->assertSee($b->reference)->assertDontSee($a->reference);
    $this->get('/dashboard/orders?from='.now()->subDay()->toDateString())->assertSee($a->reference)->assertDontSee($b->reference);
    $this->get('/dashboard/orders?status=bogus')->assertSessionHasErrors('status');
});

it('finds a dine-in order by table number', function () {
    [$business, $owner, $branches] = makeTenant(1);
    $order = placeOrder($branches[0], $business);
    $order->forceFill(['table_number' => 'A12', 'customer_name' => ''])->save();

    $this->actingAs($owner)->get('/dashboard/orders?q=A12')->assertSee($order->reference)->assertSee('Table A12')->assertSee('1 item');
});

it('rolls back the whole order when saving fails midway', function () {
    [$business, , $branches] = makeTenant(1);
    $offer = makeOffer($business, $branches[0], 1000);
    \App\Domain\Orders\Models\OrderItem::creating(fn () => throw new RuntimeException('boom'));

    expect(fn () => app(PlaceOrder::class)(qrFor($branches[0]), ['table_number' => '1'], [$offer->menu_item_id => 1]))->toThrow(RuntimeException::class);
    expect(Order::count())->toBe(0);
});
