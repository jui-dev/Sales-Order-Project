<?php

namespace App\Accounting\Posting;

use App\Accounting\AccountRole;
use App\Accounting\JournalDraft;
use App\Accounting\Money;
use App\Models\StockTransaction;
use App\Services\Pricing\ProductCostService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A retailer has sent stock back to the warehouse it came from.
 *
 *     Cr 1200 Inventory  (at the retailer)
 *     Dr 1200 Inventory  (back at the warehouse)
 *
 * The exact mirror of the transfer that put the goods there. Nothing is bought
 * or sold, so no revenue, expense or cost-of-sales account is involved - the
 * value simply moves back between two locations on the one inventory account.
 */
class RetailerReturnPostingRule implements PostingRule
{
    public function __construct(
        private readonly ProductCostService $costs,
    ) {
    }

    public function key(): string
    {
        return 'stock_transaction.retailer_return';
    }

    public function documentType(): string
    {
        return StockTransaction::class;
    }

    public function appliesTo(Model $document): bool
    {
        /** @var StockTransaction $document */
        return $document->isRetailerReturn()
            && $document->stockTransfer !== null;
    }

    public function build(Model $document): JournalDraft
    {
        /** @var StockTransaction $return */
        $return = $document;
        // stockTransfer is an accessor over the polymorphic reference, not a
        // relation, so the reference is what can be eager-loaded.
        $return->loadMissing(['product', 'location', 'reference']);

        $reference = $return->formatted_id ?? ('ST-' . $return->id);

        $draft = JournalDraft::for($return, $this->key())
            ->on(Carbon::parse($return->transaction_date ?? now()))
            ->describedAs('Retailer return - ' . $reference);

        $transfer = $return->stockTransfer;
        $from = $return->location;              // the retailer holding the goods
        $to = $transfer?->fromLocation;         // the warehouse they came from

        if (! $from || ! $to || $from->getKey() === null || $to->getKey() === null) {
            return $draft;
        }

        // Same place both sides means there is no value to move.
        if ($from->getMorphClass() === $to->getMorphClass() && $from->getKey() === $to->getKey()) {
            return $draft;
        }

        // Valued the way the outbound transfer was: at the cost in force when
        // it went out. Reading the live cost made the two halves of the round
        // trip cancel only while the price stood still.
        $value = Money::fromUnitCost(
            $return->quantity,
            $this->costs->costAtOrLegacy(
                $return->product,
                Carbon::parse($transfer->transfer_date ?? $return->transaction_date ?? now()),
            ),
        );

        if ($value->isZero()) {
            return $draft;
        }

        return $draft
            ->credit(
                AccountRole::Inventory,
                $value,
                ['location' => $from, 'product' => $return->product_id],
                'Out of retailer - ' . $reference,
            )
            ->debit(
                AccountRole::Inventory,
                $value,
                ['location' => $to, 'product' => $return->product_id],
                'Back into warehouse - ' . $reference,
            );
    }
}
