<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Marketing\Advertisement;
use App\Domain\Reporting\PlatformOverview;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $days = (int) ($request->validate(['days' => ['nullable', 'in:7,30,90']])['days'] ?? 30);
        $overview = new PlatformOverview($days);

        return view('admin.dashboard', [
            'o' => $overview,
            'days' => $overview->days,
            'kpis' => $overview->kpis(),
            'chart' => $overview->revenueChart(),
            'plans' => $overview->planBreakdown(),
            'businesses' => $overview->recentBusinesses(),
            'payments' => $overview->recentPayments(),
            'activity' => $overview->recentActivity(),
            'health' => $overview->health(),
            'counts' => $overview->counts(),
            'advertisements' => Advertisement::orderByDesc('id')->limit(5)->get(),
        ]);
    }
}
