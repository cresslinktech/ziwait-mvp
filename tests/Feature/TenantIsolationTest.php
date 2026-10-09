<?php

use App\Domain\Tenancy\Models\Branch;
use App\Domain\Tenancy\Role;
use App\Models\User;

it('lets an owner see all of their own branches', function () {
    [$business, $owner, $branches] = makeTenant(3);

    $this->actingAs($owner)->get('/dashboard/branches')
        ->assertOk()->assertSee($branches[0]->name)->assertSee($branches[2]->name);
});

it('does not show another business branches in the list', function () {
    [, $ownerA] = makeTenant();
    [, , $branchesB] = makeTenant();

    $this->actingAs($ownerA)->get('/dashboard/branches')->assertOk()->assertDontSee($branchesB[0]->name);
});

it('returns 404 when editing a branch id from another tenant (IDOR)', function () {
    [, $ownerA] = makeTenant();
    [, , $branchesB] = makeTenant();

    $this->actingAs($ownerA)->get("/dashboard/branches/{$branchesB[0]->id}/edit")->assertNotFound();
    $this->actingAs($ownerA)->put("/dashboard/branches/{$branchesB[0]->id}", ['name' => 'Hijack', 'slug' => 'x', 'timezone' => 'UTC'])->assertNotFound();
    $this->actingAs($ownerA)->post("/dashboard/branches/{$branchesB[0]->id}/toggle")->assertNotFound();

    expect($branchesB[0]->fresh()->name)->not->toBe('Hijack')->and($branchesB[0]->fresh()->is_active)->toBeTrue();
});

it('ignores a business_id supplied by the browser when creating a branch', function () {
    [$businessA, $ownerA] = makeTenant(0);
    [$businessB] = makeTenant(0);

    $this->actingAs($ownerA)->post('/dashboard/branches', [
        'name' => 'New', 'slug' => 'new', 'timezone' => 'Africa/Lagos', 'business_id' => $businessB->id, 'is_active' => false,
    ])->assertRedirect();

    $branch = Branch::firstWhere('slug', 'new');
    expect($branch->business_id)->toBe($businessA->id)->and($branch->is_active)->toBeTrue();
});

it('cannot switch into a business the user does not belong to', function () {
    [, $ownerA] = makeTenant();
    [$businessB] = makeTenant();

    $this->actingAs($ownerA)->post("/dashboard/switch-business/{$businessB->id}")->assertForbidden();
});

it('ignores a forged session business id', function () {
    [$businessA, $ownerA] = makeTenant();
    [$businessB] = makeTenant();

    $this->actingAs($ownerA)->withSession(['current_business_id' => $businessB->id])->get('/dashboard')
        ->assertOk()->assertSee($businessA->name)->assertDontSee($businessB->name);
});

it('lets a user in two businesses switch between them', function () {
    [$businessA, $ownerA] = makeTenant();
    [$businessB] = makeTenant();
    $businessB->memberships()->create(['user_id' => $ownerA->id, 'role' => Role::Manager]);

    $this->actingAs($ownerA)->post("/dashboard/switch-business/{$businessB->id}")->assertRedirect();
    $this->get('/dashboard')->assertSee($businessB->name);
});

it('suspended businesses are not accessible', function () {
    [$business, $owner] = makeTenant();
    $business->forceFill(['status' => 'suspended'])->save();

    $this->actingAs($owner)->get('/dashboard')->assertForbidden();
});

it('enforces unique branch slugs per business only', function () {
    [$businessA, $ownerA, $branchesA] = makeTenant(1);
    [, , $branchesB] = makeTenant(1);
    $payload = fn ($slug) => ['name' => 'N', 'slug' => $slug, 'timezone' => 'UTC'];

    $this->actingAs($ownerA)->post('/dashboard/branches', $payload($branchesA[0]->slug))->assertSessionHasErrors('slug');
    $this->actingAs($ownerA)->post('/dashboard/branches', $payload($branchesB[0]->slug))->assertSessionHasNoErrors();
});
