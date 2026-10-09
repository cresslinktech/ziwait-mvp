<?php

namespace App\Providers;

use App\Domain\Tenancy\Models\Branch;
use App\Domain\Tenancy\Models\Business;
use App\Domain\Tenancy\Policies\BranchPolicy;
use App\Domain\Tenancy\Policies\BusinessPolicy;
use App\Domain\Menus\Models\MenuCategory;
use App\Domain\Menus\Models\MenuItem;
use App\Domain\Menus\Policies\CatalogPolicy;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Policies\OrderPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(\App\Domain\Billing\Gateways\PaymentGateway::class, fn () => match (config('billing.gateway')) {
            'paystack' => new \App\Domain\Billing\Gateways\PaystackGateway,
            default => throw new \RuntimeException('Unknown billing gateway: '.config('billing.gateway')),
        });
    }

    public function boot(): void
    {
        Gate::policy(Business::class, BusinessPolicy::class);
        Gate::policy(Branch::class, BranchPolicy::class);
        Gate::policy(MenuCategory::class, CatalogPolicy::class);
        Gate::policy(MenuItem::class, CatalogPolicy::class);
        Gate::policy(Order::class, OrderPolicy::class);

        RateLimiter::for('auth', fn (Request $r) => Limit::perMinute(5)->by($r->ip().'|'.strtolower((string) $r->input('email'))));
        RateLimiter::for('order', fn (Request $r) => Limit::perMinute(6)->by($r->ip().'|'.$r->route('token')));
        RateLimiter::for('webhooks', fn (Request $r) => Limit::perMinute(120)->by($r->ip()));
        RateLimiter::for('contact', fn (Request $r) => Limit::perHour(5)->by($r->ip()));
        RateLimiter::for('register', fn (Request $r) => Limit::perHour(10)->by($r->ip()));
    }
}
