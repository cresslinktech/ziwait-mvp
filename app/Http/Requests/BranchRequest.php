<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BranchRequest extends FormRequest
{
    public function rules(): array
    {
        $business = $this->attributes->get('business');
        $branch = $this->route('branch');

        return [
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['required', 'alpha_dash:ascii', 'max:60',
                Rule::unique('branches', 'slug')->where('business_id', $business->id)->ignore($branch?->id)],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:190'],
            'timezone' => ['required', 'timezone:all'],
            'photo' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:max_width=6000,max_height=6000'],
        ];
    }
}
