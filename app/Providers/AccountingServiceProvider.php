<?php

namespace App\Providers;

use App\Accounting\AccountResolver;
use App\Accounting\PeriodGuard;
use App\Accounting\PostingEngine;
use App\Accounting\Posting\CostOfGoodsSoldPostingRule;
use App\Accounting\Posting\CustomerPaymentPostingRule;
use App\Accounting\Posting\CustomerReturnPostingRule;
use App\Accounting\Posting\GoodsReceiptPostingRule;
use App\Accounting\Posting\InvoicePostingRule;
use App\Accounting\Posting\PostingRuleRegistry;
use App\Accounting\Posting\RetailerReturnPostingRule;
use App\Accounting\Posting\StockTransferPostingRule;
use App\Accounting\Posting\SupplierBillPostingRule;
use App\Accounting\Posting\SupplierPaymentPostingRule;
use App\Accounting\Posting\VendorReturnPostingRule;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the ledger together.
 *
 * The list below is the whole answer to "what does this system post, and
 * when?" - a question that used to require reading four observers and four
 * services and grepping for account-code string literals.
 */
class AccountingServiceProvider extends ServiceProvider
{
    /** Every posting rule, in the order a transaction tends to move through them. */
    private const RULES = [
        // Purchase side
        GoodsReceiptPostingRule::class,
        SupplierBillPostingRule::class,
        SupplierPaymentPostingRule::class,
        VendorReturnPostingRule::class,

        // Sales side
        CostOfGoodsSoldPostingRule::class,
        InvoicePostingRule::class,
        CustomerPaymentPostingRule::class,
        CustomerReturnPostingRule::class,

        // Internal movement
        StockTransferPostingRule::class,
        RetailerReturnPostingRule::class,
    ];

    public function register(): void
    {
        // Resolution is cached per instance, so the engine and everything that
        // reads the chart share one.
        $this->app->singleton(AccountResolver::class);
        $this->app->singleton(PeriodGuard::class);

        $this->app->singleton(PostingRuleRegistry::class, function ($app) {
            return new PostingRuleRegistry(
                array_map(fn (string $rule) => $app->make($rule), self::RULES),
            );
        });

        $this->app->singleton(PostingEngine::class);
    }
}
