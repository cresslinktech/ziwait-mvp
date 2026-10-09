<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PlaceOrderRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'table_number' => ['required', 'string', 'max:20', 'regex:/^[\pL\pN][\pL\pN \-]*$/u'],
            'notes' => ['nullable', 'string', 'max:500'],
            'qty' => ['required', 'array', 'max:80'],
            'qty.*' => ['nullable', 'integer', 'min:0', 'max:'.\App\Domain\Orders\Actions\PlaceOrder::MAX_QUANTITY],
        ];
    }

    public function messages(): array
    {
        return [
            'table_number.regex' => 'Enter a valid table number.',
            'qty.required' => 'Add at least one item to your order.',
        ];
    }
}
