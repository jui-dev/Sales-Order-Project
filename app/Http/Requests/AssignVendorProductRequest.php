<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignVendorProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => [
                'required',
                'exists:products,id',
                // One row per vendor/product pair - the table enforces this too,
                // but a validation message beats a duplicate-key error page.
                Rule::unique('vendor_products')->where(
                    fn ($query) => $query->where('vendor_id', $this->route('vendor'))
                ),
            ],
            // No cost here on purpose. Assigning a product records that the
            // vendor can supply it; what they charge is set under Catalog >
            // Product Pricing, so there is one place a price is written.
        ];
    }

    public function messages(): array
    {
        return [
            'product_id.unique' => 'That product is already on this vendor\'s price list.',
        ];
    }
}
