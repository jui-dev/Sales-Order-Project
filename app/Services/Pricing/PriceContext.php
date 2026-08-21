<?php

namespace App\Services\Pricing;

use App\Models\Customer;
use App\Models\Order;
use App\Models\SalesChannel;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Everything that can affect which price applies, gathered in one place.
 *
 * Passed around instead of a widening argument list, so that teaching the
 * resolver about a new dimension does not mean editing every caller.
 *
 * `at` is the whole point of the object: it defaults to now, but a caller
 * re-pricing a historical document passes the date of that document and gets
 * the price that was in force then.
 */
final class PriceContext
{
    public function __construct(
        public readonly ?Customer $customer = null,
        public readonly ?SalesChannel $salesChannel = null,
        public readonly ?object $fulfilmentLocation = null,
        public readonly int $quantity = 1,
        public readonly ?CarbonInterface $at = null,
    ) {
    }

    /** Defaulted here rather than in the constructor so `at` stays nullable. */
    public function at(): CarbonInterface
    {
        return $this->at ?? Carbon::now();
    }

    /**
     * The entities a price list can be assigned to for this context.
     *
     * Ordered most specific first. The resolver uses that order to break ties
     * between lists of equal priority, so a rate negotiated with one customer
     * beats the group rate they would otherwise get.
     *
     * @return array<int, object>
     */
    public function assignables(): array
    {
        return array_values(array_filter([
            $this->customer,
            $this->customer?->group,
            $this->salesChannel,
            $this->fulfilmentLocation,
        ]));
    }

    public function withQuantity(int $quantity): self
    {
        return new self(
            $this->customer,
            $this->salesChannel,
            $this->fulfilmentLocation,
            $quantity,
            $this->at,
        );
    }

    /**
     * Rebuild the context an order was placed under, so its lines can be
     * re-priced or explained without guessing at the circumstances.
     */
    public static function forOrder(Order $order, int $quantity = 1): self
    {
        return new self(
            customer: $order->customer,
            salesChannel: $order->salesChannel,
            fulfilmentLocation: $order->fulfillmentLocation,
            quantity: $quantity,
            at: $order->created_at,
        );
    }
}
