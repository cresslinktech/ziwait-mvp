<?php

use App\Domain\Menus\Models\BranchMenuItem;
use App\Domain\Menus\Models\MenuCategory;
use App\Domain\Menus\Models\MenuItem;
use App\Domain\Tenancy\Role;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('lets an owner create a category and an item with a starting price on every branch', function () {
    [$business, $owner, $branches] = makeTenant(2);

    $this->actingAs($owner)->post('/dashboard/menu/categories', ['name' => 'Mains'])->assertRedirect();
    $category = MenuCategory::firstWhere('name', 'Mains');
    expect($category->business_id)->toBe($business->id);

    $this->post('/dashboard/menu/items', ['name' => 'Jollof', 'menu_category_id' => $category->id, 'default_price' => '1500.50'])->assertRedirect();

    $item = MenuItem::firstWhere('name', 'Jollof');
    expect($item->business_id)->toBe($business->id)
        ->and(BranchMenuItem::where('menu_item_id', $item->id)->pluck('price_minor')->all())->toBe([150050, 150050]);
});

    it('links the menu directory directly to each branch pricing editor', function () {
        [$business, $owner, $branches] = makeTenant(2);
        $offer = makeOffer($business, $branches[0], 150000, ['name' => 'Jollof Rice']);

        $this->actingAs($owner)->get('/dashboard/menu')
        ->assertOk()
        ->assertSee('Jollof Rice')
        ->assertSee('Branch pricing')
        ->assertSee(route('branch.menu.edit', $branches[0]), false)
        ->assertSee('1 of 2 branches priced');
    });

it('keeps branch prices independent', function () {
    [$business, $owner, $branches] = makeTenant(2);
    $category = MenuCategory::factory()->create(['business_id' => $business->id]);
    $item = MenuItem::factory()->create(['menu_category_id' => $category->id]);

    $this->actingAs($owner)->put("/dashboard/branches/{$branches[0]->id}/menu", ['items' => [$item->id => ['price' => '1000', 'available' => 1, 'visible' => 1]]])->assertRedirect();
    $this->put("/dashboard/branches/{$branches[1]->id}/menu", ['items' => [$item->id => ['price' => '1200.5', 'available' => 1, 'visible' => 1]]]);

    expect(BranchMenuItem::where('branch_id', $branches[0]->id)->value('price_minor'))->toBe(100000)
        ->and(BranchMenuItem::where('branch_id', $branches[1]->id)->value('price_minor'))->toBe(120050);
});

it('removes an item from a branch when the price is blank', function () {
    [$business, $owner, $branches] = makeTenant(1);
    $offer = makeOffer($business, $branches[0], 1000);

    $this->actingAs($owner)->put("/dashboard/branches/{$branches[0]->id}/menu", ['items' => [$offer->menu_item_id => ['price' => '']]]);

    expect(BranchMenuItem::count())->toBe(0);
});

it('rejects malformed prices', function () {
    [$business, $owner, $branches] = makeTenant(1);
    $offer = makeOffer($business, $branches[0], 1000);

    foreach (['-5', 'abc', '10.999', '1e3'] as $bad) {
        $this->actingAs($owner)->put("/dashboard/branches/{$branches[0]->id}/menu", ['items' => [$offer->menu_item_id => ['price' => $bad]]])->assertSessionHasErrors('items.*.price');
    }
    expect($offer->fresh()->price_minor)->toBe(1000);
});

it('refuses another tenant item ids in a branch menu update', function () {
    [$businessA, $ownerA, $branchesA] = makeTenant(1);
    [$businessB, , $branchesB] = makeTenant(1);
    $foreign = makeOffer($businessB, $branchesB[0], 500);

    $this->actingAs($ownerA)->put("/dashboard/branches/{$branchesA[0]->id}/menu", ['items' => [$foreign->menu_item_id => ['price' => '1']]])->assertRedirect();

    expect(BranchMenuItem::where('branch_id', $branchesA[0]->id)->count())->toBe(0)
        ->and($foreign->fresh()->price_minor)->toBe(500);
});

it('returns 404 for another tenant branch menu, item and category (IDOR)', function () {
    [, $ownerA] = makeTenant(1);
    [$businessB, , $branchesB] = makeTenant(1);
    $offer = makeOffer($businessB, $branchesB[0], 500);

    $this->actingAs($ownerA)->get("/dashboard/branches/{$branchesB[0]->id}/menu")->assertNotFound();
    $this->put("/dashboard/branches/{$branchesB[0]->id}/menu", ['items' => [$offer->menu_item_id => ['price' => '1']]])->assertNotFound();
    $this->get("/dashboard/menu/items/{$offer->menu_item_id}/edit")->assertNotFound();
    $this->delete("/dashboard/menu/items/{$offer->menu_item_id}")->assertNotFound();
    $this->put('/dashboard/menu/categories/'.$offer->menuItem->menu_category_id, ['name' => 'x'])->assertNotFound();
    $this->get("/dashboard/branches/{$branchesB[0]->id}/qr")->assertNotFound();
});

it('does not let a manager change the catalogue but lets them price their branch', function () {
    [$business, , $branches] = makeTenant(2);
    $manager = makeMember($business, Role::Manager, [$branches[0]]);
    $offer = makeOffer($business, $branches[0], 1000);

    $this->actingAs($manager)->post('/dashboard/menu/categories', ['name' => 'Nope'])->assertForbidden();
    $this->delete("/dashboard/menu/items/{$offer->menu_item_id}")->assertForbidden();
    $this->get("/dashboard/menu/items/{$offer->menu_item_id}/edit")->assertForbidden();

    $this->put("/dashboard/branches/{$branches[0]->id}/menu", ['items' => [$offer->menu_item_id => ['price' => '2000', 'available' => 1, 'visible' => 1]]])->assertRedirect();
    expect($offer->fresh()->price_minor)->toBe(200000);

    $this->get("/dashboard/branches/{$branches[1]->id}/menu")->assertForbidden();
});

it('limits staff to availability toggles when granted that permission', function () {
    [$business, , $branches] = makeTenant(1);
    $staff = makeMember($business, Role::Staff, [$branches[0]]);
    $staff->branchAssignments()->updateExistingPivot($branches[0]->id, ['permissions' => json_encode(['menu_availability'])]);
    $offer = makeOffer($business, $branches[0], 1000);

    $this->actingAs($staff)->put("/dashboard/branches/{$branches[0]->id}/menu", ['items' => [$offer->menu_item_id => ['price' => '1', 'available' => 0, 'visible' => 0]]])->assertRedirect();

    $offer->refresh();
    expect($offer->is_available)->toBeFalse()->and($offer->price_minor)->toBe(1000)->and($offer->is_visible)->toBeTrue();
});

it('denies staff without the availability permission', function () {
    [$business, , $branches] = makeTenant(1);
    $staff = makeMember($business, Role::Staff, [$branches[0]]);

    $this->actingAs($staff)->get("/dashboard/branches/{$branches[0]->id}/menu")->assertForbidden();
});

it('stores uploaded images re-encoded as webp and rejects non-images', function () {
    Storage::fake('public');
    [$business, $owner] = makeTenant(1);
    $category = MenuCategory::factory()->create(['business_id' => $business->id]);

    $this->actingAs($owner)->post('/dashboard/menu/items', ['name' => 'Pic', 'menu_category_id' => $category->id, 'image' => UploadedFile::fake()->image('a.png', 400, 300)])->assertRedirect();
    $path = MenuItem::firstWhere('name', 'Pic')->image_path;
    expect($path)->toEndWith('.webp');
    Storage::disk('public')->assertExists($path);

    $this->post('/dashboard/menu/items', ['name' => 'Bad', 'menu_category_id' => $category->id, 'image' => UploadedFile::fake()->create('x.php', 10, 'application/x-php')])->assertSessionHasErrors('image');
    $this->post('/dashboard/menu/items', ['name' => 'Svg', 'menu_category_id' => $category->id, 'image' => UploadedFile::fake()->createWithContent('x.svg', '<svg onload="alert(1)"/>')])->assertSessionHasErrors('image');
});

it('rejects a category from another business for an item', function () {
    [, $ownerA] = makeTenant(1);
    $foreign = MenuCategory::factory()->create();

    $this->actingAs($ownerA)->post('/dashboard/menu/items', ['name' => 'X', 'menu_category_id' => $foreign->id])->assertSessionHasErrors('menu_category_id');
});

it('blocks deleting a category that still has items', function () {
    [$business, $owner] = makeTenant(1);
    $category = MenuCategory::factory()->create(['business_id' => $business->id]);
    MenuItem::factory()->create(['menu_category_id' => $category->id]);

    $this->actingAs($owner)->delete("/dashboard/menu/categories/{$category->id}")->assertSessionHasErrors('category');
    expect($category->fresh())->not->toBeNull();
});

it('issues a QR code and revokes the old link on rotation', function () {
    [$business, $owner, $branches] = makeTenant(1);

    $this->actingAs($owner)->get("/dashboard/branches/{$branches[0]->id}/qr.svg")->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
    $old = qrFor($branches[0])->token;

    $this->post("/dashboard/branches/{$branches[0]->id}/qr/rotate")->assertRedirect();
    $this->get("/m/{$old}")->assertNotFound();
    $this->get('/m/'.qrFor($branches[0])->token)->assertOk();
});
