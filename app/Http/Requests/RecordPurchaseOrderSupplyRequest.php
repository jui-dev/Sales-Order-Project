<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Recording what actually turned up against a purchase order.
 *
 * Vendor and destination warehouse are deliberately absent - they are taken
 * from the order itself, so a delivery cannot be attributed to someone else.
 */
class RecordPurchaseOrderSupplyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'supply_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'products' => ['required', 'array', 'min:1'],
            'products.*.product_id' => ['required', 'exists:products,id'],
            // Zero is allowed: a line that did not turn up at all is recorded
            // as nothing received rather than being silently dropped.
            'products.*.quantity' => ['required', 'integer', 'min:0'],
            'products.*.unit_cost' => ['required', 'numeric', 'min:0'],
        ];
    }

    /**
     * Only products that were actually ordered can be received, and the whole
     * delivery cannot be empty.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $order = $this->route('purchaseOrder');
                $ordered = $order
                    ? $order->items->pluck('product_id')->all()
                    : [];

                $total = 0;

                foreach ($this->input('products', []) as $index => $line) {
                    $total += (int) ($line['quantity'] ?? 0);

                    if ($ordered && ! in_array((int) ($line['product_id'] ?? 0), $ordered, true)) {
                        $validator->errors()->add(
                            "products.{$index}.product_id",
                            'That product was not on this purchase order.'
                        );
                    }
                }

                if ($total < 1) {
                    $validator->errors()->add('products', 'Record at least one item as received.');
                }
            },
        ];
    }
}
