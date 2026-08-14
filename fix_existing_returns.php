<?php

/*
|--------------------------------------------------------------------------
| Retired - do not run
|--------------------------------------------------------------------------
|
| This was a one-off script that looped over every return transaction and
| called updateProductStock() on each one. It had no idempotency of its own,
| so re-running it re-applied every return's stock movement on top of the
| balances that were already there. It also processed "pending" returns,
| which must not move stock at all until they are approved.
|
| Returns now record stock_posted_at when their stock effect is applied, so
| this script would mostly no-op today - but it remains the wrong tool.
|
| To inspect balance drift, use the read-only replacement:
|
|     php artisan returns:reconcile-stock
|
*/

fwrite(STDERR, <<<'MESSAGE'
fix_existing_returns.php has been retired and does nothing.

It re-applied return stock movements on every run, compounding any drift
rather than correcting it.

Use the read-only report instead:

    php artisan returns:reconcile-stock

MESSAGE);

exit(1);
