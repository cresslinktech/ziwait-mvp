<?php

namespace App\Http\Controllers\Dashboard;

use App\Domain\Administration\Models\AuditLog;
use App\Domain\Menus\Actions\StoreMenuImage;
use App\Domain\Tenancy\Models\Branch;
use App\Http\Controllers\Controller;
use App\Http\Requests\BranchRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BranchController extends Controller
{
    public function index(Request $request)
    {
        $business = $request->attributes->get('business');
        $this->authorize('viewAny', [Branch::class, $business]);

        return view('dashboard.branches.index', [
            'branches' => $request->user()->accessibleBranches($business)->orderBy('name')->get(),
        ]);
    }

    public function create(Request $request)
    {
        $this->authorize('create', [Branch::class, $request->attributes->get('business')]);

        return view('dashboard.branches.form', ['branch' => new Branch(['timezone' => 'Africa/Lagos'])]);
    }

    public function store(BranchRequest $request, StoreMenuImage $storeImage): RedirectResponse
    {
        $business = $request->attributes->get('business');
        $this->authorize('create', [Branch::class, $business]);

        $entitlements = app(\App\Domain\Billing\Actions\EntitlementService::class);
        if (! $entitlements->canAddBranch($business)) {
            $max = $entitlements->maxBranches($business);
            $message = $max === 0
                ? 'Your subscription is not active. Renew to add branches.'
                : "Your plan allows {$max} branch".($max === 1 ? '' : 'es').'. Upgrade to add more.';

            return back()->withInput()->withErrors(['name' => $message]);
        }

        $branch = $business->branches()->make($request->safe()->except('photo'));
        $branch->business_id = $business->id;
        if ($request->hasFile('photo')) {
            $branch->photo_path = $storeImage($request->file('photo'), 'branches', 1600);
        }
        $branch->save();

        AuditLog::record('branch.created', $branch, [], $business->id);

        return redirect()->route('branches.index')->with('status', 'Branch created.');
    }

    public function edit(Branch $branch)
    {
        $this->authorizeInTenant($branch, 'update');

        return view('dashboard.branches.form', ['branch' => $branch]);
    }

    public function update(BranchRequest $request, Branch $branch, StoreMenuImage $storeImage): RedirectResponse
    {
        $this->authorizeInTenant($branch, 'update');

        $branch->fill($request->safe()->except('photo'));
        $oldPhoto = $branch->photo_path;
        if ($request->hasFile('photo')) {
            $branch->photo_path = $storeImage($request->file('photo'), 'branches', 1600);
        }
        $branch->save();
        if ($request->hasFile('photo') && $oldPhoto) {
            Storage::disk('public')->delete($oldPhoto);
        }
        AuditLog::record('branch.updated', $branch, $branch->getChanges(), $branch->business_id);

        return redirect()->route('branches.index')->with('status', 'Branch updated.');
    }

    public function toggle(Branch $branch): RedirectResponse
    {
        $this->authorizeInTenant($branch, 'toggleActive');

        $branch->forceFill(['is_active' => ! $branch->is_active])->save();
        AuditLog::record($branch->is_active ? 'branch.activated' : 'branch.deactivated', $branch, [], $branch->business_id);

        return redirect()->route('branches.index')->with('status', 'Branch updated.');
    }

    /**
     * The branch must belong to the current business (404 otherwise, so ids from other
     * tenants are not confirmed to exist) and pass the policy (403).
     */
    private function authorizeInTenant(Branch $branch, string $ability): void
    {
        $this->ensureTenant($branch);
        $this->authorize($ability, $branch);
    }
}
