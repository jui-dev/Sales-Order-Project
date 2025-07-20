<?php

namespace App\Observers;

use App\Models\Grn;
use App\Models\AuditLog;

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

        // ------------------------------------------------------------------
        // Auto-create Supplier Bill (draft) instead of posting journal entry
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

        $bill = \App\Models\SupplierBill::create([
            // Temporary placeholder; will update after ID generated
            'formatted_id' => 'TMP-'.uniqid(),
            'grn_id'       => $grn->id,
            'vendor_id'    => $vendorId,
            'bill_date'    => now()->toDateString(),
            'description'  => 'Supplier Bill for GRN '.$grn->id,
            'total_amount' => round($totalAmount, 2),
            'status'       => 'draft',
        ]);

        // Update formatted_id to proper code
        $bill->formatted_id = 'SB-' . str_pad((string) $bill->id, 6, '0', STR_PAD_LEFT);
        $bill->saveQuietly();

        // Attach items
        foreach ($billItemsData as $data) {
            $bill->items()->create($data);
        }

        // Audit trail for bill creation (draft)
        AuditLog::create([
            'user_id'      => auth()->id(),
            'action'       => 'supplier_bill_created',
            'description'  => 'Supplier Bill '.$bill->formatted_id.' created from GRN '.$grn->id.'.',
            'subject_type' => $bill->getMorphClass(),
            'subject_id'   => $bill->getKey(),
        ]);
    }
} 