<?php

namespace App\Services\Pricing;

use App\Models\PriceListItem;

/**
 * A price, plus where it came from.
 *
 * The provenance is not decoration. "Why is this customer being charged that?"
 * is the question a pricing system gets asked most, and carrying the list and
 * row that produced the number means the answer survives onto the order line
 * (order_items.price_list_item_id) instead of having to be reconstructed.
 */
final class ResolvedPrice
{
    public function __construct(
        public readonly float $unitPrice,
        public readonly ?int $priceListId = null,
        public readonly ?int $priceListItemId = null,
        public readonly string $source = 'unknown',
        public readonly ?string $priceListName = null,
    ) {
    }

    public static function fromItem(PriceListItem $item): self
    {
        return new self(
            unitPrice: (float) $item->unit_price,
            priceListId: $item->price_list_id,
            priceListItemId: $item->id,
            source: 'price_list',
            priceListName: $item->priceList?->name,
        );
    }

    /**
     * No list priced this product, so it was derived from cost and markup.
     * Worth telling apart from a real list price - it means somebody still has
     * to agree a price.
     */
    public static function derived(float $unitPrice): self
    {
        return new self(unitPrice: $unitPrice, source: 'derived_from_cost');
    }

    public function isDerived(): bool
    {
        return $this->source === 'derived_from_cost';
    }
}
