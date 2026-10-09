<?php

namespace App\Http\Controllers\Dashboard;

use App\Domain\Billing\Actions\EntitlementService;
use App\Domain\Reporting\BusinessOverview;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request, EntitlementService $entitlements)
    {
        $business = $request->attributes->get('business');
        $user = $request->user();
        $overview = new BusinessOverview($business, $user, $request->attributes->get('currentBranch'));
        $isOwner = $user->isOwnerOf($business);

        return view('dashboard.index', [
            'o' => $overview,
            'business' => $business,
            'isOwner' => $isOwner,
            'canMenu' => $user->roleIn($business) !== \App\Domain\Tenancy\Role::Staff,
            'kpis' => $overview->kpis(),
            'orders' => $overview->todaysOrders(),
            'chart' => $overview->ordersChart(),
            'activity' => $overview->activity(),
            'categories' => $overview->menuCategories(),
            'subscription' => $isOwner ? $entitlements->subscription($business) : null,
            'lastPayment' => $isOwner ? \App\Domain\Billing\Models\Payment::where('business_id', $business->id)->where('status', 'successful')->latest('paid_at')->with('planPrice')->first() : null,
        ]);
    }

    public function switchBusiness(Request $request, int $business)
    {
        // Only businesses the user actually belongs to can be selected.
        abort_unless($request->user()->businesses()->whereKey($business)->exists(), 403);
        session(['current_business_id' => $business]);
        session()->forget('current_branch_id');

        return redirect()->route('dashboard');
    }

    /** The branch switcher: a branch the user may access, or all of them. */
    public function switchBranch(Request $request)
    {
        $business = $request->attributes->get('business');
        $id = $request->validate(['branch_id' => ['nullable', 'integer']])['branch_id'] ?? null;

        if ($id !== null) {
            abort_unless($request->user()->accessibleBranches($business)->whereKey($id)->exists(), 403);
            session(['current_branch_id' => (int) $id]);
        } else {
            session()->forget('current_branch_id');
        }

        return back();
    }
}
