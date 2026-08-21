<?php

namespace App\Http\Requests;

use App\Models\PurchaseOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreSupplyRequest extends FormRequest
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

            // Every supply is a delivery against a purchase order. The order
            // owns the vendor and the warehouse, and counts what has arrived so
            // far - so the link has to survive validation.
            'purchase_order_id'         => ['required', 'integer', 'exists:purchase_orders,id'],

            // Nested products
            'products'                  => ['required', 'array', 'min:1'],
            'products.*.product_id'     => ['required', 'integer', 'exists:products,id'],
            'products.*.quantity'       => ['required', 'integer', 'min:1'],
            'products.*.unit_cost'      => ['required', 'numeric', 'min:0'],
        ];
    }

    /**
     * A delivery cannot bring goods that were never ordered.
     *
     * A line that did not turn up at all is left off the form rather than sent
     * as a zero, so there is nothing to check on the low side.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $orderId = $this->input('purchase_order_id');

                // Required above; the guard is here so this closure does not
                // also fail when the order is simply missing.
                if (! $orderId) {
                    return;
                }

                $ordered = PurchaseOrder::query()
                    ->find($orderId)
                    ?->items
                    ->pluck('product_id')
                    ->all() ?? [];

                if (! $ordered) {
                    return;
                }

                foreach ($this->input('products', []) as $index => $line) {
                    if (! in_array((int) ($line['product_id'] ?? 0), $ordered, true)) {
                        $validator->errors()->add(
                            "products.{$index}.product_id",
                            'That product was not on this purchase order.'
                        );
                    }
                }
            },
        ];
    }

    /**
     * Custom messages for validation errors.
     */
    public function messages(): array
    {
        return [
            'purchase_order_id.required' => 'A supply is recorded against a purchase order. Pick the order these goods arrived for.',
            'products.required'          => 'Please add at least one product to the supply.',
            'products.*.product_id.*'    => 'The selected product is invalid.',
            'products.*.quantity.min'    => 'Quantity must be at least 1.',
            'products.*.unit_cost.min'   => 'Unit cost cannot be negative.',
        ];
    }
}
