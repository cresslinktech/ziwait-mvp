<?php

namespace App\Http\Middleware;

use App\Domain\Tenancy\Models\Business;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the active business from the session and re-verifies membership on every
 * request. The session value is never trusted on its own.
 */
class ResolveCurrentBusiness
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user, 401);

        $businesses = $user->businesses()->where('businesses.status', 'active')->get();
        $business = $businesses->firstWhere('id', session('current_business_id')) ?? $businesses->first();

        abort_unless($business instanceof Business, 403, 'No active business membership.');

        session(['current_business_id' => $business->id]);
        app()->instance('currentBusiness', $business);
        $request->attributes->set('business', $business);
        view()->share('currentSubscription', app(\App\Domain\Billing\Actions\EntitlementService::class)->subscription($business));

        // Branch switcher: the stored choice is only honoured if the user can still access that branch.
        $branches = $user->accessibleBranches($business)->orderBy('name')->get();
        $current = $branches->firstWhere('id', session('current_branch_id'));
        if (! $current) {
            session()->forget('current_branch_id');
        }
        $request->attributes->set('currentBranch', $current);

        $overview = new \App\Domain\Reporting\BusinessOverview($business, $user, $current);
        view()->share('shell', [
            'business' => $business, 'branches' => $branches, 'currentBranch' => $current, 'isOwner' => $user->isOwnerOf($business),
            'role' => $user->roleIn($business)?->value, 'newOrders' => $overview->newOrderCount(), 'canOrders' => $overview->canSeeOrders(),
            'canCustomers' => in_array($user->roleIn($business), [\App\Domain\Tenancy\Role::Owner, \App\Domain\Tenancy\Role::Manager], true),
        ]);

        return $next($request);
    }
}
