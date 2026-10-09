<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Administration\Models\AuditLog;
use App\Domain\Marketing\Advertisement;
use App\Domain\Menus\Actions\StoreMenuImage;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class AdvertisementController extends Controller
{
    public function index()
    {
        return view('admin.advertisements.index', [
            'advertisements' => Advertisement::orderBy('placement')->orderBy('sort_order')->orderBy('id')->get(),
            'placements' => Advertisement::PLACEMENTS,
        ]);
    }

    public function create()
    {
        return view('admin.advertisements.form', ['advertisement' => new Advertisement(['label' => 'Sponsored', 'is_active' => true]), 'placements' => Advertisement::PLACEMENTS]);
    }

    public function store(Request $request, StoreMenuImage $storeImage): RedirectResponse
    {
        $data = $request->validate($this->rules(true));
        $advertisement = new Advertisement(collect($data)->except(['image', 'is_active'])->all());
        $advertisement->image_path = $storeImage($request->file('image'), 'advertisements', 1600);
        $advertisement->is_active = $request->boolean('is_active', true);
        $advertisement->save();
        AuditLog::record('admin.advertisement_created', $advertisement, ['placement' => $advertisement->placement]);

        return redirect()->route('admin.advertisements')->with('status', 'Advertisement created.');
    }

    public function edit(Advertisement $advertisement)
    {
        return view('admin.advertisements.form', ['advertisement' => $advertisement, 'placements' => Advertisement::PLACEMENTS]);
    }

    public function update(Request $request, Advertisement $advertisement, StoreMenuImage $storeImage): RedirectResponse
    {
        $data = $request->validate($this->rules(false));
        $oldImage = $advertisement->image_path;
        $advertisement->fill(collect($data)->except(['image', 'is_active'])->all());
        $advertisement->is_active = $request->boolean('is_active');
        if ($request->hasFile('image')) {
            $advertisement->image_path = $storeImage($request->file('image'), 'advertisements', 1600);
        }
        $advertisement->save();
        if ($request->hasFile('image')) {
            Storage::disk('public')->delete($oldImage);
        }
        AuditLog::record('admin.advertisement_updated', $advertisement, $advertisement->getChanges());

        return redirect()->route('admin.advertisements')->with('status', 'Advertisement updated.');
    }

    public function toggle(Advertisement $advertisement): RedirectResponse
    {
        $advertisement->forceFill(['is_active' => ! $advertisement->is_active])->save();
        AuditLog::record($advertisement->is_active ? 'admin.advertisement_activated' : 'admin.advertisement_paused', $advertisement, []);

        return back()->with('status', $advertisement->is_active ? 'Advertisement activated.' : 'Advertisement paused.');
    }

    public function destroy(Advertisement $advertisement): RedirectResponse
    {
        Storage::disk('public')->delete($advertisement->image_path);
        AuditLog::record('admin.advertisement_deleted', $advertisement, ['placement' => $advertisement->placement]);
        $advertisement->delete();

        return redirect()->route('admin.advertisements')->with('status', 'Advertisement deleted.');
    }

    private function rules(bool $imageRequired): array
    {
        return [
            'placement' => ['required', Rule::in(array_keys(Advertisement::PLACEMENTS))],
            'title' => ['required', 'string', 'max:120'],
            'body' => ['nullable', 'string', 'max:500'],
            'label' => ['required', 'string', 'max:40'],
            'cta_label' => ['nullable', 'string', 'max:40'],
            'target_url' => ['nullable', 'url:http,https', 'max:500'],
            'image' => [$imageRequired ? 'required' : 'nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:max_width=6000,max_height=6000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}