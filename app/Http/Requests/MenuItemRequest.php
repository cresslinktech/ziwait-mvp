<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MenuItemRequest extends FormRequest
{
    public function rules(): array
    {
        $business = $this->attributes->get('business');

        return [
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'menu_category_id' => ['required', 'integer', Rule::exists('menu_categories', 'id')->where('business_id', $business->id)->whereNull('deleted_at')],
            'image' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=6000,max_height=6000'],
            'default_price' => ['nullable', 'regex:/^\d{1,9}(\.\d{1,2})?$/'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
