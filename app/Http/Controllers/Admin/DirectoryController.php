<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Administration\Models\AuditLog;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Marketing\Enquiry;
use App\Domain\Tenancy\Models\Branch;
use App\Domain\Tenancy\Models\Business;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Read-mostly oversight lists for super admins, plus a few audited support actions. */
class DirectoryController extends Controller
{
    public function businesses(Request $request)
    {
        $q = trim((string) $request->query('q'));

        return view('admin.directory.businesses', [
            'q' => $q,
            'businesses' => Business::withTrashed()->withCount('branches')
                ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%")))
                ->latest('id')->paginate(20)->withQueryString()
                ->through(function ($b) {
                    $b->subscription = Subscription::where('business_id', $b->id)->with('plan')->latest('id')->first();
                    $b->owner = $b->users()->wherePivot('role', 'owner')->first();

                    return $b;
                }),
        ]);
    }

    public function toggleBusiness(Request $request, Business $business): RedirectResponse
    {
        $request->validate(['reason' => ['required', 'string', 'min:5', 'max:300']]);
        $suspend = $business->isActive();
        $business->forceFill(['status' => $suspend ? 'suspended' : 'active'])->save();
        AuditLog::record($suspend ? 'admin.business_suspended' : 'admin.business_reactivated', $business, ['reason' => $request->input('reason')], $business->id);

        return back()->with('status', $suspend ? 'Business suspended. Its team can no longer sign in to it and customers cannot order.' : 'Business reactivated.');
    }

    public function branches(Request $request)
    {
        $q = trim((string) $request->query('q'));

        return view('admin.directory.branches', [
            'q' => $q,
            'branches' => Branch::with('business')
                ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w->where('name', 'like', "%{$q}%")->orWhere('city', 'like', "%{$q}%")))
                ->latest('id')->paginate(20)->withQueryString(),
        ]);
    }

    public function users(Request $request)
    {
        $q = trim((string) $request->query('q'));

        return view('admin.directory.users', [
            'q' => $q,
            'users' => User::withCount('businessMemberships')
                ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%")))
                ->latest('id')->paginate(20)->withQueryString(),
        ]);
    }

    public function toggleUser(Request $request, User $user): RedirectResponse
    {
        $request->validate(['reason' => ['required', 'string', 'min:5', 'max:300']]);
        abort_if($user->is($request->user()), 422, 'You cannot disable your own account.');

        $disable = $user->disabled_at === null;
        $user->forceFill(['disabled_at' => $disable ? now() : null])->save();
        if ($disable) {
            $user->forceFill(['remember_token' => null])->save();
            \Illuminate\Support\Facades\DB::table('sessions')->where('user_id', $user->id)->delete();
        }
        AuditLog::record($disable ? 'admin.user_disabled' : 'admin.user_enabled', $user, ['reason' => $request->input('reason')]);

        return back()->with('status', $disable ? 'User disabled and signed out.' : 'User re-enabled.');
    }

    public function subscriptions(Request $request)
    {
        $status = $request->query('status');

        return view('admin.directory.subscriptions', [
            'status' => $status,
            'subscriptions' => Subscription::with('business', 'plan')->when($status, fn ($q) => $q->where('status', $status))->latest('id')->paginate(20)->withQueryString(),
        ]);
    }

    public function audit(Request $request)
    {
        $action = trim((string) $request->query('action'));

        return view('admin.directory.audit', [
            'action' => $action,
            'logs' => AuditLog::query()->leftJoin('businesses', 'businesses.id', '=', 'audit_logs.business_id')->leftJoin('users', 'users.id', '=', 'audit_logs.user_id')
                ->when($action !== '', fn ($q) => $q->where('audit_logs.action', 'like', $action.'%'))
                ->latest('audit_logs.id')->paginate(30, ['audit_logs.*', 'businesses.name as business_name', 'users.name as user_name'])->withQueryString(),
        ]);
    }

    public function enquiries(Request $request)
    {
        return view('admin.directory.enquiries', ['enquiries' => Enquiry::latest('id')->paginate(20)]);
    }

    public function handleEnquiry(Enquiry $enquiry): RedirectResponse
    {
        $enquiry->forceFill(['status' => 'handled'])->save();

        return back()->with('status', 'Marked as handled.');
    }
}
