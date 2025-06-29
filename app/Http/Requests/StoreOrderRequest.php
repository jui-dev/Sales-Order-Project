<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // In UI-only mode there is no auth layer yet
    }

    public function rules(): array
    {
        return [
            'customer_id'                        => ['required', 'exists:customers,id'],
            'order_date'                         => ['required', 'date'],
            'notes'                              => ['nullable', 'string'],

            'products'                           => ['required', 'array', 'min:1'],
            'products.*.product_id'              => ['required', 'exists:products,id'],
            'products.*.quantity'                => ['required', 'integer', 'min:1'],
            'products.*.unit_price'              => ['required', 'numeric', 'min:0'],
            'products.*.fulfillment_location_id'   => ['required', 'integer'],
            'products.*.fulfillment_location_type' => ['required', 'in:warehouse,retailer,other'],
        ];
    }

    public function messages(): array
    {
        return [
            'products.required' => 'At least one product must be added to the order.',
        ];
    }
} 