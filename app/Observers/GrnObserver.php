<?php

namespace App\Observers;

use App\Models\Grn;
use App\Models\JournalEntry;
use App\Models\AuditLog;
use App\Services\AccountingService;
use Illuminate\Support\Facades\App;

class GrnObserver
{
    /**
     * Handle the Grn "updated" event.
     */
    public function updated(Grn $grn): void
    {
        // Trigger only when status transitions to "posted"
        if (! $grn->wasChanged('status') || $grn->status !== 'posted') {
            return;
        }

        // Guard against duplicate postings (idempotent)
        $exists = JournalEntry::where('source_type', $grn->getMorphClass())
            ->where('source_id', $grn->getKey())
            ->exists();
        if ($exists) {
            return; // Already posted
        }

        // Calculate total cost of goods received
        $supply     = $grn->supply()->with('items')->first();
        $totalCost  = $supply?->total_cost;
        if ($totalCost === null) {
            $totalCost = $supply?->items->sum(fn ($i) => ($i->unit_cost ?? 0) * ($i->quantity ?? 0));
        }
        $totalCost = round($totalCost, 2);
        if ($totalCost <= 0) {
            return; // Nothing to post
        }

        // Post journal entry (Inventory Dr / Accounts Payable Cr)
        /** @var AccountingService $acct */
        $acct = App::make(AccountingService::class);
        $acct->post([
            [
                'account_code' => '1200', // Inventory
                'debit'        => $totalCost,
                'credit'       => 0,
                'description'  => 'Inventory received – GRN #'.$grn->id,
            ],
            [
                'account_code' => '2000', // Accounts Payable
                'debit'        => 0,
                'credit'       => $totalCost,
                'description'  => 'Liability to vendor – GRN #'.$grn->id,
            ],
        ], $grn->received_date ?? now(), 'Goods Receipt Note #'.$grn->id, $grn);

        // Audit trail
        AuditLog::create([
            'user_id'      => auth()->id(),
            'action'       => 'grn_posted',
            'description'  => 'GRN #' . $grn->id . ' posted.',
            'subject_type' => $grn->getMorphClass(),
            'subject_id'   => $grn->getKey(),
        ]);
    }
} 