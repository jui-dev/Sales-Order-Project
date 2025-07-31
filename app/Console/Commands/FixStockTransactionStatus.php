<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\StockTransaction;
use App\Models\Grn;
use App\Models\PickingList;
// ReturnRecord import removed - model no longer exists

class FixStockTransactionStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stock-transactions:fix-status {--dry-run : Show what would be changed without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix the status of stock transactions based on their transaction type and reference';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting stock transaction status fix...');
        
        $dryRun = $this->option('dry-run');
        $changes = [];

        // Fix GRN-related stock transactions (should be 'completed')
        $grnTransactions = StockTransaction::where('reference_type', Grn::class)
            ->where('transaction_type', StockTransaction::TYPE_STOCK_IN)
            ->where('status', 'pending')
            ->get();

        foreach ($grnTransactions as $transaction) {
            $changes[] = [
                'id' => $transaction->id,
                'current_status' => $transaction->status,
                'new_status' => 'completed',
                'reason' => 'GRN stock transactions should be completed when posted'
            ];
            
            if (!$dryRun) {
                $transaction->update(['status' => 'completed']);
            }
        }

        // Fix PickingList-related stock transactions (should be 'completed')
        $pickingTransactions = StockTransaction::where('reference_type', PickingList::class)
            ->whereIn('transaction_type', [
                StockTransaction::TYPE_STOCK_TRANSFER,
                StockTransaction::TYPE_ORDER_FULFILLMENT
            ])
            ->where('status', 'pending')
            ->get();

        foreach ($pickingTransactions as $transaction) {
            $changes[] = [
                'id' => $transaction->id,
                'current_status' => $transaction->status,
                'new_status' => 'completed',
                'reason' => 'PickingList stock transactions should be completed when picking is finalized'
            ];
            
            if (!$dryRun) {
                $transaction->update(['status' => 'completed']);
            }
        }

        // ReturnRecord-related stock transactions removed - using unified approach

        // Show summary
        if (empty($changes)) {
            $this->info('No stock transactions need status updates.');
            return;
        }

        $this->info("Found " . count($changes) . " stock transactions that need status updates:");

        $headers = ['ID', 'Current Status', 'New Status', 'Reason'];
        $rows = array_map(function ($change) {
            return [
                $change['id'],
                $change['current_status'],
                $change['new_status'],
                $change['reason']
            ];
        }, $changes);

        $this->table($headers, $rows);

        if ($dryRun) {
            $this->warn('This was a dry run. No changes were made.');
            $this->info('Run without --dry-run to apply the changes.');
        } else {
            $this->info('Stock transaction statuses have been updated successfully!');
        }
    }
} 