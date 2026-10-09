<?php

namespace App\Http\Controllers\Public;

use App\Domain\Menus\Models\BranchMenuItem;
use App\Domain\Menus\Models\QrLink;
use App\Domain\Marketing\Advertisement;
use App\Domain\Orders\Actions\PlaceOrder;
use App\Domain\Orders\Models\Order;
use App\Http\Controllers\Controller;
use App\Http\Requests\PlaceOrderRequest;

class PublicMenuController extends Controller
{
    public function show(string $token)
    {
        $link = $this->activeLink($token);
        $branch = $link->branch;
        $business = $branch->business;

        $offers = BranchMenuItem::query()
            ->where('branch_id', $branch->id)
            ->where('is_visible', true)
            ->whereHas('menuItem', fn ($q) => $q->where('is_active', true)->whereHas('category', fn ($c) => $c->where('is_active', true)))
            ->with('menuItem.category')
            ->get()
            ->sortBy([fn ($a, $b) => $a->menuItem->category->sort_order <=> $b->menuItem->category->sort_order, fn ($a, $b) => $a->menuItem->sort_order <=> $b->menuItem->sort_order])
            ->groupBy(fn ($o) => $o->menuItem->category->name);

        $advertisements = Advertisement::query()
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
            ->orderBy('sort_order')->orderBy('id')->get()->groupBy('placement');
        $sessionOrders = $this->sessionOrders($link);

        return view('public.customer-menu', [
            'branch' => $branch,
            'business' => $business,
            'link' => $link,
            'groups' => $offers,
            'currency' => $business->currency,
            'advertisements' => $advertisements,
            'sessionOrders' => $sessionOrders,
        ]);
    }

    public function store(PlaceOrderRequest $request, string $token, PlaceOrder $placeOrder)
    {
        $link = $this->activeLink($token);
        $order = $placeOrder($link, $request->safe()->only(['table_number', 'notes']), $request->validated('qty'));
        $sessionKey = $this->ordersSessionKey($link);
        $tokens = session()->get($sessionKey, []);
        $tokens = array_values(array_unique(array_merge([$order->public_token], is_array($tokens) ? $tokens : [])));
        session()->put($sessionKey, array_slice($tokens, 0, 10));

        return redirect()->route('menu.show', ['token' => $token, 'screen' => 'confirmation']);
    }

    public function orders(string $token)
    {
        $link = $this->activeLink($token);
        $orders = $this->sessionOrders($link);

        return response()->json(['orders' => $orders->map(fn (Order $order) => [
            'reference' => $order->reference,
            'table_number' => $order->table_number,
            'status' => $order->status->value,
            'status_label' => $order->status->value === 'completed' ? 'Served' : $order->status->label(),
            'served' => $order->status->value === 'completed',
            'placed_at' => $order->placed_at->diffForHumans(),
            'total' => \App\Support\MoneyFormat::display($order->total_minor, $order->currency),
            'items' => $order->items->map(fn ($item) => ['name' => $item->name, 'quantity' => $item->quantity])->values(),
        ])->values()]);
    }

    public function confirmation(string $publicToken)
    {
        $order = Order::with('items', 'branch')->where('public_token', $publicToken)->firstOrFail();

        return view('public.customer-confirmation', ['order' => $order, 'menuUrl' => QrLink::currentFor($order->branch)->url()]);
    }

    private function activeLink(string $token): QrLink
    {
        $link = QrLink::with('branch.business')->where('token', $token)->whereNull('revoked_at')->first();

        abort_unless($link && $link->branch && ! $link->branch->trashed() && $link->branch->business && ! $link->branch->business->trashed(), 404);

        return $link;
    }

    private function ordersSessionKey(QrLink $link): string
    {
        return 'customer_menu.orders.'.$link->branch_id;
    }

    private function sessionOrders(QrLink $link)
    {
        $tokens = session()->get($this->ordersSessionKey($link), []);
        if (! is_array($tokens) || $tokens === []) {
            return collect();
        }

        $orders = Order::with('items')->where('branch_id', $link->branch_id)->whereIn('public_token', $tokens)->get()->keyBy('public_token');

        return collect($tokens)->map(fn ($token) => $orders->get($token))->filter()->values();
    }
}
