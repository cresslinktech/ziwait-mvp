<?php

use App\Http\Middleware\EnsureOnboarded;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\ResolveCurrentBusiness;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'business' => ResolveCurrentBusiness::class,
            'super-admin' => EnsureSuperAdmin::class,
            'onboarded' => EnsureOnboarded::class,
        ]);
        $middleware->redirectGuestsTo('/login');
        // Webhooks are authenticated by signature, not by a browser session.
        $middleware->validateCsrfTokens(except: ['webhooks/*']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
