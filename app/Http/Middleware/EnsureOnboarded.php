<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Owners of a business that has not finished setup are sent to the wizard. Other roles are not blocked. */
class EnsureOnboarded
{
    public function handle(Request $request, Closure $next): Response
    {
        $business = $request->attributes->get('business');

        if ($business && ! $business->isOnboarded() && $request->user()->isOwnerOf($business) && ! $request->routeIs('billing.callback')) {
            return redirect()->route('onboarding.index');
        }

        return $next($request);
    }
}
