<?php

namespace App\Observers;

use App\Models\ReturnRecord;
use App\Models\JournalEntry;
use App\Models\Invoice;
use App\Services\AccountingService;
use Illuminate\Support\Facades\App;

class ReturnRecordObserver
{
    public function updated(ReturnRecord $record): void
    {
        if (! $record->wasChanged('status') || $record->status !== 'completed') {
            return;
        }

        // Ensure uniqueness
        $exists = JournalEntry::where('source_type', $record->getMorphClass())
            ->where('source_id', $record->getKey())
            ->exists();
        if ($exists) {
            return;
        }

        // For now we only handle returns related to an Invoice
        if ($record->reference_type !== Invoice::class) {
            return;
        }

        /** @var Invoice|null $invoice */
        $invoice = Invoice::find($record->reference_id);
        if (! $invoice) {
            return;
        }

        $totalSale = round($invoice->total, 2);

        // Estimate cost of goods
        $totalCost = 0;
        $order = $invoice->relationLoaded('order') ? $invoice->order : $invoice->order;
        if ($order) {
            $order->loadMissing('orderItems.product');
            $totalCost = $order->orderItems->sum(function ($item) {
                $unitCost = $item->unit_cost ?? ($item->product->purchase_price ?? 0);
                return $unitCost * $item->quantity;
            });
        }
        $totalCost = round($totalCost, 2);

        $lines = [
            [
                'account_code' => '5200', // Sales Returns & Allowances (Contra Revenue)
                'debit'        => $totalSale,
                'credit'       => 0,
                'description'  => 'Return – Invoice #'.$invoice->invoice_number,
            ],
            [
                'account_code' => '1100', // Accounts Receivable
                'debit'        => 0,
                'credit'       => $totalSale,
                'description'  => 'Reverse A/R – Invoice #'.$invoice->invoice_number,
            ],
        ];

        if ($totalCost > 0) {
            $lines[] = [
                'account_code' => '1200', // Inventory
                'debit'        => $totalCost,
                'credit'       => 0,
                'description'  => 'Inventory back in – Return #'.$record->id,
            ];
            $lines[] = [
                'account_code' => '5000', // COGS
                'debit'        => 0,
                'credit'       => $totalCost,
                'description'  => 'Reverse COGS – Return #'.$record->id,
            ];
        }

        /** @var AccountingService $acct */
        $acct = App::make(AccountingService::class);
        $acct->post($lines, $record->return_date ?? now(), 'Product Return #'.$record->id, $record);
    }
} 