<?php

use App\Domain\Orders\Models\Order;
use App\Domain\Orders\OrderStatus;
use App\Domain\Menus\Models\MenuCategory;
use App\Domain\Marketing\Advertisement;
use Illuminate\Support\Facades\Notification;

beforeEach(fn () => Notification::fake());

it('shows the public menu with branch-specific prices and hides hidden items', function () {
    [$business, , $branches] = makeTenant(2);
    $shown = makeOffer($business, $branches[0], 150000, ['name' => 'Jollof Rice']);
    $otherBranch = makeOffer($business, $branches[1], 99900, ['name' => 'Only Branch Two']);
    $hidden = makeOffer($business, $branches[0], 5000, ['name' => 'Secret Dish']);
    $hidden->update(['is_visible' => false]);
    makeOffer($business, $branches[0], 5000, ['name' => 'Inactive Dish', 'is_active' => false]);
    $drinks = MenuCategory::factory()->create(['business_id' => $business->id, 'name' => 'Drinks']);
    $drink = makeOffer($business, $branches[0], 2500, ['name' => 'Zobo Cooler'], $drinks);

    $this->get('/m/'.qrFor($branches[0])->token)
        ->assertOk()->assertSee('Jollof Rice')->assertSee('1,500.00')->assertSee('Featured Dishes')->assertSee('data-category="all"', false)->assertSee('max-w-[430px]', false)
        ->assertSee('id="table_number" value=""', false)
        ->assertSee('onclick="openItemModal('.$drink->menu_item_id, false)
        ->assertSee('id="screen-home"', false)->assertSee('id="screen-menu"', false)->assertSee('id="screen-item"', false)->assertSee('id="screen-cart"', false)->assertSee('id="screen-more"', false)
        ->assertDontSee('ZW-PENDING')->assertDontSee('4.8')->assertDontSee('Preparation: 15 mins')
        ->assertDontSee('Sponsored')->assertDontSee('20% Off')->assertDontSee('15% off')
        ->assertDontSee('Only Branch Two')->assertDontSee('Secret Dish')->assertDontSee('Inactive Dish');
});

it('renders only active advertisements assigned by an administrator', function () {
    [$business, , $branches] = makeTenant(1);
    $menuToken = qrFor($branches[0])->token;
    Advertisement::create(['placement' => 'home_banner', 'title' => 'Admin Home Promotion', 'body' => 'Published offer text.', 'label' => 'Sponsored', 'image_path' => 'advertisements/home.webp', 'is_active' => true]);
    Advertisement::create(['placement' => 'meal_detail', 'title' => 'Paused Meal Promotion', 'label' => 'Sponsored', 'image_path' => 'advertisements/paused.webp', 'is_active' => false]);

    $this->get('/m/'.$menuToken)
        ->assertOk()
        ->assertSee('Admin Home Promotion')
        ->assertSee('Published offer text.')
        ->assertDontSee('Paused Meal Promotion');
});

it('shows sold-out items but does not let them be ordered', function () {
    [$business, , $branches] = makeTenant(1);
    $offer = makeOffer($business, $branches[0], 1000, ['name' => 'Sold Dish']);
    $offer->update(['is_available' => false]);

    $this->get('/m/'.qrFor($branches[0])->token)->assertSee('Sold Dish')->assertSee('Sold out');
    $this->post('/m/'.qrFor($branches[0])->token.'/order', orderPayload([$offer->menu_item_id => 1]))->assertSessionHasErrors('qty');
    expect(Order::count())->toBe(0);
});

it('places an order with server-calculated totals and immutable snapshots', function () {
    [$business, , $branches] = makeTenant(1);
    $a = makeOffer($business, $branches[0], 150050, ['name' => 'Jollof']);
    $b = makeOffer($business, $branches[0], 20000, ['name' => 'Chapman']);
    $token = qrFor($branches[0])->token;

    $response = $this->post('/m/'.$token.'/order', orderPayload([$a->menu_item_id => 2, $b->menu_item_id => 3, 99999 => 0], ['notes' => 'No pepper']));

    $order = Order::with('items', 'history')->first();
    $response->assertRedirect(route('menu.show', ['token' => $token, 'screen' => 'confirmation']));
    expect($order->total_minor)->toBe(2 * 150050 + 3 * 20000)
        ->and($order->status)->toBe(OrderStatus::Submitted)
        ->and($order->table_number)->toBe('12')
        ->and($order->customer_phone)->toBeNull()
        ->and($order->reference)->toMatch('/^ZW-[A-Z2-9]{8}$/')
        ->and($order->branch_id)->toBe($branches[0]->id)
        ->and($order->business_id)->toBe($business->id)
        ->and($order->currency)->toBe('NGN')
        ->and($order->notes)->toBe('No pepper')
        ->and($order->items)->toHaveCount(2)
        ->and($order->history)->toHaveCount(1);

    // Later menu edits must not change history.
    $a->menuItem->update(['name' => 'Renamed']);
    $a->update(['price_minor' => 1]);
    $a->menuItem->delete();
    $item = $order->fresh()->items->firstWhere('name', 'Jollof');
    expect($item->unit_price_minor)->toBe(150050)->and($item->line_total_minor)->toBe(300100);

    $this->get(route('order.confirmation', $order->public_token))->assertOk()->assertSee($order->reference)->assertSee('Table 12')->assertSee('Order Submitted!')->assertSee('Order Status');
});

it('places a dine-in order with only a table number and special instructions', function () {
    [$business, , $branches] = makeTenant(1);
    $offer = makeOffer($business, $branches[0], 1000);

    $this->post('/m/'.qrFor($branches[0])->token.'/order', [
        'table_number' => 'A12',
        'notes' => 'No onions, please.',
        'qty' => [$offer->menu_item_id => 1],
    ])->assertSessionHasNoErrors();

    expect(Order::first()->table_number)->toBe('A12')
        ->and(Order::first()->notes)->toBe('No onions, please.')
        ->and(Order::first()->customer_phone)->toBeNull();
});

it('tracks the current browser session orders by table and status until served', function () {
    [$business, , $branches] = makeTenant(1);
    $offer = makeOffer($business, $branches[0], 1000, ['name' => 'Session Meal']);
    $token = qrFor($branches[0])->token;

    $this->post('/m/'.$token.'/order', [
        'table_number' => 'B7',
        'qty' => [$offer->menu_item_id => 1],
    ])->assertRedirect(route('menu.show', ['token' => $token, 'screen' => 'confirmation']));

    $order = Order::firstOrFail();
    $this->get('/m/'.$token.'?screen=confirmation')->assertOk()->assertSee($order->reference)->assertSee('Table B7');
    $this->get('/m/'.$token.'/orders')->assertOk()->assertJsonPath('orders.0.reference', $order->reference)->assertJsonPath('orders.0.status_label', 'Submitted');

    $order->forceFill(['status' => OrderStatus::Completed])->save();
    $this->get('/m/'.$token.'/orders')->assertJsonPath('orders.0.status_label', 'Served')->assertJsonPath('orders.0.served', true);
});

it('does not expose another browser session orders in the Orders tab', function () {
    [, , $branches] = makeTenant(1);
    $offer = makeOffer($branches[0]->business, $branches[0], 1000);
    $token = qrFor($branches[0])->token;

    $this->post('/m/'.$token.'/order', ['table_number' => 'C3', 'qty' => [$offer->menu_item_id => 1]])->assertRedirect();
    $this->get('/m/'.$token.'/orders')->assertJsonCount(1, 'orders');

    $this->flushSession();
    $this->get('/m/'.$token.'/orders')->assertOk()->assertJsonCount(0, 'orders');
});

it('ignores browser-supplied prices, totals, branch ids and status', function () {
    [$business, , $branches] = makeTenant(2);
    $offer = makeOffer($business, $branches[0], 10000);

    $this->post('/m/'.qrFor($branches[0])->token.'/order', orderPayload([$offer->menu_item_id => 1], [
        'total_minor' => 1, 'price' => 1, 'branch_id' => $branches[1]->id, 'business_id' => 999, 'status' => 'completed', 'paid' => true,
    ]))->assertRedirect();

    $order = Order::first();
    expect($order->total_minor)->toBe(10000)->and($order->branch_id)->toBe($branches[0]->id)->and($order->status)->toBe(OrderStatus::Submitted);
});

it('rejects items that are not offered by this branch or belong to another business', function () {
    [$business, , $branches] = makeTenant(2);
    [$other, , $otherBranches] = makeTenant(1);
    $notHere = makeOffer($business, $branches[1], 1000);
    $foreign = makeOffer($other, $otherBranches[0], 1000);

    foreach ([$notHere, $foreign] as $offer) {
        $this->post('/m/'.qrFor($branches[0])->token.'/order', orderPayload([$offer->menu_item_id => 1]))->assertSessionHasErrors('qty');
    }
    expect(Order::count())->toBe(0);
});

it('rejects items in inactive categories and inactive items', function () {
    [$business, , $branches] = makeTenant(1);
    $offer = makeOffer($business, $branches[0], 1000);
    $offer->menuItem->category->forceFill(['is_active' => false])->save();

    $this->post('/m/'.qrFor($branches[0])->token.'/order', orderPayload([$offer->menu_item_id => 1]))->assertSessionHasErrors('qty');
});

it('rejects invalid quantities and an empty cart', function () {
    [$business, , $branches] = makeTenant(1);
    $offer = makeOffer($business, $branches[0], 1000);
    $url = '/m/'.qrFor($branches[0])->token.'/order';

    $this->post($url, orderPayload([$offer->menu_item_id => 21]))->assertSessionHasErrors('qty.*');
    $this->post($url, orderPayload([$offer->menu_item_id => -1]))->assertSessionHasErrors('qty.*');
    $this->post($url, orderPayload([$offer->menu_item_id => 'abc']))->assertSessionHasErrors('qty.*');
    $this->post($url, orderPayload([$offer->menu_item_id => 1.5]))->assertSessionHasErrors('qty.*');
    $this->post($url, orderPayload([$offer->menu_item_id => 0]))->assertSessionHasErrors('qty');
    $this->post($url, ['table_number' => '12'])->assertSessionHasErrors('qty');
    expect(Order::count())->toBe(0);
});

it('requires a valid table number and accepts special instructions', function () {
    [$business, , $branches] = makeTenant(1);
    $offer = makeOffer($business, $branches[0], 1000);
    $url = '/m/'.qrFor($branches[0])->token.'/order';

    $this->post($url, ['qty' => [$offer->menu_item_id => 1]])->assertSessionHasErrors('table_number');
    $this->post($url, orderPayload([$offer->menu_item_id => 1], ['table_number' => '<script>']))->assertSessionHasErrors('table_number');
});

it('does not take orders for inactive branches, suspended businesses or unknown tokens', function () {
    [$business, , $branches] = makeTenant(1);
    $offer = makeOffer($business, $branches[0], 1000);
    $token = qrFor($branches[0])->token;

    $this->get('/m/nope')->assertNotFound();
    $this->post('/m/nope/order', orderPayload([$offer->menu_item_id => 1]))->assertNotFound();

    $branches[0]->forceFill(['is_active' => false])->save();
    $this->post("/m/{$token}/order", orderPayload([$offer->menu_item_id => 1]))->assertSessionHasErrors('branch');

    $branches[0]->forceFill(['is_active' => true])->save();
    $business->forceFill(['status' => 'suspended'])->save();
    $this->post("/m/{$token}/order", orderPayload([$offer->menu_item_id => 1]))->assertSessionHasErrors('branch');
    expect(Order::count())->toBe(0);
});

it('rate limits public order submissions', function () {
    [$business, , $branches] = makeTenant(1);
    $offer = makeOffer($business, $branches[0], 1000);
    $url = '/m/'.qrFor($branches[0])->token.'/order';

    for ($i = 0; $i < 6; $i++) {
        $this->post($url, orderPayload([$offer->menu_item_id => 1]));
    }
    $this->post($url, orderPayload([$offer->menu_item_id => 1]))->assertStatus(429);
});

it('escapes customer-supplied text on the order page', function () {
    [$business, $owner, $branches] = makeTenant(1);
    $offer = makeOffer($business, $branches[0], 1000);
    $this->post('/m/'.qrFor($branches[0])->token.'/order', orderPayload([$offer->menu_item_id => 1], ['notes' => '<img src=x onerror=alert(1)>']));
    $order = Order::first();

    $this->actingAs($owner)->get("/dashboard/orders/{$order->id}")->assertOk()->assertSee('Table 12')->assertDontSee('<img src=x', false);
});

it('never exposes an order through a guessable confirmation link', function () {
    $this->get('/order/1')->assertNotFound();
    $this->get('/order/'.str_repeat('a', 40))->assertNotFound();
});
