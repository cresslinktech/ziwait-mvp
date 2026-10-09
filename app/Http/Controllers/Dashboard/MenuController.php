<?php

namespace App\Http\Controllers\Dashboard;

use App\Domain\Administration\Models\AuditLog;
use App\Domain\Menus\Actions\StoreMenuImage;
use App\Domain\Menus\Models\BranchMenuItem;
use App\Domain\Menus\Models\MenuCategory;
use App\Domain\Menus\Models\MenuItem;
use App\Http\Controllers\Controller;
use App\Http\Requests\MenuItemRequest;
use App\Support\MoneyFormat;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class MenuController extends Controller
{
    public function index(Request $request)
    {
        $business = $request->attributes->get('business');
        $user = $request->user();
        abort_unless($user->roleIn($business), 403);
        $branches = $user->accessibleBranches($business)->orderBy('name')->get()
            ->filter(fn ($branch) => $user->can('managePricing', $branch))->values();
        $branchIds = $branches->pluck('id');

        return view('dashboard.menu.index', [
            'categories' => MenuCategory::where('business_id', $business->id)
                ->with(['items.branchItems' => fn ($query) => $query->whereIn('branch_id', $branchIds)])
                ->orderBy('sort_order')->orderBy('name')->get(),
            'branches' => $branches,
            'canManage' => $user->isOwnerOf($business),
        ]);
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $business = $request->attributes->get('business');
        abort_unless($request->user()->isOwnerOf($business), 403);

        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999']]);
        $category = new MenuCategory($data);
        $category->business_id = $business->id;
        $category->save();

        return back()->with('status', 'Category added.');
    }

    public function updateCategory(Request $request, MenuCategory $category): RedirectResponse
    {
        $this->ensureTenant($category);
        $this->authorize('update', $category);

        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'], 'is_active' => ['sometimes', 'boolean']]);
        $category->fill($data)->forceFill(['is_active' => $request->boolean('is_active')])->save();

        return back()->with('status', 'Category updated.');
    }

    public function destroyCategory(MenuCategory $category): RedirectResponse
    {
        $this->ensureTenant($category);
        $this->authorize('delete', $category);

        if ($category->items()->exists()) {
            throw ValidationException::withMessages(['category' => 'Remove or move this category\'s items first.']);
        }

        $category->delete();

        return back()->with('status', 'Category deleted.');
    }

    public function createItem(Request $request)
    {
        $business = $request->attributes->get('business');
        abort_unless($request->user()->isOwnerOf($business), 403);

        return view('dashboard.menu.item-form', ['item' => new MenuItem(['is_active' => true]), 'categories' => MenuCategory::where('business_id', $business->id)->orderBy('name')->get()]);
    }

    public function storeItem(MenuItemRequest $request, StoreMenuImage $storeImage): RedirectResponse
    {
        $business = $request->attributes->get('business');
        abort_unless($request->user()->isOwnerOf($business), 403);

        DB::transaction(function () use ($request, $business, $storeImage) {
            $item = new MenuItem($request->safe()->only(['name', 'description']));
            $item->business_id = $business->id;
            $item->menu_category_id = $request->integer('menu_category_id');
            $item->is_active = $request->boolean('is_active', true);
            if ($request->hasFile('image')) {
                $item->image_path = $storeImage($request->file('image'));
            }
            $item->save();

            // A starting price puts the item on every branch menu; prices can then be changed per branch.
            if ($request->filled('default_price')) {
                $minor = MoneyFormat::toMinor($request->input('default_price'), $business->currency);
                foreach ($business->branches()->pluck('id') as $branchId) {
                    BranchMenuItem::create(['branch_id' => $branchId, 'menu_item_id' => $item->id, 'price_minor' => $minor]);
                }
            }

            AuditLog::record('menu_item.created', $item, [], $business->id);
        });

        return redirect()->route('menu.index')->with('status', 'Item added.');
    }

    public function editItem(Request $request, MenuItem $item)
    {
        $this->ensureTenant($item);
        $this->authorize('update', $item);

        return view('dashboard.menu.item-form', ['item' => $item, 'categories' => MenuCategory::where('business_id', $item->business_id)->orderBy('name')->get()]);
    }

    public function updateItem(MenuItemRequest $request, MenuItem $item, StoreMenuImage $storeImage): RedirectResponse
    {
        $this->ensureTenant($item);
        $this->authorize('update', $item);

        $item->fill($request->safe()->only(['name', 'description']));
        $item->menu_category_id = $request->integer('menu_category_id');
        $item->is_active = $request->boolean('is_active');

        if ($request->hasFile('image')) {
            $old = $item->image_path;
            $item->image_path = $storeImage($request->file('image'));
            if ($old) {
                Storage::disk('public')->delete($old);
            }
        }
        $item->save();
        AuditLog::record('menu_item.updated', $item, $item->getChanges(), $item->business_id);

        return redirect()->route('menu.index')->with('status', 'Item updated.');
    }

    public function destroyItem(MenuItem $item): RedirectResponse
    {
        $this->ensureTenant($item);
        $this->authorize('delete', $item);

        $item->delete(); // soft delete keeps order snapshots intact
        AuditLog::record('menu_item.deleted', $item, [], $item->business_id);

        return redirect()->route('menu.index')->with('status', 'Item deleted.');
    }
}
