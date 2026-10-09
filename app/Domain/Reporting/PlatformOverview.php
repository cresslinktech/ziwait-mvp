<?php

namespace App\Domain\Reporting;

use App\Domain\Administration\Models\AuditLog;
use App\Domain\Billing\Models\ManualPaymentReview;
use App\Domain\Billing\Models\Payment;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Billing\PaymentStatus;
use App\Domain\Billing\SubscriptionStatus;
use App\Domain\Marketing\Enquiry;
use App\Domain\Tenancy\Models\Branch;
use App\Domain\Tenancy\Models\Business;
use App\Support\MoneyFormat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** Platform-wide numbers for the super-admin dashboard. Every figure is computed from stored records. */
class PlatformOverview
{
    public string $currency;

    public function __construct(public int $days = 30)
    {
        $this->currency = config('billing.reporting_currency');
    }

    public function kpis(): array
    {
        $since = now()->subDays($this->days);
        $prevSince = now()->subDays($this->days * 2);

        $revenue = $this->revenueBetween($since, now());
        $prevRevenue = $this->revenueBetween($prevSince, $since);

        return [
            'businesses' => ['value' => Business::count(), 'growth' => $this->growth(Business::count(), Business::where('created_at', '<=', $since)->count())],
            'branches' => ['value' => Branch::count(), 'growth' => $this->growth(Branch::count(), Branch::where('created_at', '<=', $since)->count())],
            'subscriptions' => ['value' => Subscription::where('status', SubscriptionStatus::Active)->count(), 'trialing' => Subscription::where('status', SubscriptionStatus::Trialing)->count()],
            'revenue' => ['value' => $revenue, 'growth' => $this->growth($revenue, $prevRevenue)],
        ];
    }

    /** Percentage change, or null when there is no earlier figure to compare with. */
    private function growth(int $now, int $before): ?float
    {
        return $before > 0 ? round((($now - $before) / $before) * 100, 1) : null;
    }

    private function revenueBetween(Carbon $from, Carbon $to): int
    {
        return (int) Payment::where('status', PaymentStatus::Successful)->where('currency', $this->currency)->whereBetween('paid_at', [$from, $to])->sum('amount_minor');
    }

    /** Daily revenue as an SVG-ready line chart. */
    public function revenueChart(int $width = 640, int $height = 220): array
    {
        $start = now()->subDays($this->days - 1)->startOfDay();
        $byDay = Payment::where('status', PaymentStatus::Successful)->where('currency', $this->currency)->where('paid_at', '>=', $start)->get(['paid_at', 'amount_minor'])
            ->groupBy(fn ($p) => $p->paid_at->toDateString())->map->sum('amount_minor');

        $series = [];
        for ($i = 0; $i < $this->days; $i++) {
            $day = $start->copy()->addDays($i);
            $series[] = ['date' => $day, 'value' => (int) ($byDay[$day->toDateString()] ?? 0)];
        }

        $max = max(1, max(array_column($series, 'value')));
        $niceMax = $this->niceCeil($max);
        [$left, $right, $top, $bottom] = [48, 12, 10, 24];
        $plotW = $width - $left - $right;
        $plotH = $height - $top - $bottom;
        $n = max(1, count($series) - 1);

        $points = [];
        foreach ($series as $i => $s) {
            $points[] = [round($left + ($i / $n) * $plotW, 1), round($top + $plotH - ($s['value'] / $niceMax) * $plotH, 1)];
        }
        $line = implode(' ', array_map(fn ($p) => $p[0].','.$p[1], $points));
        $area = 'M'.$points[0][0].','.($top + $plotH).' L'.implode(' L', array_map(fn ($p) => $p[0].','.$p[1], $points)).' L'.end($points)[0].','.($top + $plotH).' Z';

        $yLabels = array_map(fn ($f) => [round($top + $plotH - $f * $plotH, 1), MoneyFormat::compact((int) ($niceMax * $f), $this->currency)], [0, 0.25, 0.5, 0.75, 1]);
        $xLabels = [];
        foreach ([0, 0.25, 0.5, 0.75, 1] as $f) {
            $i = (int) round($f * $n);
            $xLabels[] = [$points[$i][0], $series[$i]['date']->format('M j')];
        }

        return [
            'width' => $width, 'height' => $height, 'line' => $line, 'area' => $area, 'yLabels' => $yLabels, 'xLabels' => $xLabels,
            'left' => $left, 'right' => $width - $right, 'total' => array_sum(array_column($series, 'value')), 'empty' => array_sum(array_column($series, 'value')) === 0,
        ];
    }

    private function niceCeil(int $value): int
    {
        $pow = 10 ** max(0, strlen((string) $value) - 1);

        return (int) (ceil($value / $pow) * $pow);
    }

    /** Active subscriptions per plan, with donut-ready arcs. */
    public function planBreakdown(): array
    {
        $rows = Subscription::where('status', SubscriptionStatus::Active)->join('plans', 'plans.id', '=', 'subscriptions.plan_id')
            ->groupBy('plans.name', 'plans.sort_order')->orderBy('plans.sort_order')->get([DB::raw('plans.name as name'), DB::raw('count(*) as total')]);
        $total = (int) $rows->sum('total');
        $colors = ['#14a6a0', '#4cc38a', '#6366f1', '#f5b73b', '#ec6a5c'];
        $circ = 2 * M_PI * 40;
        $offset = 0.0;

        $slices = $rows->values()->map(function ($row, $i) use ($total, $colors, $circ, &$offset) {
            $share = $total ? $row->total / $total : 0;
            $slice = ['name' => $row->name, 'count' => (int) $row->total, 'percent' => round($share * 100), 'color' => $colors[$i % count($colors)],
                'dash' => round($share * $circ, 2), 'gap' => round($circ - $share * $circ, 2), 'offset' => round(-$offset, 2)];
            $offset += $share * $circ;

            return $slice;
        })->all();

        return ['total' => $total, 'slices' => $slices];
    }

    public function recentBusinesses(int $limit = 5)
    {
        return Business::withCount('branches')->latest('id')->limit($limit)->get()->each(function ($b) {
            $b->subscription = Subscription::where('business_id', $b->id)->with('plan')->latest('id')->first();
        });
    }

    public function recentPayments(int $limit = 5)
    {
        return Payment::with('business')->latest('id')->limit($limit)->get();
    }

    public function recentActivity(int $limit = 8)
    {
        return AuditLog::query()->leftJoin('businesses', 'businesses.id', '=', 'audit_logs.business_id')->leftJoin('users', 'users.id', '=', 'audit_logs.user_id')
            ->latest('audit_logs.id')->limit($limit)->get(['audit_logs.*', 'businesses.name as business_name', 'users.name as user_name']);
    }

    public function counts(): array
    {
        return [
            'open_reviews' => ManualPaymentReview::where('status', 'open')->count(),
            'new_enquiries' => Enquiry::where('status', 'new')->count(),
        ];
    }

    /** Real checks, each reporting ok / warn with a plain label. */
    public function health(): array
    {
        $checks = [];

        try {
            DB::select('select 1');
            $checks[] = ['Database', 'ok', 'Healthy'];
        } catch (\Throwable) {
            $checks[] = ['Database', 'warn', 'Unreachable'];
        }

        try {
            $pending = config('queue.default') === 'database' ? DB::table('jobs')->count() : null;
            $failed = DB::table('failed_jobs')->count();
            $checks[] = ['Queue', $failed === 0 ? 'ok' : 'warn', $failed === 0 ? ($pending === null ? 'No failures' : "{$pending} waiting") : "{$failed} failed job".($failed === 1 ? '' : 's')];
        } catch (\Throwable) {
            $checks[] = ['Queue', 'warn', 'Unknown'];
        }

        $beat = cache()->get('scheduler.heartbeat');
        $running = $beat && (now()->timestamp - $beat) < 180;
        $checks[] = ['Scheduler', $running ? 'ok' : 'warn', $running ? 'Running' : 'Not running'];

        $checks[] = ['Storage', is_writable(storage_path('app')) ? 'ok' : 'warn', is_writable(storage_path('app')) ? 'Writable' : 'Not writable'];
        $checks[] = ['Payment gateway', config('billing.paystack.secret_key') ? 'ok' : 'warn', config('billing.paystack.secret_key') ? 'Configured' : 'Not configured'];
        $checks[] = ['Order push alerts', config('webpush.vapid.public_key') && config('webpush.vapid.private_key') ? 'ok' : 'warn', config('webpush.vapid.public_key') ? 'Configured' : 'Not configured'];

        return $checks;
    }

    public static function describe(string $action): string
    {
        return [
            'business.registered' => 'New business registered',
            'branch.created' => 'Branch created', 'branch.updated' => 'Branch updated',
            'branch.activated' => 'Branch activated', 'branch.deactivated' => 'Branch deactivated',
            'payment.succeeded' => 'Payment received', 'payment.flagged_for_review' => 'Payment flagged for review',
            'payment.review.approved' => 'Payment case approved', 'payment.review.rejected' => 'Payment case rejected',
            'subscription.past_due' => 'Subscription entered grace period', 'subscription.expired' => 'Subscription expired',
            'menu_item.created' => 'Menu item added', 'menu_item.updated' => 'Menu item updated', 'menu_item.deleted' => 'Menu item deleted',
            'branch_menu.updated' => 'Branch menu updated', 'qr.rotated' => 'QR link reissued',
            'admin.business_suspended' => 'Business suspended', 'admin.business_reactivated' => 'Business reactivated',
            'admin.user_disabled' => 'User disabled', 'admin.user_enabled' => 'User re-enabled',
            'admin.created_via_cli' => 'Super admin created',
        ][$action] ?? ucfirst(str_replace(['.', '_'], ' ', $action));
    }
}
