<?php

use App\Domain\Tenancy\Role;
use App\Domain\Tenancy\Models\Branch;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('limits a branch manager to assigned branches', function () {
    [$business, , $branches] = makeTenant(2);
    $manager = makeMember($business, Role::Manager, [$branches[0]]);

    $this->actingAs($manager)->get('/dashboard/branches')
        ->assertOk()->assertSee($branches[0]->name)->assertDontSee($branches[1]->name);
    $this->get("/dashboard/branches/{$branches[0]->id}/edit")->assertOk();
    $this->get("/dashboard/branches/{$branches[1]->id}/edit")->assertForbidden();
});

it('prevents managers from creating or deactivating branches', function () {
    [$business, , $branches] = makeTenant(1);
    $manager = makeMember($business, Role::Manager, [$branches[0]]);

    $this->actingAs($manager)->get('/dashboard/branches/create')->assertForbidden();
    $this->post('/dashboard/branches', ['name' => 'N', 'slug' => 'n', 'timezone' => 'UTC'])->assertForbidden();
    $this->post("/dashboard/branches/{$branches[0]->id}/toggle")->assertForbidden();
    expect($branches[0]->fresh()->is_active)->toBeTrue();
});

it('prevents staff from editing branches but lets them see assigned ones', function () {
    [$business, , $branches] = makeTenant(2);
    $staff = makeMember($business, Role::Staff, [$branches[1]]);

    $this->actingAs($staff)->get('/dashboard/branches')->assertOk()->assertSee($branches[1]->name)->assertDontSee($branches[0]->name);
    $this->get("/dashboard/branches/{$branches[1]->id}/edit")->assertForbidden();
});

it('gives a member with no branch assignment an empty branch list', function () {
    [$business] = makeTenant(2);
    $staff = makeMember($business, Role::Staff);

    $this->actingAs($staff)->get('/dashboard/branches')->assertOk();
    expect($staff->accessibleBranches($business)->count())->toBe(0);
});

it('allows an owner to deactivate and reactivate a branch with audit entries', function () {
    [$business, $owner, $branches] = makeTenant(1);

    $this->actingAs($owner)->post("/dashboard/branches/{$branches[0]->id}/toggle");
    expect($branches[0]->fresh()->is_active)->toBeFalse();
    $this->post("/dashboard/branches/{$branches[0]->id}/toggle");
    expect($branches[0]->fresh()->is_active)->toBeTrue();
    $this->assertDatabaseHas('audit_logs', ['action' => 'branch.deactivated', 'business_id' => $business->id]);
    $this->assertDatabaseHas('audit_logs', ['action' => 'branch.activated', 'user_id' => $owner->id]);
});

it('lets an owner upload and replace a branch photo from the dashboard', function () {
    Storage::fake('public');
    [, $owner] = makeTenant(1);
    $this->actingAs($owner);
    $branchData = ['name' => 'Victoria Island', 'slug' => 'victoria-island', 'address' => '12 Adeola Odeku', 'city' => 'Lagos', 'timezone' => 'Africa/Lagos'];

    $this->get('/dashboard/branches/create')->assertOk()->assertSee('Branch photo')->assertSee('name="photo"', false);
    $this->post('/dashboard/branches', [...$branchData, 'photo' => UploadedFile::fake()->image('branch.jpg', 800, 600)])->assertRedirect('/dashboard/branches');
    $branch = Branch::where('slug', 'victoria-island')->firstOrFail();
    $firstPhoto = $branch->photo_path;
    expect($firstPhoto)->toEndWith('.webp');
    Storage::disk('public')->assertExists($firstPhoto);

    $this->put('/dashboard/branches/'.$branch->id, [...$branchData, 'photo' => UploadedFile::fake()->image('replacement.png', 900, 600)])->assertRedirect('/dashboard/branches');
    $branch->refresh();
    expect($branch->photo_path)->not->toBe($firstPhoto)->and($branch->photo_path)->toEndWith('.webp');
    Storage::disk('public')->assertMissing($firstPhoto);
    Storage::disk('public')->assertExists($branch->photo_path);
    $this->get('/dashboard/branches')->assertSee('Victoria Island')->assertSee('storage/'.$branch->photo_path, false);
});

it('rejects a non-image branch photo from the dashboard', function () {
    [, $owner] = makeTenant(1);
    $this->actingAs($owner)->post('/dashboard/branches', [
        'name' => 'Victoria Island', 'slug' => 'victoria-island', 'timezone' => 'Africa/Lagos',
        'photo' => UploadedFile::fake()->create('branch.php', 5, 'application/x-php'),
    ])->assertSessionHasErrors('photo');
});

it('keeps super admin separate from business roles', function () {
    [, $owner] = makeTenant();
    $admin = \App\Models\User::factory()->create();
    $admin->forceFill(['is_super_admin' => true])->save();

    $this->actingAs($owner)->get('/admin')->assertForbidden();
    $this->actingAs($admin)->get('/admin')->assertOk();
});

it('denies a disabled super admin', function () {
    $admin = \App\Models\User::factory()->create();
    $admin->forceFill(['is_super_admin' => true, 'disabled_at' => now()])->save();

    $this->actingAs($admin)->get('/admin')->assertForbidden();
});

it('requires login for the admin area', function () {
    $this->get('/admin')->assertRedirect('/login');
});
