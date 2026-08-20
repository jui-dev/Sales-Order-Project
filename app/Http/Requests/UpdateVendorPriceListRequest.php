<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateVendorPriceListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Rows are keyed by vendor_product id. Ownership is not checked here - the
     * service re-queries the rows scoped to the vendor, so an id belonging to
     * someone else simply never matches.
     */
    public function rules(): array
    {
        return [
            'rows' => ['array'],
            'rows.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
            'rows.*.vendor_sku' => ['nullable', 'string', 'max:255'],
            'rows.*.is_active' => ['nullable', 'boolean'],
        ];
    }
}
