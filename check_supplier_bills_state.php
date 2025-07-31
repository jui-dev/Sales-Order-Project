<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\SupplierBill;
use App\Models\SupplierBillPayment;
use App\Models\JournalEntry;

echo "=== SUPPLIER BILLS CURRENT STATE ===\n\n";

// Check supplier bills
$supplierBills = SupplierBill::with('payment', 'vendor')->get();

if ($supplierBills->isEmpty()) {
    echo "No supplier bills found.\n";
} else {
    echo "Found " . $supplierBills->count() . " supplier bills:\n\n";
    foreach ($supplierBills as $bill) {
        echo "Bill: {$bill->formatted_id} (ID: {$bill->id})\n";
        echo "  Status: {$bill->status}\n";
        echo "  Vendor: " . ($bill->vendor ? $bill->vendor->name : 'No Vendor') . "\n";
        echo "  Amount: $" . number_format($bill->total_amount, 2) . "\n";
        echo "  Posted At: " . ($bill->posted_at ? $bill->posted_at->format('Y-m-d H:i:s') : 'Not posted') . "\n";
        echo "  Paid At: " . ($bill->paid_at ? $bill->paid_at->format('Y-m-d H:i:s') : 'Not paid') . "\n";
        echo "  Purchase Journal ID: " . ($bill->purchase_journal_id ?? 'null') . "\n";
        echo "  Payment Journal ID: " . ($bill->payment_journal_id ?? 'null') . "\n";
        
        if ($bill->payment) {
            echo "  Payment Record:\n";
            echo "    Status: {$bill->payment->payment_status}\n";
            echo "    Amount: $" . number_format($bill->payment->payment_amount, 2) . "\n";
            echo "    Paid At: " . ($bill->payment->paid_at ? $bill->payment->paid_at->format('Y-m-d H:i:s') : 'Not paid') . "\n";
            echo "    Payment Journal ID: " . ($bill->payment->payment_journal_id ?? 'null') . "\n";
        } else {
            echo "  Payment Record: None\n";
        }
        echo "\n";
    }
}

// Check payment records
echo "=== PAYMENT RECORDS ===\n\n";
$payments = SupplierBillPayment::with('supplierBill', 'vendor')->get();

if ($payments->isEmpty()) {
    echo "No payment records found.\n";
} else {
    echo "Found " . $payments->count() . " payment records:\n\n";
    foreach ($payments as $payment) {
        echo "Payment: {$payment->formatted_id} (ID: {$payment->id})\n";
        echo "  Bill: " . ($payment->supplierBill ? $payment->supplierBill->formatted_id : 'No Bill') . "\n";
        echo "  Vendor: " . ($payment->vendor ? $payment->vendor->name : 'No Vendor') . "\n";
        echo "  Status: {$payment->payment_status}\n";
        echo "  Amount: $" . number_format($payment->payment_amount, 2) . "\n";
        echo "  Payment Journal ID: " . ($payment->payment_journal_id ?? 'null') . "\n";
        echo "  Paid At: " . ($payment->paid_at ? $payment->paid_at->format('Y-m-d H:i:s') : 'Not paid') . "\n";
        echo "\n";
    }
}

// Check journal entries
echo "=== JOURNAL ENTRIES ===\n\n";
$journalEntries = JournalEntry::where('description', 'like', '%Supplier Bill%')->get();

if ($journalEntries->isEmpty()) {
    echo "No supplier bill related journal entries found.\n";
} else {
    echo "Found " . $journalEntries->count() . " supplier bill related journal entries:\n\n";
    foreach ($journalEntries as $entry) {
        echo "Journal Entry: {$entry->formatted_id} (ID: {$entry->id})\n";
        echo "  Description: {$entry->description}\n";
        echo "  Status: {$entry->status}\n";
        echo "  Created: " . $entry->created_at->format('Y-m-d H:i:s') . "\n";
        echo "\n";
    }
}

echo "=== END OF REPORT ===\n"; 