<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('product')?->id ?? $this->route('id');

        return [
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['required', 'string', 'max:255', 'unique:products,sku,' . $id],
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