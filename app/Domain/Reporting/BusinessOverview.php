<?php

namespace App\Domain\Reporting;

use App\Domain\Administration\Models\AuditLog;
use App\Domain\Billing\Models\Payment;
use App\Domain\Billing\PaymentStatus;
use App\Domain\Menus\Models\MenuCategory;
use App\Domain\Menus\Models\MenuItem;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\OrderStatus;
use App\Domain\Tenancy\Models\Branch;
use App\Domain\Tenancy\Models\Business;
use App\Models\User;
use Illuminate\Support\Collection;

/** What one signed-in team member sees on the restaurant dashboard: only their own business and branches. */
class BusinessOverview
{
    /** @var Collection<int, Branch> */
    public Collection $branches;

    /** @var list<int> branch ids whose orders this user may see (after the branch switcher is applied) */
    public array $orderBranchIds;

    public function __construct(public Business $business, public User $user, public ?Branch $selected = null)
    {
        $this->branches = $user->accessibleBranches($business)->orderBy('name')->get();
        $scope = $selected ? $this->branches->where('id', $selected->id) : $this->branches;
        $this->orderBranchIds = $scope->filter(fn (Branch $b) => $user->canInBranch($b, 'orders'))->pluck('id')->all();
    }

    public function greeting(): string
    {
        $h = (int) now()->format('G');

        return $h < 12 ? 'Good morning' : ($h < 17 ? 'Good afternoon' : 'Good evening');
    }

    public function canSeeOrders(): bool
    {
        return $this->orderBranchIds !== [];
    }

    private function orders()
    {
        return Order::where('business_id', $this->business->id)->whereIn('branch_id', $this->orderBranchIds);
    }

    public function kpis(): array
    {
        $today = $this->orders()->whereDate('placed_at', today())->count();
        $yesterday = $this->orders()->whereDate('placed_at', today()->subDay())->count();

        $items = MenuItem::where('business_id', $this->business->id)->where('is_active', true);
        $itemsNow = (clone $items)->count();
        $itemsWeekAgo = (clone $items)->where('created_at', '<=', now()->subWeek())->count();

        return [
            'orders_today' => $today,
            'orders_growth' => $yesterday > 0 ? round((($today - $yesterday) / $yesterday) * 100) : null,
            'branches_active' => $this->branches->where('is_active', true)->count(),
            'branches_total' => $this->branches->count(),
            'items' => $itemsNow,
            'items_growth' => $itemsWeekAgo > 0 ? round((($itemsNow - $itemsWeekAgo) / $itemsWeekAgo) * 100) : null,
        ];
    }

    public function todaysOrders(int $limit = 5)
    {
        return $this->orders()->whereDate('placed_at', today())->withCount('items')->with('branch')->latest('placed_at')->latest('id')->limit($limit)->get();
    }

    public function newOrderCount(): int
    {
        return $this->orders()->where('status', OrderStatus::Submitted)->count();
    }

    /** Orders per day for the last 7 days, as an SVG-ready line chart. */
    public function ordersChart(int $width = 360, int $height = 150): array
    {
        $start = today()->subDays(6);
        $counts = $this->orders()->where('placed_at', '>=', $start)->get(['placed_at'])->groupBy(fn ($o) => $o->placed_at->toDateString())->map->count();
        $series = collect(range(0, 6))->map(fn ($i) => ['d' => $start->copy()->addDays($i), 'v' => (int) ($counts[$start->copy()->addDays($i)->toDateString()] ?? 0)]);

        $max = max(4, (int) ceil($series->max('v') / 4) * 4);
        [$l, $r, $t, $b] = [28, 8, 8, 22];
        $w = $width - $l - $r;
        $h = $height - $t - $b;
        $pts = $series->values()->map(fn ($s, $i) => [round($l + ($i / 6) * $w, 1), round($t + $h - ($s['v'] / $max) * $h, 1)]);

        return [
            'width' => $width, 'height' => $height, 'left' => $l, 'right' => $width - $r, 'empty' => $series->sum('v') === 0, 'total' => $series->sum('v'),
            'line' => $pts->map(fn ($p) => $p[0].','.$p[1])->implode(' '),
            'area' => 'M'.$pts[0][0].','.($t + $h).' L'.$pts->map(fn ($p) => $p[0].','.$p[1])->implode(' L').' L'.$pts->last()[0].','.($t + $h).' Z',
            'points' => $pts->all(),
            'yLabels' => collect([0, 0.5, 1])->map(fn ($f) => [round($t + $h - $f * $h, 1), (int) ($max * $f)])->all(),
            'xLabels' => $series->values()->map(fn ($s, $i) => [$pts[$i][0], $s['d']->format('M j')])->filter(fn ($x, $i) => $i % 2 === 0)->values()->all(),
        ];
    }

    /** A real feed: orders placed, payments received and menu changes, newest first. */
    public function activity(int $limit = 6): Collection
    {
        $feed = collect();

        $this->orders()->latest('placed_at')->limit($limit)->get()->each(fn ($o) => $feed->push(['at' => $o->placed_at, 'tone' => 'amber', 'icon' => 'receipt',
            'title' => "New order {$o->reference}", 'sub' => $o->customer_name, 'url' => route('orders.show', $o)]));

        if ($this->user->isOwnerOf($this->business)) {
            Payment::where('business_id', $this->business->id)->where('status', PaymentStatus::Successful)->latest('paid_at')->limit($limit)->get()
                ->each(fn ($p) => $feed->push(['at' => $p->paid_at, 'tone' => 'slate', 'icon' => 'card', 'title' => 'Subscription payment received',
                    'sub' => \App\Support\MoneyFormat::display($p->amount_minor, $p->currency), 'url' => route('billing.index')]));
        }

        if ($this->user->roleIn($this->business) !== \App\Domain\Tenancy\Role::Staff) {
            AuditLog::where('business_id', $this->business->id)->whereIn('action', ['menu_item.created', 'menu_item.updated', 'branch_menu.updated'])->latest('id')->limit($limit)->get()
                ->each(fn ($a) => $feed->push(['at' => $a->created_at, 'tone' => 'green', 'icon' => 'menu', 'title' => PlatformOverview::describe($a->action), 'sub' => '', 'url' => route('menu.index')]));
        }

        return $feed->sortByDesc('at')->take($limit)->values();
    }

    /** Categories for the menu panel, each with its item count and a picture. */
    public function menuCategories(int $limit = 4): Collection
    {
        return MenuCategory::where('business_id', $this->business->id)->where('is_active', true)->withCount('items')->with(['items' => fn ($q) => $q->whereNotNull('image_path')->limit(1)])
            ->orderBy('sort_order')->orderBy('name')->limit($limit)->get()
            ->each(fn ($c) => $c->picture = $c->items->first()?->imageUrl() ?? asset('images/stock/food-'.self::foodPhoto($c->name).'.jpg'));
    }

    public static function foodPhoto(string $categoryName): string
    {
        $n = strtolower($categoryName);

        return match (true) {
            str_contains($n, 'drink') || str_contains($n, 'beverage') || str_contains($n, 'cocktail') || str_contains($n, 'juice') => 'drinks',
            str_contains($n, 'dessert') || str_contains($n, 'sweet') || str_contains($n, 'cake') => 'desserts',
            str_contains($n, 'starter') || str_contains($n, 'appetizer') || str_contains($n, 'side') || str_contains($n, 'salad') => 'appetizers',
            default => 'mains',
        };
    }
}
