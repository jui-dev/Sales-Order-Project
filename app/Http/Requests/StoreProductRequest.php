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

    /**
     * selling_price, purchase_price and gross_profit are deliberately absent.
     *
     * purchase_price is written when goods are received, and the other two are
     * derived from it by ProductObserver. Accepting them here would let a form
     * post overwrite figures the system owns.
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['required', 'string', 'max:255', 'unique:products,sku'],
            'description' => ['nullable', 'string'],
            'category_id' => ['nullable', 'exists:product_categories,id'],
            'available_stocks' => ['sometimes', 'integer', 'min:0'],
            'markup' => ['nullable', 'numeric', 'min:0'],
            'auto_pricing_enabled' => ['sometimes', 'boolean'],
            // Which vendors can supply this product. Their individual costs are
            // set on the vendor's price list, not here.
            'vendor_ids' => ['nullable', 'array'],
            'vendor_ids.*' => ['integer', 'exists:vendors,id'],
        ];
    }
} 