<?php

namespace App\Http\Controllers\Dashboard;

use App\Domain\Orders\Models\Order;
use App\Domain\Tenancy\Role;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Contact-based customers are grouped by phone; anonymous dine-in table orders are excluded.
 * Because this shows contact details it is limited to owners and managers, and accessible branches.
 */
class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $business = $request->attributes->get('business');
        $user = $request->user();
        abort_unless(in_array($user->roleIn($business), [Role::Owner, Role::Manager], true), 403);

        $branchIds = $user->accessibleBranches($business)->get()->filter(fn ($b) => $user->canInBranch($b, 'orders'))->pluck('id');
        $q = trim((string) $request->query('q'));

        $customers = Order::query()->where('business_id', $business->id)->whereIn('branch_id', $branchIds)->whereNull('anonymised_at')->whereNotNull('customer_phone')->where('customer_phone', '!=', '')
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w->where('customer_name', 'like', "%{$q}%")->orWhere('customer_phone', 'like', "%{$q}%")))
            ->groupBy('customer_phone')
            ->select('customer_phone', DB::raw('max(customer_name) as name'), DB::raw('count(*) as orders'), DB::raw('sum(total_minor) as spent'), DB::raw('max(placed_at) as last_order'))
            ->orderByDesc('last_order')->paginate(20)->withQueryString();

        return view('dashboard.customers.index', ['customers' => $customers, 'currency' => $business->currency, 'q' => $q]);
    }
}
