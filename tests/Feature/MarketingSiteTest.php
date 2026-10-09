<?php

use App\Domain\Marketing\Enquiry;

it('serves the home page with prices from the plan records', function () {
    makePlan('starter', '1', 2500000);
    makePlan('professional', '5', 5000000);

    $this->get('/')->assertOk()
        ->assertSee('Digitize Your Restaurant or Hotel')->assertSee('₦25,000')->assertSee('₦50,000')->assertSee('Most popular')
        ->assertSee('Enterprise')->assertSee('Contact Sales')->assertSee(route('register'), false)->assertSee(route('login'), false);
});

it('shows no stats or testimonials unless placeholder social proof is explicitly enabled', function () {
    config(['marketing.social_proof' => false]);
    $this->get('/')->assertOk()->assertDontSee('500+')->assertDontSee('Tunde Adebayo')->assertDontSee('What our customers say');

    config(['marketing.social_proof' => true]);
    $this->get('/')->assertSee('500+')->assertSee('Tunde Adebayo');
});

it('still renders with no plans configured', function () {
    $this->get('/')->assertOk()->assertSee('Plans are being set up');
});

it('hides inactive plans and inactive prices', function () {
    makePlan('starter', '1', 2500000);
    \App\Domain\Billing\Models\Plan::where('code', 'starter')->update(['is_active' => false]);

    $this->get('/')->assertDontSee('₦25,000');
});

it('stores a contact enquiry', function () {
    $this->post('/contact', ['name' => 'Ada', 'email' => 'ada@example.com', 'message' => 'Do you support three branches?', 'business_name' => 'Ada Kitchen'])
        ->assertRedirect(route('home').'#contact')->assertSessionHas('enquiry_sent');

    expect(Enquiry::first())->name->toBe('Ada')->status->toBe('new');
});

it('validates enquiries', function () {
    $this->post('/contact', ['name' => '', 'email' => 'nope', 'message' => 'short'])->assertSessionHasErrors(['name', 'email', 'message']);
    $this->post('/contact', ['name' => 'A', 'email' => 'a@b.co', 'message' => 'A long enough message', 'phone' => '<script>'])->assertSessionHasErrors('phone');
    expect(Enquiry::count())->toBe(0);
});

it('silently drops honeypot submissions', function () {
    $this->post('/contact', ['name' => 'Bot', 'email' => 'bot@example.com', 'message' => 'Buy cheap stuff now please', 'website' => 'http://spam.example'])->assertSessionHas('enquiry_sent');

    expect(Enquiry::count())->toBe(0);
});

it('rate limits enquiries', function () {
    $payload = ['name' => 'Ada', 'email' => 'ada@example.com', 'message' => 'Hello there, interested.'];
    foreach (range(1, 5) as $_) {
        $this->post('/contact', $payload);
    }
    $this->post('/contact', $payload)->assertStatus(429);
    expect(Enquiry::count())->toBe(5);
});

it('escapes enquiry content in the admin list', function () {
    Enquiry::create(['name' => '<b>Bold</b>', 'email' => 'a@b.co', 'message' => '<script>alert(1)</script> and more text']);

    $this->actingAs(superAdmin())->get('/admin/enquiries')->assertOk()->assertDontSee('<script>alert(1)</script>', false)->assertDontSee('<b>Bold</b>', false);
});
