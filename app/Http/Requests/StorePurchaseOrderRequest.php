<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StorePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'vendor_id' => ['required', 'exists:vendors,id'],
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'expected_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'products' => ['required', 'array', 'min:1'],
            'products.*.product_id' => ['required', 'exists:products,id'],
            'products.*.quantity' => ['required', 'integer', 'min:1'],
            'products.*.unit_cost' => ['required', 'numeric', 'min:0'],
        ];
    }

    /**
     * An order may only ask a vendor for products they actually carry.
     *
     * Checked here rather than with an `exists` rule because the constraint is
     * the pair - the product exists, and so does the vendor; what matters is
     * that this vendor sells this product.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $vendorId = $this->input('vendor_id');

                if (! $vendorId) {
                    return;
                }

                $carried = \App\Models\VendorProduct::where('vendor_id', $vendorId)
                    ->pluck('product_id')
                    ->all();

                foreach ($this->input('products', []) as $index => $line) {
                    if (! in_array((int) ($line['product_id'] ?? 0), $carried, true)) {
                        $validator->errors()->add(
                            "products.{$index}.product_id",
                            'This vendor does not carry that product. Add it to their price list first.'
                        );
                    }
                }
            },
        ];
    }
}
