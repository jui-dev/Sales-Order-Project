<?php

namespace App\Accounting\Exceptions;

use App\Models\FiscalPeriod;
use RuntimeException;

class ClosedPeriod extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?FiscalPeriod $period = null,
    ) {
        parent::__construct($message);
    }

    public static function for(FiscalPeriod $period): self
    {
        return new self(
            sprintf(
                'The %s period is %s and cannot accept postings. Reopen it, or date the entry into an open period.',
                $period->label(),
                $period->status,
            ),
            $period,
        );
    }
}
