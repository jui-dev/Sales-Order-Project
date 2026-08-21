<?php

namespace App\Http\Requests;

use App\Models\PriceList;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePriceListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorisation is applied by the route's permission middleware.
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:255', 'alpha_dash', 'unique:price_lists,code'],
            'type' => ['required', Rule::in([PriceList::TYPE_SALE, PriceList::TYPE_PURCHASE])],
            'currency' => ['nullable', 'string', 'size:3'],
            // Highest match wins. The seeded convention is customer-specific
            // 100, group or wholesale 50, base retail 0.
            'priority' => ['nullable', 'integer', 'min:0'],
            'is_default' => ['sometimes', 'boolean'],
            // A promotion runs for a stretch and then stops applying without
            // anyone having to remember to delete it.
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.unique' => 'Another price list already uses that code.',
            'ends_at.after' => 'A price list cannot finish before it starts.',
        ];
    }
}
