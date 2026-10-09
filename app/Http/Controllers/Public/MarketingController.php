<?php

namespace App\Http\Controllers\Public;

use App\Domain\Billing\Models\Plan;
use App\Domain\Marketing\Enquiry;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class MarketingController extends Controller
{
    public function home()
    {
        $currency = config('marketing.currency');

        return view('marketing.home', [
            'plans' => Plan::where('is_active', true)->with(['prices' => fn ($q) => $q->where('is_active', true)->where('currency', $currency)])->orderBy('sort_order')->get()
                ->filter(fn ($plan) => $plan->prices->contains('interval', 'month')),
        ]);
    }

    public function contact(Request $request)
    {
        // Bots fill the hidden "website" field; humans never see it. Pretend it worked.
        if ($request->filled('website')) {
            return redirect(route('home').'#contact')->with('enquiry_sent', true);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:32', 'regex:/^[0-9+()\-\s]{6,32}$/'],
            'business_name' => ['nullable', 'string', 'max:120'],
            'message' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        $enquiry = new Enquiry($data);
        $enquiry->ip_address = $request->ip();
        $enquiry->save();

        return redirect(route('home').'#contact')->with('enquiry_sent', true);
    }
}
