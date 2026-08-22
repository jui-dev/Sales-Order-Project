<?php

namespace App\Accounting\Posting;

use App\Accounting\JournalDraft;
use Illuminate\Database\Eloquent\Model;

/**
 * How one business event becomes a journal entry.
 *
 * All posting logic lives in an implementation of this interface and nowhere
 * else. It used to be spread across four observers and four services, each
 * naming account codes as string literals, so the entries a transaction raised
 * could only be discovered by reading whichever class happened to trigger it.
 */
interface PostingRule
{
    /**
     * Stable identifier, unique per rule.
     *
     * Stored on the entry, so a document that raises more than one entry - a
     * supplier bill raises one when it is posted and another when it is paid -
     * keeps them apart, and so re-running a rule is a no-op rather than a
     * duplicate.
     */
    public function key(): string;

    /** The model class this rule reads. */
    public function documentType(): string;

    /**
     * Whether the document is in the state this rule posts for.
     *
     * State, not class: a supplier bill supports both the purchase rule and
     * the payment rule, but only one of them applies at a time.
     */
    public function appliesTo(Model $document): bool;

    /** Build the entry. May return an empty draft when there is nothing to post. */
    public function build(Model $document): JournalDraft;
}
