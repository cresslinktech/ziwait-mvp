<?php

use App\Domain\Tenancy\Models\Business;
use App\Domain\Tenancy\Role;
use App\Models\User;

it('registers an owner with a business and sends them to onboarding', function () {
    $this->post('/register', [
        'name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'Secretpass12', 'password_confirmation' => 'Secretpass12',
    ])->assertRedirect(route('onboarding.index'));

    $user = User::firstWhere('email', 'ada@example.com');
    $business = Business::firstWhere('name', "Ada's Business");

    expect($user->roleIn($business))->toBe(Role::Owner)
        ->and($business->isOnboarded())->toBeFalse()->and($business->onboarding_step)->toBe(1)
        ->and($user->is_super_admin)->toBeFalse();
    $this->assertAuthenticatedAs($user);
});

it('shows a standard account form without business setup steps', function () {
    $this->get('/register')
        ->assertOk()
        ->assertSee('Create your account')
        ->assertSee('name="email"', false)
        ->assertDontSee('Business Details')
        ->assertDontSee('business_name');
});

it('cannot self-assign super admin through registration', function () {
    $this->post('/register', [
        'name' => 'Eve', 'email' => 'eve@example.com', 'password' => 'Secretpass12', 'password_confirmation' => 'Secretpass12',
        'is_super_admin' => 1,
    ]);

    expect(User::firstWhere('email', 'eve@example.com')->is_super_admin)->toBeFalse();
});

it('rejects weak passwords and duplicate emails', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    $this->post('/register', ['name' => 'X', 'email' => 'taken@example.com', 'password' => 'short', 'password_confirmation' => 'short'])
        ->assertSessionHasErrors(['email', 'password']);
});

it('logs in with valid credentials and rejects bad ones', function () {
    $user = User::factory()->create(['password' => 'Secretpass12']);

    $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
    $this->assertGuest();
    $this->post('/login', ['email' => $user->email, 'password' => 'Secretpass12'])->assertRedirect();
    $this->assertAuthenticatedAs($user);
});

it('blocks disabled accounts from logging in', function () {
    $user = User::factory()->create(['password' => 'Secretpass12', 'disabled_at' => now()]);

    $this->post('/login', ['email' => $user->email, 'password' => 'Secretpass12'])->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('rate limits login attempts', function () {
    for ($i = 0; $i < 5; $i++) {
        $this->post('/login', ['email' => 'a@b.com', 'password' => 'x']);
    }
    $this->post('/login', ['email' => 'a@b.com', 'password' => 'x'])->assertStatus(429);
});

it('redirects guests away from the dashboard', function () {
    $this->get('/dashboard')->assertRedirect('/login');
});

it('denies a user with no business membership', function () {
    $this->actingAs(User::factory()->create())->get('/dashboard')->assertForbidden();
});
