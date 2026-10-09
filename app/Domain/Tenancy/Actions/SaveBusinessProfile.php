<?php

namespace App\Domain\Tenancy\Actions;

use App\Domain\Administration\Models\AuditLog;
use App\Domain\Menus\Actions\StoreMenuImage;
use App\Domain\Tenancy\Models\Business;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SaveBusinessProfile
{
    public function __construct(private StoreMenuImage $storeImage) {}

    public function __invoke(Business $business, array $data, ?UploadedFile $logo = null, ?UploadedFile $coverPhoto = null): Business
    {
        $isInitialProfile = $business->business_type === null;
        $business->fill($data);

        if ($isInitialProfile && isset($data['name'])) {
            $baseSlug = Str::slug($data['name']) ?: 'business';
            $slug = $baseSlug;
            $suffix = 2;

            while (Business::where('slug', $slug)->whereKeyNot($business->getKey())->exists()) {
                $slug = $baseSlug.'-'.$suffix++;
            }

            $business->slug = $slug;
        }

        if ($logo) {
            $old = $business->logo_path;
            $business->logo_path = ($this->storeImage)($logo, 'logos', 600);
            if ($old) {
                Storage::disk('public')->delete($old);
            }
        }

        if ($coverPhoto) {
            $old = $business->cover_path;
            $business->cover_path = ($this->storeImage)($coverPhoto, 'business-covers', 2000);
            if ($old) {
                Storage::disk('public')->delete($old);
            }
        }

        $business->save();
        AuditLog::record('business.profile_updated', $business, $business->getChanges(), $business->id);

        return $business;
    }
}
