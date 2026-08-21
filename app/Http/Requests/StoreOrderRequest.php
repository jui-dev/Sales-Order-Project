<?php

namespace App\Http\Requests;

use App\Models\Customer;
use App\Models\Product;
use App\Models\SalesChannel;
use App\Services\Pricing\PriceContext;
use App\Services\Pricing\PriceResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreOrderRequest extends FormRequest
{
    /**
     * How far a posted price may sit from the resolved one before it counts as
     * an override rather than a rounding difference.
     */
    private const PRICE_TOLERANCE = 0.01;

    public function authorize(): bool
    {
        return true; // In UI-only mode there is no auth layer yet
    }

    public function rules(): array
    {
        return [
            'customer_id'                        => ['required', 'exists:customers,id'],
            'sales_channel_id'                   => ['nullable', 'exists:sales_channels,id'],
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

    /**
     * A posted price must be the one the system would have quoted.
     *
     * The unit price field is readonly in the browser and nowhere else - the
     * form posts whatever it is given, and nothing used to check it. Anyone
     * able to submit the form could name their own price.
     *
     * Re-resolving here is not a second opinion on what to charge; it is the
     * same resolution the form asked for, repeated somewhere the customer
     * cannot reach. Deliberate discounts are a real need, so they are allowed -
     * but as an explicit permission rather than as an unchecked field.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if (! $this->has('products') || ! is_array($this->input('products'))) {
                    return;
                }

                $user = $this->user();

                if ($user && method_exists($user, 'hasPermission') && $user->hasPermission('orders.override-price')) {
                    return;
                }

                $resolver = app(PriceResolver::class);
                $customer = Customer::find($this->input('customer_id'));
                $channel = SalesChannel::find($this->input('sales_channel_id'));

                foreach ($this->input('products', []) as $index => $line) {
                    $product = Product::find($line['product_id'] ?? null);

                    if (! $product) {
                        continue; // The exists rule already reported this.
                    }

                    $resolved = $resolver->forSale($product, new PriceContext(
                        customer: $customer,
                        salesChannel: $channel,
                        quantity: (int) ($line['quantity'] ?? 1),
                    ));

                    if (! $resolved) {
                        $validator->errors()->add(
                            "products.{$index}.unit_price",
                            'No price has been agreed for this product yet. Set one on its price list first.'
                        );

                        continue;
                    }

                    $posted = (float) ($line['unit_price'] ?? 0);

                    if (abs($posted - $resolved->unitPrice) > self::PRICE_TOLERANCE) {
                        $validator->errors()->add(
                            "products.{$index}.unit_price",
                            sprintf(
                                'The price for %s is %s, not %s. Ask for the override permission to sell at a different price.',
                                $product->name,
                                number_format($resolved->unitPrice, 2),
                                number_format($posted, 2),
                            )
                        );
                    }
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'products.required' => 'At least one product must be added to the order.',
        ];
    }
}
