<?php

namespace App\Http\Controllers\Dashboard;

use App\Domain\Tenancy\Actions\SaveBusinessProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\BusinessProfileRequest;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function edit(Request $request)
    {
        $business = $request->attributes->get('business');
        $this->authorize('update', $business);

        return view('dashboard.settings.edit', ['business' => $business]);
    }

    public function update(BusinessProfileRequest $request, SaveBusinessProfile $save)
    {
        $business = $request->attributes->get('business');
        $this->authorize('update', $business);

        $save($business, $request->safe()->except(['logo', 'cover_photo']), $request->file('logo'), $request->file('cover_photo'));

        return back()->with('status', 'Business profile saved.');
    }
}
