<?php

namespace App\Http\Requests;

use App\Models\Product;
use App\Models\Vendor;
use App\Services\Pricing\PriceResolver;
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
     * An order may only ask a vendor for products they actually carry, at a
     * cost that vendor has agreed.
     *
     * Carriage is checked here rather than with an `exists` rule because the
     * constraint is the pair - the product exists, and so does the vendor; what
     * matters is that this vendor sells this product.
     *
     * The price check is separate from carriage on purpose. Carrying a product
     * only says the vendor can supply it; until somebody sets what it costs
     * there is no figure to order against, and the line's unit_cost field would
     * accept whatever was typed into it - which is how a product nobody has
     * priced ends up bought at a number nobody agreed.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $vendorId = $this->input('vendor_id');

                if (! $vendorId) {
                    return;
                }

                $vendor = Vendor::find($vendorId);

                $carried = \App\Models\VendorProduct::where('vendor_id', $vendorId)
                    ->pluck('product_id')
                    ->all();

                $resolver = app(PriceResolver::class);

                foreach ($this->input('products', []) as $index => $line) {
                    $productId = (int) ($line['product_id'] ?? 0);

                    if (! in_array($productId, $carried, true)) {
                        $validator->errors()->add(
                            "products.{$index}.product_id",
                            'This vendor does not carry that product. Add it to their price list first.'
                        );

                        continue; // One problem per line; carriage is the bigger one.
                    }

                    $product = Product::find($productId);

                    if (! $vendor || ! $product) {
                        continue; // The exists rules already reported this.
                    }

                    if (! $resolver->forPurchase($product, $vendor)) {
                        $validator->errors()->add(
                            "products.{$index}.product_id",
                            'No purchase price has been set for this product yet. Set one in Catalog > Product Pricing first.'
                        );
                    }
                }
            },
        ];
    }
}
