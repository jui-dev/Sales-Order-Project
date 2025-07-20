<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSupplyRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Authorize all requests for now; adjust as needed when auth is added.
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * This covers the parent supply data as well as the nested products array.
     */
    public function rules(): array
    {
        return [
            'vendor_id'                 => ['required', 'integer', 'exists:vendors,id'],
            'warehouse_id'              => ['required', 'integer', 'exists:warehouses,id'],
            'supply_date'               => ['required', 'date'],
            'notes'                     => ['nullable', 'string'],

            // Nested products
            'products'                  => ['required', 'array', 'min:1'],
            'products.*.product_id'     => ['required', 'integer', 'exists:products,id'],
            'products.*.quantity'       => ['required', 'integer', 'min:1'],
            'products.*.unit_cost'      => ['required', 'numeric', 'min:0'],
        ];
    }

    /**
     * Custom messages for validation errors.
     */
    public function messages(): array
    {
        return [
            'products.required'          => 'Please add at least one product to the supply.',
            'products.*.product_id.*'    => 'The selected product is invalid.',
            'products.*.quantity.min'    => 'Quantity must be at least 1.',
            'products.*.unit_cost.min'   => 'Unit cost cannot be negative.',
        ];
    }
} 