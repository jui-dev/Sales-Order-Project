<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Invoice;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // This data fix is timestamped ahead of the migration that creates the
        // invoices table, so on a fresh database there is nothing to fix yet.
        if (! Schema::hasTable('invoices')) {
            return;
        }

        // Fix existing invoices that don't have invoice numbers
        $invoices = Invoice::whereNull('invoice_number')->orWhere('invoice_number', '')->orderBy('id')->get();
        
        $nextNumber = 1;
        foreach ($invoices as $invoice) {
            $invoiceNumber = 'INV' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
            $invoice->update(['invoice_number' => $invoiceNumber]);
            $nextNumber++;
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No rollback needed for this data fix
    }
}; 