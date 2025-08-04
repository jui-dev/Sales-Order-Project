<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Implement authorization logic if needed. For now, allow all.
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['required', 'string', 'max:255', 'unique:products,sku'],
            'description' => ['nullable', 'string'],
            'category_id' => ['nullable', 'exists:product_categories,id'],
            'selling_price' => ['nullable', 'numeric', 'min:0'],
            'available_stocks' => ['sometimes', 'integer', 'min:0'],
            'purchase_price' => ['nullable', 'numeric', 'min:0'],
            'markup' => ['nullable', 'numeric', 'min:0'],
            'gross_profit' => ['nullable', 'numeric', 'min:0'],
            'auto_pricing_enabled' => ['sometimes', 'boolean'],
            'last_price_update' => ['nullable', 'date'],
        ];
    }
} 