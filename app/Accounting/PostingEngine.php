<?php

namespace App\Accounting;

use App\Accounting\Posting\PostingRule;
use App\Accounting\Posting\PostingRuleRegistry;
use App\Models\AuditLog;
use App\Models\JournalEntry;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The only thing that writes to the ledger.
 *
 * A system entry is posted the moment it is created. The old design created
 * every entry as a draft and waited for a human to approve and then post it
 * one at a time - including entries the system had generated itself from a
 * document that had already been approved. The books were therefore always
 * behind reality, and a statement drawn from posted entries alone looked empty
 * rather than incomplete. Review belongs on entries a person typed, and those
 * still take the draft path.
 */
class PostingEngine
{
    public function __construct(
        private readonly AccountResolver $accounts,
        private readonly PeriodGuard $periods,
        private readonly PostingRuleRegistry $rules,
    ) {
    }

    // ------------------------------------------------------------------
    // Posting from documents
    // ------------------------------------------------------------------

    /**
     * Run every rule that applies to this document in its current state.
     *
     * @return array<int,JournalEntry> the entries written, which may be empty
     */
    public function postFor(Model $document): array
    {
        $written = [];

        foreach ($this->rules->applicableTo($document) as $rule) {
            if ($entry = $this->runRule($rule, $document)) {
                $written[] = $entry;
            }
        }

        return $written;
    }

    /**
     * Run one rule, unless it has already been run for this document.
     */
    public function runRule(PostingRule $rule, Model $document): ?JournalEntry
    {
        if ($existing = $this->existingEntry($document, $rule->key())) {
            return $existing;
        }

        $draft = $rule->build($document);

        return $this->write($draft, JournalEntry::ORIGIN_SYSTEM);
    }

    public function existingEntry(Model $document, string $ruleKey): ?JournalEntry
    {
        return JournalEntry::where('source_type', $document->getMorphClass())
            ->where('source_id', $document->getKey())
            ->where('rule_key', $ruleKey)
            ->first();
    }

    // ------------------------------------------------------------------
    // Writing
    // ------------------------------------------------------------------

    /**
     * Turn a draft into rows.
     *
     * A system entry lands posted; a manual one lands draft and takes the
     * approval path. Returns null when the draft had nothing to say.
     */
    public function write(JournalDraft $draft, string $origin = JournalEntry::ORIGIN_SYSTEM): ?JournalEntry
    {
        if ($draft->isEmpty()) {
            return null;
        }

        $draft->assertValid();

        $date = $draft->date();

        // A closed period refuses the posting outright rather than letting it
        // silently restate a statement someone has already acted on.
        $this->periods->assertOpen($date);

        $posted = $origin === JournalEntry::ORIGIN_SYSTEM;

        try {
            return DB::transaction(function () use ($draft, $date, $origin, $posted) {
                $entry = JournalEntry::create([
                    'entry_date'   => $date,
                    'description'  => $draft->description(),
                    'status'       => $posted ? JournalEntry::STATUS_POSTED : JournalEntry::STATUS_DRAFT,
                    'origin'       => $origin,
                    'posted_at'    => $posted ? now() : null,
                    'rule_key'     => $draft->ruleKey,
                    'source_type'  => $draft->source?->getMorphClass() ?? '',
                    'source_id'    => $draft->source?->getKey() ?? 0,
                    'formatted_id' => (string) \Illuminate\Support\Str::uuid(),
                ]);

                // formatted_id is NOT NULL and the reference is derived from
                // the primary key, so the row goes in behind a placeholder and
                // is stamped once the key exists.
                $entry->forceFill(['formatted_id' => $entry->code])->saveQuietly();

                if (! $draft->source) {
                    $entry->updateQuietly([
                        'source_type' => $entry->getMorphClass(),
                        'source_id'   => $entry->getKey(),
                    ]);
                }

                foreach ($draft->lines() as $line) {
                    $entry->lines()->create([
                        'account_id'    => $this->accountIdFor($line['role']),
                        'debit'         => $line['debit']->toDecimal(),
                        'credit'        => $line['credit']->toDecimal(),
                        'description'   => $line['description'],
                        'party_type'    => $line['party']?->getMorphClass(),
                        'party_id'      => $line['party']?->getKey(),
                        'location_type' => $line['location']?->getMorphClass(),
                        'location_id'   => $line['location']?->getKey(),
                        'product_id'    => $line['product_id'],
                        'currency'      => config('accounting.base_currency'),
                    ]);
                }

                $this->log($entry, $posted ? 'journal_posted' : 'journal_created');

                return $entry;
            });
        } catch (QueryException $e) {
            // Two requests raced to post the same document and rule. The
            // unique index is the authority; whichever lost reads the winner
            // rather than failing the business operation behind it.
            if ($draft->source && $draft->ruleKey && $existing = $this->existingEntry($draft->source, $draft->ruleKey)) {
                return $existing;
            }

            throw $e;
        }
    }

    // ------------------------------------------------------------------
    // Reversal
    // ------------------------------------------------------------------

    /**
     * Mirror a posted entry, at a date in an open period.
     *
     * A posted entry is never edited or deleted. Correcting one means saying
     * so on the books, which is what this does - and it works for any entry,
     * not only the credit and debit notes that used to have reversal logic of
     * their own.
     */
    public function reverse(JournalEntry $entry, CarbonInterface|string|null $date = null, ?string $reason = null): JournalEntry
    {
        if (! $entry->isPosted()) {
            throw new \RuntimeException('Only a posted entry needs reversing; discard or reject an unposted one instead.');
        }

        if ($existing = $entry->reverseJournals()->first()) {
            return $existing;
        }

        $date = $date ? Carbon::parse($date) : Carbon::now();
        $this->periods->assertOpen($date);

        $description = $reason
            ? sprintf('Reversal of %s - %s', $entry->formatted_id, $reason)
            : sprintf('Reversal of %s', $entry->formatted_id);

        return DB::transaction(function () use ($entry, $date, $description) {
            $reversal = JournalEntry::create([
                'entry_date'          => $date,
                'description'         => $description,
                'status'              => JournalEntry::STATUS_POSTED,
                'origin'              => $entry->origin,
                'posted_at'           => now(),
                'rule_key'            => $entry->rule_key ? $entry->rule_key . ':reversal' : null,
                'is_reverse'          => true,
                'reverses_journal_id' => $entry->id,
                'source_type'         => $entry->source_type,
                'source_id'           => $entry->source_id,
                'formatted_id'        => (string) \Illuminate\Support\Str::uuid(),
            ]);

            $reversal->forceFill(['formatted_id' => $reversal->code])->saveQuietly();

            foreach ($entry->lines as $line) {
                $reversal->lines()->create([
                    'account_id'    => $line->account_id,
                    // Sides swapped; dimensions carried over unchanged so the
                    // reversal lands on exactly the same balances.
                    'debit'         => $line->credit,
                    'credit'        => $line->debit,
                    'description'   => $description,
                    'party_type'    => $line->party_type,
                    'party_id'      => $line->party_id,
                    'location_type' => $line->location_type,
                    'location_id'   => $line->location_id,
                    'product_id'    => $line->product_id,
                    'currency'      => $line->currency,
                ]);
            }

            $this->log($reversal, 'journal_reversed');

            return $reversal;
        });
    }

    /**
     * A line names either a role or, for the closing and opening entries, the
     * account itself.
     */
    private function accountIdFor(\App\Accounting\AccountRole|\App\Models\Account $account): int
    {
        return $account instanceof \App\Models\Account
            ? $account->id
            : $this->accounts->idFor($account);
    }

    private function log(JournalEntry $entry, string $action): void
    {
        AuditLog::create([
            'user_id'      => auth()->id() ?? 1,
            'action'       => $action,
            'description'  => sprintf(
                'Journal entry %s (%s) %s.',
                $entry->formatted_id,
                $entry->origin,
                $entry->isPosted() ? 'posted' : 'created as draft',
            ),
            'subject_type' => $entry->getMorphClass(),
            'subject_id'   => $entry->getKey(),
        ]);
    }
}
