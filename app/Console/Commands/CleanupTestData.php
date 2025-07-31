<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupTestData extends Command
{
    protected $signature = 'cleanup:accounting-data';
    protected $description = 'Clean up all test data from journal entries, stock transactions, and stock transfers tables';

    public function handle()
    {
        $this->info('Starting accounting data cleanup...');

        try {
            // 1. Delete journal entry lines first (they reference journal entries)
            $journalEntryLinesCount = DB::table('journal_entry_lines')->count();
            DB::table('journal_entry_lines')->delete();
            $this->info("✅ Removed {$journalEntryLinesCount} journal entry lines");

            // 2. Delete journal entries
            $journalEntriesCount = DB::table('journal_entries')->count();
            DB::table('journal_entries')->delete();
            $this->info("✅ Removed {$journalEntriesCount} journal entries");

            // 3. Delete stock transfer items first (they reference stock transfers)
            $stockTransferItemsCount = DB::table('stock_transfer_items')->count();
            DB::table('stock_transfer_items')->delete();
            $this->info("✅ Removed {$stockTransferItemsCount} stock transfer items");

            // 4. Delete stock transfers
            $stockTransfersCount = DB::table('stock_transfers')->count();
            DB::table('stock_transfers')->delete();
            $this->info("✅ Removed {$stockTransfersCount} stock transfers");

            // 5. Delete stock transactions
            $stockTransactionsCount = DB::table('stock_transactions')->count();
            DB::table('stock_transactions')->delete();
            $this->info("✅ Removed {$stockTransactionsCount} stock transactions");

            // 6. Reset auto-increment for all cleaned tables
            $this->info("\nResetting auto-increment...");
            
            $tables = ['journal_entry_lines', 'journal_entries', 'stock_transfer_items', 'stock_transfers', 'stock_transactions'];
            foreach ($tables as $table) {
                DB::statement("ALTER TABLE {$table} AUTO_INCREMENT = 1");
                $this->info("✅ Reset {$table} auto-increment to 1");
            }

            // 7. Final verification
            $this->info("\nFinal verification:");
            $this->info("- Journal Entries: " . DB::table('journal_entries')->count());
            $this->info("- Journal Entry Lines: " . DB::table('journal_entry_lines')->count());
            $this->info("- Stock Transactions: " . DB::table('stock_transactions')->count());
            $this->info("- Stock Transfers: " . DB::table('stock_transfers')->count());
            $this->info("- Stock Transfer Items: " . DB::table('stock_transfer_items')->count());

            // Overall verification
            $allClean = (DB::table('journal_entries')->count() === 0 && 
                        DB::table('journal_entry_lines')->count() === 0 && 
                        DB::table('stock_transactions')->count() === 0 && 
                        DB::table('stock_transfers')->count() === 0 && 
                        DB::table('stock_transfer_items')->count() === 0);

            if ($allClean) {
                $this->info("\n🎉 All accounting and stock data cleaned successfully!");
                $this->info("All tables are clean and auto-increment starts from 1");
            } else {
                $this->warn("\n⚠️ Some cleanup verification failed");
            }

        } catch (\Exception $e) {
            $this->error("❌ Error: " . $e->getMessage());
        }
    }
} 