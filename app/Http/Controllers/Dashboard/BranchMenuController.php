<?php

namespace App\Http\Controllers\Dashboard;

use App\Domain\Administration\Models\AuditLog;
use App\Domain\Menus\Models\BranchMenuItem;
use App\Domain\Menus\Models\MenuItem;
use App\Domain\Tenancy\Models\Branch;
use App\Http\Controllers\Controller;
use App\Support\MoneyFormat;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BranchMenuController extends Controller
{
    public function edit(Request $request, Branch $branch)
    {
        $this->authorizeBranch($request, $branch);

        return view('dashboard.menu.branch', [
            'branch' => $branch,
            'items' => MenuItem::where('business_id', $branch->business_id)->with('category')->orderBy('name')->get(),
            'offers' => BranchMenuItem::where('branch_id', $branch->id)->get()->keyBy('menu_item_id'),
            'currency' => $branch->business->currency,
            'canPrice' => $request->user()->can('managePricing', $branch),
        ]);
    }

    public function update(Request $request, Branch $branch): RedirectResponse
    {
        $this->authorizeBranch($request, $branch);
        $canPrice = $request->user()->can('managePricing', $branch);

        $request->validate([
            'items' => ['required', 'array', 'max:500'],
            'items.*.price' => ['nullable', 'regex:/^\d{1,9}(\.\d{1,2})?$/'],
            'items.*.available' => ['nullable', 'boolean'],
            'items.*.visible' => ['nullable', 'boolean'],
        ], ['items.*.price.regex' => 'Enter prices like 1500 or 1500.50.']);

        // Only items of this branch's own business can be touched, whatever ids the browser sends.
        $valid = MenuItem::where('business_id', $branch->business_id)->whereIn('id', array_keys($request->input('items')))->pluck('id')->all();
        $existing = BranchMenuItem::where('branch_id', $branch->id)->get()->keyBy('menu_item_id');
        $currency = $branch->business->currency;

        DB::transaction(function () use ($request, $branch, $valid, $existing, $canPrice, $currency) {
            foreach ($request->input('items') as $itemId => $row) {
                if (! in_array((int) $itemId, $valid, true)) {
                    continue;
                }
                $offer = $existing->get((int) $itemId);

                if (! $canPrice) {
                    // Availability-only users can flip existing offers and nothing else.
                    $offer?->forceFill(['is_available' => (bool) ($row['available'] ?? false)])->save();

                    continue;
                }

                if (($row['price'] ?? '') === '') {
                    $offer?->delete(); // blank price = item not offered at this branch

                    continue;
                }

                BranchMenuItem::updateOrCreate(
                    ['branch_id' => $branch->id, 'menu_item_id' => (int) $itemId],
                    [
                        'price_minor' => MoneyFormat::toMinor($row['price'], $currency),
                        'is_available' => (bool) ($row['available'] ?? false),
                        'is_visible' => (bool) ($row['visible'] ?? false),
                    ],
                );
            }

            AuditLog::record('branch_menu.updated', $branch, [], $branch->business_id);
        });

        return back()->with('status', 'Branch menu saved.');
    }

    private function authorizeBranch(Request $request, Branch $branch): void
    {
        $this->ensureTenant($branch);
        abort_unless($request->user()->can('managePricing', $branch) || $request->user()->can('manageAvailability', $branch), 403);
    }
}
