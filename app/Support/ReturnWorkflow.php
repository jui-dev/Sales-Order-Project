<?php

namespace App\Support;

use App\Models\CreditNote;
use App\Models\DebitNote;
use App\Models\StockTransaction;

/**
 * Single source of truth for a return's position in its workflow.
 *
 * Unlike the supply and order chains, returns have two shapes. Customer and
 * vendor returns raise a note and settle it:
 *
 *     Record Return -> Approve -> Credit/Debit Note -> Post to Ledger
 *
 * Retailer returns are an internal stock move with no financial side at all
 * (StockTransaction::generateReturnNotes() deliberately does nothing for them),
 * so they run:
 *
 *     Record Return -> Approve & Move Stock
 *
 * Returns a list of stages shaped for <x-workflow-rail>:
 *   ['name' => string, 'state' => done|current|todo, 'meta' => string, 'url' => ?string]
 */
final class ReturnWorkflow
{
    public const DONE    = 'done';
    public const CURRENT = 'current';
    public const TODO    = 'todo';

    /** Stage positions, used to suppress links back to the current page. */
    public const STAGE_RECORD  = 0;
    public const STAGE_APPROVE = 1;
    public const STAGE_NOTE    = 2;
    public const STAGE_LEDGER  = 3;

    /**
     * Stages for a return, seen from the page named by $selfStage.
     */
    public static function forReturn(StockTransaction $return, int $selfStage = self::STAGE_RECORD): array
    {
        $isRetailer = $return->isRetailerReturn();
        $returnUrl  = route('returns.show', $return->id);

        $approved  = in_array($return->status, [
            StockTransaction::STATUS_APPROVED,
            StockTransaction::STATUS_COMPLETED,
        ], true);
        $rejected  = $return->status === StockTransaction::STATUS_REJECTED;
        $cancelled = $return->status === StockTransaction::STATUS_CANCELLED;
        $closed    = $rejected || $cancelled;

        $stages = [
            [
                'name'  => 'Record Return',
                'state' => self::DONE,
                'meta'  => optional($return->transaction_date)->format('M d, Y') ?: 'Recorded',
                'url'   => $returnUrl,
            ],
            [
                // Stock moves here and nowhere else - the observer skips returns
                // so that recording one leaves inventory untouched.
                'name'  => $isRetailer ? 'Approve & Move Stock' : 'Approve Return',
                'state' => match (true) {
                    $approved => self::DONE,
                    $closed   => self::TODO,
                    default   => self::CURRENT,
                },
                'meta'  => match (true) {
                    $approved  => optional($return->approved_at)->format('M d, Y') ?: 'Stock moved',
                    $rejected  => 'Return rejected',
                    $cancelled => 'Return cancelled',
                    default    => 'Awaiting approval',
                },
                'url'   => $returnUrl,
            ],
        ];

        if ($isRetailer) {
            return self::suppressSelfLink($stages, $selfStage);
        }

        $note    = self::noteFor($return);
        $posted  = $note && $note->status === 'posted';
        $journal = $note?->journalEntry;

        $stages[] = [
            'name'  => $return->isCustomerReturn() ? 'Credit Note' : 'Debit Note',
            'state' => match (true) {
                $note !== null => self::DONE,
                $approved      => self::CURRENT,
                default        => self::TODO,
            },
            'meta'  => $note
                ? ($note->formatted_id ?: 'Raised')
                : ($closed ? 'Not raised' : 'Raised on approval'),
            'url'   => self::noteUrl($return, $note),
        ];

        $stages[] = [
            'name'  => 'Post to Ledger',
            'state' => match (true) {
                $journal && $journal->status === 'posted' => self::DONE,
                $note !== null                           => self::CURRENT,
                default                                  => self::TODO,
            },
            'meta'  => match (true) {
                $journal && $journal->status === 'posted' => 'Posted to accounts',
                $journal                                  => 'Draft entry, not posted',
                $posted                                   => 'Awaiting journal entry',
                $note !== null                            => 'Post the note first',
                default                                   => 'Nothing to post yet',
            },
            'url'   => self::noteUrl($return, $note),
        ];

        return self::suppressSelfLink($stages, $selfStage);
    }

    /**
     * Stages as seen from the credit or debit note raised by a return.
     *
     * The note and ledger stages both live on the note page, so calling from
     * either lands on the same suppressed link.
     */
    public static function forNote(CreditNote|DebitNote $note): array
    {
        $return = $note->returnTransaction;

        // A note whose return has gone missing has no chain to draw.
        if (! $return) {
            return [];
        }

        return self::forReturn($return, self::STAGE_NOTE);
    }

    /**
     * Never link a stage to the page the reader is already on. Several stages
     * can share one page - approving happens on the return, posting on the
     * note - so match on the destination rather than the index.
     */
    private static function suppressSelfLink(array $stages, int $selfStage): array
    {
        $selfUrl = $stages[$selfStage]['url'] ?? null;

        if ($selfUrl === null) {
            return $stages;
        }

        foreach ($stages as $index => $stage) {
            if ($stage['url'] === $selfUrl) {
                $stages[$index]['url'] = null;
            }
        }

        return $stages;
    }

    /**
     * The credit or debit note raised from this return, if approval got that far.
     */
    public static function noteFor(StockTransaction $return): CreditNote|DebitNote|null
    {
        if ($return->isCustomerReturn()) {
            return $return->creditNote;
        }

        if ($return->isVendorReturn()) {
            return $return->debitNote;
        }

        return null;
    }

    private static function noteUrl(StockTransaction $return, CreditNote|DebitNote|null $note): ?string
    {
        if (! $note) {
            return null;
        }

        return $return->isCustomerReturn()
            ? route('credit-notes.show', $note->id)
            : route('debit-notes.show', $note->id);
    }
}
