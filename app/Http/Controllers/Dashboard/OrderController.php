<?php

namespace App\Http\Controllers\Dashboard;

use App\Domain\Orders\Actions\TransitionOrder;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\OrderStatus;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $business = $request->attributes->get('business');
        $user = $request->user();

        $branches = $user->accessibleBranches($business)->orderBy('name')->get()
            ->filter(fn ($b) => $user->canInBranch($b, 'orders'))->values();

        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(OrderStatus::class)],
            'branch_id' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:60'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $orders = Order::query()
            ->where('business_id', $business->id)
            ->whereIn('branch_id', $branches->pluck('id'))
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($filters['branch_id'] ?? null, fn ($q, $b) => $q->where('branch_id', $b))
            ->when($filters['q'] ?? null, fn ($q, $t) => $q->where(fn ($w) => $w
                ->where('reference', 'like', '%'.$t.'%')
                ->orWhere('table_number', 'like', '%'.$t.'%')
                ->orWhere('customer_name', 'like', '%'.$t.'%')
                ->orWhere('customer_phone', 'like', '%'.$t.'%')))
            ->when($filters['from'] ?? null, fn ($q, $d) => $q->whereDate('placed_at', '>=', $d))
            ->when($filters['to'] ?? null, fn ($q, $d) => $q->whereDate('placed_at', '<=', $d))
            ->with('branch')
            ->withCount('items')
            ->latest('placed_at')->latest('id')
            ->paginate(20)->withQueryString();

        return view('dashboard.orders.index', ['orders' => $orders, 'branches' => $branches, 'statuses' => OrderStatus::cases()]);
    }

    public function show(Order $order)
    {
        $this->ensureTenant($order);
        $this->authorize('view', $order);

        return view('dashboard.orders.show', ['order' => $order->load('items', 'branch', 'history.user')]);
    }

    public function transition(Request $request, Order $order, TransitionOrder $transition): RedirectResponse
    {
        $this->ensureTenant($order);
        $this->authorize('updateStatus', $order);

        $data = $request->validate(['status' => ['required', Rule::enum(OrderStatus::class)], 'note' => ['nullable', 'string', 'max:190']]);
        $transition($order, OrderStatus::from($data['status']), $request->user(), $data['note'] ?? null);

        return back()->with('status', 'Order updated.');
    }
}
