<?php

namespace App\Console\Commands;

use App\Accounting\ChartOfAccounts;
use App\Accounting\PostingEngine;
use App\Accounting\Posting\PostingRuleRegistry;
use App\Accounting\Reconciliation\ReconciliationService;
use App\Models\FiscalPeriod;
use App\Models\JournalEntry;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Delete the ledger and rebuild it from the business documents.
 *
 * The ledger is a function of the documents and holds nothing the documents do
 * not already know. Being able to prove that - by throwing it away and getting
 * the same books back - is worth more than any amount of assurance that the
 * posting code is correct, and it is what makes a change to a posting rule
 * something that can be applied to history rather than only to what happens
 * next.
 */
class RebuildLedger extends Command
{
    protected $signature = 'accounting:rebuild
                            {--force : Do not ask for confirmation}
                            {--keep-manual : Leave manually typed entries in place}';

    protected $description = 'Discard the ledger and replay every business document through the posting rules';

    public function handle(
        ChartOfAccounts $chart,
        PostingRuleRegistry $rules,
        PostingEngine $engine,
        ReconciliationService $reconciliation,
    ): int {
        if (! $this->option('force') && ! $this->confirm('This deletes every journal entry and rebuilds them from the documents. Continue?')) {
            return self::FAILURE;
        }

        $this->info('Syncing the chart of accounts...');
        $chart->sync();

        if ($missing = $chart->unresolvableRoles()) {
            $this->error('These account roles cannot be resolved: ' . implode(', ', $missing));

            return self::FAILURE;
        }

        $this->discardLedger();

        // Periods have to be open again, or the replay is refused by the very
        // guard that makes closed periods worth having.
        FiscalPeriod::query()->update([
            'status'           => FiscalPeriod::STATUS_OPEN,
            'closed_at'        => null,
            'closing_entry_id' => null,
        ]);

        $posted = $this->replay($rules, $engine);

        $this->newLine();
        $this->info(sprintf('Replayed %d document(s).', $posted));

        return $this->report($reconciliation);
    }

    /**
     * Every document, oldest first, through every rule that applies to it.
     */
    private function replay(PostingRuleRegistry $rules, PostingEngine $engine): int
    {
        $posted = 0;

        // Grouped by document class so each is walked once, whatever number of
        // rules read it.
        $documentTypes = [];

        foreach ($rules->all() as $rule) {
            $documentTypes[$rule->documentType()] = true;
        }

        foreach (array_keys($documentTypes) as $class) {
            /** @var class-string<Model> $class */
            $query = $class::query();

            $this->line(sprintf('  %s...', class_basename($class)));

            $query->orderBy('id')->chunkById(200, function ($documents) use ($engine, &$posted) {
                foreach ($documents as $document) {
                    // Replay order matters only within a document; across
                    // documents the entries are dated, not sequenced.
                    $entries = $engine->postFor($document);

                    if ($entries !== []) {
                        $posted++;
                    }
                }
            });
        }

        return $posted;
    }

    private function discardLedger(): void
    {
        $this->info('Discarding the existing ledger...');

        DB::transaction(function () {
            foreach ([
                'credit_notes'           => ['journal_entry_id'],
                'debit_notes'            => ['journal_entry_id'],
                'supplier_bills'         => ['purchase_journal_id', 'payment_journal_id'],
                'supplier_bill_payments' => ['payment_journal_id'],
            ] as $table => $columns) {
                foreach ($columns as $column) {
                    DB::table($table)->update([$column => null]);
                }
            }

            $entries = JournalEntry::query();

            if ($this->option('keep-manual')) {
                $entries->where('origin', JournalEntry::ORIGIN_SYSTEM);
            }

            $ids = $entries->pluck('id');

            DB::table('journal_entries')->whereIn('id', $ids)->update([
                'reverses_journal_id'   => null,
                'linked_debit_note_id'  => null,
                'linked_credit_note_id' => null,
            ]);

            DB::table('journal_entry_lines')->whereIn('journal_entry_id', $ids)->delete();
            DB::table('journal_entries')->whereIn('id', $ids)->delete();
        });
    }

    private function report(ReconciliationService $reconciliation): int
    {
        $this->newLine();
        $this->info('Reconciliation');

        $rows = [];
        $failed = false;

        foreach ($reconciliation->run() as $check) {
            $failed = $failed || ! $check->passed;

            $rows[] = [
                $check->passed ? 'OK' : 'FAIL',
                $check->title,
                $check->ledger->toDecimal(),
                $check->expected->toDecimal(),
                $check->difference->toDecimal(),
            ];
        }

        $this->table(['', 'Check', 'Ledger', 'Expected', 'Difference'], $rows);

        if ($failed) {
            $this->error('The rebuilt ledger does not reconcile.');

            return self::FAILURE;
        }

        $this->info('Everything ties out.');

        return self::SUCCESS;
    }
}
