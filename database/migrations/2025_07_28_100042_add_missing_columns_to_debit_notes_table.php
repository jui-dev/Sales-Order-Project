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
            // Add missing columns that should be in the debit_notes table
            if (!Schema::hasColumn('debit_notes', 'debit_note_number')) {
                $table->string('debit_note_number')->unique()->after('formatted_id');
            }
            
            if (!Schema::hasColumn('debit_notes', 'vendor_id')) {
                $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete()->after('debit_note_number');
            }
            
            if (!Schema::hasColumn('debit_notes', 'supplier_bill_id')) {
                $table->foreignId('supplier_bill_id')->nullable()->constrained('supplier_bills')->nullOnDelete()->after('vendor_id');
            }
            
            if (!Schema::hasColumn('debit_notes', 'journal_entry_id')) {
                $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete()->after('return_transaction_id');
            }
            
            if (!Schema::hasColumn('debit_notes', 'subtotal')) {
                $table->decimal('subtotal', 15, 2)->default(0)->after('journal_entry_id');
            }
            
            if (!Schema::hasColumn('debit_notes', 'tax_amount')) {
                $table->decimal('tax_amount', 15, 2)->default(0)->after('subtotal');
            }
            
            if (!Schema::hasColumn('debit_notes', 'discount_amount')) {
                $table->decimal('discount_amount', 15, 2)->default(0)->after('tax_amount');
            }
            
            if (!Schema::hasColumn('debit_notes', 'total_amount')) {
                $table->decimal('total_amount', 15, 2)->default(0)->after('discount_amount');
            }
            
            if (!Schema::hasColumn('debit_notes', 'remaining_amount')) {
                $table->decimal('remaining_amount', 15, 2)->default(0)->after('total_amount');
            }
            
            if (!Schema::hasColumn('debit_notes', 'currency')) {
                $table->string('currency', 3)->default('USD')->after('remaining_amount');
            }
            
            if (!Schema::hasColumn('debit_notes', 'exchange_rate')) {
                $table->decimal('exchange_rate', 10, 4)->default(1.0000)->after('currency');
            }
            
            if (!Schema::hasColumn('debit_notes', 'reference_number')) {
                $table->string('reference_number')->nullable()->after('exchange_rate');
            }
            
            if (!Schema::hasColumn('debit_notes', 'reason')) {
                $table->string('reason')->nullable()->after('reference_number');
            }
            
            if (!Schema::hasColumn('debit_notes', 'notes')) {
                $table->text('notes')->nullable()->after('reason');
            }
            
            if (!Schema::hasColumn('debit_notes', 'issue_date')) {
                $table->timestamp('issue_date')->nullable()->after('notes');
            }
            
            if (!Schema::hasColumn('debit_notes', 'expiry_date')) {
                $table->timestamp('expiry_date')->nullable()->after('issue_date');
            }
            
            if (!Schema::hasColumn('debit_notes', 'is_partial')) {
                $table->boolean('is_partial')->default(false)->after('expiry_date');
            }
            
            if (!Schema::hasColumn('debit_notes', 'is_recurring')) {
                $table->boolean('is_recurring')->default(false)->after('is_partial');
            }
            
            if (!Schema::hasColumn('debit_notes', 'recurring_frequency')) {
                $table->string('recurring_frequency')->nullable()->after('is_recurring');
            }
            
            if (!Schema::hasColumn('debit_notes', 'next_recurring_date')) {
                $table->date('next_recurring_date')->nullable()->after('recurring_frequency');
            }
            
            if (!Schema::hasColumn('debit_notes', 'metadata')) {
                $table->json('metadata')->nullable()->after('next_recurring_date');
            }
            
            if (!Schema::hasColumn('debit_notes', 'tax_breakdown')) {
                $table->json('tax_breakdown')->nullable()->after('metadata');
            }
            
            if (!Schema::hasColumn('debit_notes', 'discount_breakdown')) {
                $table->json('discount_breakdown')->nullable()->after('tax_breakdown');
            }
            
            if (!Schema::hasColumn('debit_notes', 'created_by')) {
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete()->after('discount_breakdown');
            }
            
            if (!Schema::hasColumn('debit_notes', 'cancelled_by')) {
                $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete()->after('created_by');
            }
            
            if (!Schema::hasColumn('debit_notes', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('cancelled_by');
            }
            
            if (!Schema::hasColumn('debit_notes', 'cancellation_reason')) {
                $table->text('cancellation_reason')->nullable()->after('cancelled_at');
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
            $table->dropForeign(['journal_entry_id']);
            $table->dropForeign(['created_by']);
            $table->dropForeign(['cancelled_by']);
            
            $table->dropColumn([
                'debit_note_number', 'vendor_id', 'supplier_bill_id', 'journal_entry_id',
                'subtotal', 'tax_amount', 'discount_amount', 'total_amount', 'remaining_amount',
                'currency', 'exchange_rate', 'reference_number', 'reason', 'notes', 'issue_date',
                'expiry_date', 'is_partial', 'is_recurring', 'recurring_frequency', 'next_recurring_date',
                'metadata', 'tax_breakdown', 'discount_breakdown', 'created_by', 'cancelled_by',
                'cancelled_at', 'cancellation_reason'
            ]);
        });
    }
};
