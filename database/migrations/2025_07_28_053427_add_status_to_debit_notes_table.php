<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('debit_notes', function (Blueprint $table) {
            // Add status column
            if (!Schema::hasColumn('debit_notes', 'status')) {
                $table->enum('status', ['draft', 'pending', 'issued', 'cancelled', 'expired'])->default('draft')->after('formatted_id');
            }
            
            // Add other essential columns
            if (!Schema::hasColumn('debit_notes', 'debit_note_number')) {
                $table->string('debit_note_number')->unique()->after('status');
            }
            
            if (!Schema::hasColumn('debit_notes', 'vendor_id')) {
                $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete()->after('debit_note_number');
            }
            
            if (!Schema::hasColumn('debit_notes', 'supplier_bill_id')) {
                $table->foreignId('supplier_bill_id')->nullable()->constrained('supplier_bills')->nullOnDelete()->after('vendor_id');
            }
            
            if (!Schema::hasColumn('debit_notes', 'return_transaction_id')) {
                $table->foreignId('return_transaction_id')->nullable()->constrained('stock_transactions')->nullOnDelete()->after('supplier_bill_id');
            }
            
            if (!Schema::hasColumn('debit_notes', 'total_amount')) {
                $table->decimal('total_amount', 15, 2)->default(0)->after('return_transaction_id');
            }
            
            if (!Schema::hasColumn('debit_notes', 'issue_date')) {
                $table->timestamp('issue_date')->nullable()->after('total_amount');
            }
            
            if (!Schema::hasColumn('debit_notes', 'reason')) {
                $table->string('reason')->nullable()->after('issue_date');
            }
            
            if (!Schema::hasColumn('debit_notes', 'notes')) {
                $table->text('notes')->nullable()->after('reason');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('debit_notes', function (Blueprint $table) {
            $table->dropForeign(['vendor_id']);
            $table->dropForeign(['supplier_bill_id']);
            $table->dropForeign(['return_transaction_id']);
            
            $table->dropColumn([
                'status', 'debit_note_number', 'vendor_id', 'supplier_bill_id', 
                'return_transaction_id', 'total_amount', 'issue_date', 'reason', 'notes'
            ]);
        });
    }
};
