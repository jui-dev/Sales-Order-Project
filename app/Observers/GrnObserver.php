<?php

namespace App\Observers;

use App\Accounting\PostingEngine;
use App\Models\Grn;
use App\Models\AuditLog;
use App\Services\GrnService;

class GrnObserver
{
    public function __construct(
        private readonly PostingEngine $ledger,
        private readonly GrnService $stock,
    ) {
    }

    /**
     * Handle the Grn "updated" event.
     */
    public function updated(Grn $grn): void
    {
        // Trigger only when status transitions to "posted"
        if (! $grn->wasChanged('status') || $grn->status !== 'posted') {
            return;
        }

        // ------------------------------------------------------------------
        // The goods are here. Stock and ledger move on this one event, in that
        // order: booking the value of goods that never reached a shelf is
        // exactly the drift the goods-receipt rule was written to close, and
        // that is what happened while the stock was posted from GrnService and
        // the ledger from here.
        // ------------------------------------------------------------------
        $this->stock->postStock($grn);

        // ------------------------------------------------------------------
        // Inventory is debited and the other side parks in Goods Received Not
        // Invoiced until the vendor bills for it.
        //
        // The ledger used to wait for the supplier bill to be posted - an
        // office task that happens later and sometimes not at all - so stock
        // could be sold and its cost relieved from an inventory balance that
        // had never been debited.
        // ------------------------------------------------------------------
        $this->ledger->postFor($grn);

        // ------------------------------------------------------------------
        // Auto-create the Supplier Bill as a draft.
        // ------------------------------------------------------------------

        // Prevent duplicate bill generation
        if ($grn->supplierBill()->exists()) {
            return;
        }

        $supply = $grn->supply()->with('items')->first();
        $vendorId = $supply?->vendor_id;
        if (! $supply || ! $vendorId) {
            return; // Cannot create bill without vendor info
        }

        // Build bill items from supply items
        $billItemsData = [];
        $totalAmount   = 0;
        foreach ($supply->items as $item) {
            $qty    = $item->quantity;
            $unit   = $item->unit_cost;
            $subtot = round($qty * $unit, 2);
            $totalAmount += $subtot;
            $billItemsData[] = [
                'product_id' => $item->product_id,
                'quantity'   => $qty,
                'unit_cost'  => $unit,
                'subtotal'   => $subtot,
            ];
        }

        // No formatted_id: HasFormattedId derives SB-0012 from the primary key
        // and its accessor shadows the column, so the placeholder written here
        // and the SB-000012 that replaced it were both write-only - and padded
        // to a different width than the reference everyone actually saw.
        $bill = \App\Models\SupplierBill::create([
            'grn_id'       => $grn->id,
            'vendor_id'    => $vendorId,
            'bill_date'    => now()->toDateString(),
            'description'  => 'Supplier Bill for GRN '.$grn->id,
            'total_amount' => round($totalAmount, 2),
            'status'       => 'draft',
        ]);

        // Attach items
        foreach ($billItemsData as $data) {
            $bill->items()->create($data);
        }

        // Audit trail for bill creation (draft)
        AuditLog::create([
            'user_id'      => auth()->id() ?? 1, // Default to user ID 1 if not authenticated
            'action'       => 'supplier_bill_created',
            'description'  => 'Supplier Bill '.$bill->formatted_id.' created from GRN '.$grn->id.'.',
            'subject_type' => $bill->getMorphClass(),
            'subject_id'   => $bill->getKey(),
        ]);
    }
} 