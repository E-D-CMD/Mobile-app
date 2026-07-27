<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // 'set' replaces stock outright; 'increase'/'decrease' adjust
            // it by `quantity` (decrease is floored at 0, never negative).
            'action' => ['required', 'string', 'in:set,increase,decrease'],
            'quantity' => ['required', 'integer', 'min:0'],
        ];
    }
}
