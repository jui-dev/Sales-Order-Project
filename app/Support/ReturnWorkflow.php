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

    /**
     * Stages for a return, seen from the return detail page.
     */
    public static function forReturn(StockTransaction $return): array
    {
        $isRetailer = $return->isRetailerReturn();

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
                'url'   => null,
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
                'url'   => null,
            ],
        ];

        if ($isRetailer) {
            return $stages;
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
