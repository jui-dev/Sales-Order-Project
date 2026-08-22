<?php

namespace App\Accounting;

use App\Accounting\Exceptions\ClosedPeriod;
use App\Models\FiscalPeriod;
use Carbon\CarbonInterface;

/**
 * Decides whether the books are open on a given date.
 *
 * Every write to the ledger passes through here. Before it existed, an entry
 * could be dated into a month that had already been reported on and would
 * silently restate a statement someone had acted on.
 */
class PeriodGuard
{
    public function periodFor(CarbonInterface $date): FiscalPeriod
    {
        return FiscalPeriod::findOrCreateFor($date);
    }

    public function isOpen(CarbonInterface $date): bool
    {
        return $this->periodFor($date)->isOpen();
    }

    /**
     * @throws ClosedPeriod
     */
    public function assertOpen(CarbonInterface $date): FiscalPeriod
    {
        $period = $this->periodFor($date);

        if (! $period->isOpen()) {
            throw ClosedPeriod::for($period);
        }

        return $period;
    }
}
