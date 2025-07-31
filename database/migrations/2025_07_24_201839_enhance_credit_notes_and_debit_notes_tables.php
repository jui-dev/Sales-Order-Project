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
        // Enhance credit_notes table
        Schema::table('credit_notes', function (Blueprint $table) {
            // Add formatted_id if not exists
            if (!Schema::hasColumn('credit_notes', 'formatted_id')) {
                $table->string('formatted_id')->unique()->after('id');
            }
            
            // Basic Information
            if (!Schema::hasColumn('credit_notes', 'credit_note_number')) {
                $table->string('credit_note_number')->unique()->after('formatted_id');
            }
            if (!Schema::hasColumn('credit_notes', 'status')) {
                $table->enum('status', ['draft', 'pending', 'issued', 'cancelled', 'expired'])->default('draft')->after('credit_note_number');
            }
            if (!Schema::hasColumn('credit_notes', 'issue_date')) {
                $table->timestamp('issue_date')->nullable()->after('status');
            }
            if (!Schema::hasColumn('credit_notes', 'expiry_date')) {
                $table->timestamp('expiry_date')->nullable()->after('issue_date');
            }
            
            // Relationships
            if (!Schema::hasColumn('credit_notes', 'customer_id')) {
                $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete()->after('expiry_date');
            }
            if (!Schema::hasColumn('credit_notes', 'invoice_id')) {
                $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete()->after('customer_id');
            }
            if (!Schema::hasColumn('credit_notes', 'return_transaction_id')) {
                $table->foreignId('return_transaction_id')->nullable()->constrained('stock_transactions')->nullOnDelete()->after('invoice_id');
            }
            
            // Financial Information
            if (!Schema::hasColumn('credit_notes', 'subtotal')) {
                $table->decimal('subtotal', 15, 2)->default(0)->after('return_transaction_id');
            }
            if (!Schema::hasColumn('credit_notes', 'tax_amount')) {
                $table->decimal('tax_amount', 15, 2)->default(0)->after('subtotal');
            }
            if (!Schema::hasColumn('credit_notes', 'discount_amount')) {
                $table->decimal('discount_amount', 15, 2)->default(0)->after('tax_amount');
            }
            if (!Schema::hasColumn('credit_notes', 'total_amount')) {
                $table->decimal('total_amount', 15, 2)->default(0)->after('discount_amount');
            }
            if (!Schema::hasColumn('credit_notes', 'remaining_amount')) {
                $table->decimal('remaining_amount', 15, 2)->default(0)->after('total_amount');
            }
            if (!Schema::hasColumn('credit_notes', 'applied_amount')) {
                $table->decimal('applied_amount', 15, 2)->default(0)->after('remaining_amount');
            }
            
            // Currency and Exchange Rate
            if (!Schema::hasColumn('credit_notes', 'currency')) {
                $table->string('currency', 3)->default('USD')->after('applied_amount');
            }
            if (!Schema::hasColumn('credit_notes', 'exchange_rate')) {
                $table->decimal('exchange_rate', 10, 4)->default(1.0000)->after('currency');
            }
            if (!Schema::hasColumn('credit_notes', 'total_amount_base_currency')) {
                $table->decimal('total_amount_base_currency', 15, 2)->default(0)->after('exchange_rate');
            }
            
            // Additional Information
            if (!Schema::hasColumn('credit_notes', 'reference_number')) {
                $table->string('reference_number')->nullable()->after('total_amount_base_currency');
            }
            if (!Schema::hasColumn('credit_notes', 'reason')) {
                $table->string('reason')->nullable()->after('reference_number');
            }
            if (!Schema::hasColumn('credit_notes', 'notes')) {
                $table->text('notes')->nullable()->after('reason');
            }
            if (!Schema::hasColumn('credit_notes', 'terms_and_conditions')) {
                $table->text('terms_and_conditions')->nullable()->after('notes');
            }
            if (!Schema::hasColumn('credit_notes', 'is_partial')) {
                $table->boolean('is_partial')->default(false)->after('terms_and_conditions');
            }
            if (!Schema::hasColumn('credit_notes', 'is_recurring')) {
                $table->boolean('is_recurring')->default(false)->after('is_partial');
            }
            if (!Schema::hasColumn('credit_notes', 'recurring_frequency')) {
                $table->string('recurring_frequency')->nullable()->after('is_recurring');
            }
            if (!Schema::hasColumn('credit_notes', 'next_recurring_date')) {
                $table->date('next_recurring_date')->nullable()->after('recurring_frequency');
            }
            
            // Metadata
            if (!Schema::hasColumn('credit_notes', 'metadata')) {
                $table->json('metadata')->nullable()->after('next_recurring_date');
            }
            if (!Schema::hasColumn('credit_notes', 'tax_breakdown')) {
                $table->json('tax_breakdown')->nullable()->after('metadata');
            }
            if (!Schema::hasColumn('credit_notes', 'discount_breakdown')) {
                $table->json('discount_breakdown')->nullable()->after('tax_breakdown');
            }
            
            // Audit Fields
            if (!Schema::hasColumn('credit_notes', 'created_by')) {
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete()->after('discount_breakdown');
            }
            if (!Schema::hasColumn('credit_notes', 'updated_by')) {
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete()->after('created_by');
            }
            if (!Schema::hasColumn('credit_notes', 'approved_by')) {
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete()->after('updated_by');
            }
            if (!Schema::hasColumn('credit_notes', 'cancelled_by')) {
                $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete()->after('approved_by');
            }
            if (!Schema::hasColumn('credit_notes', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('cancelled_by');
            }
            if (!Schema::hasColumn('credit_notes', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('approved_at');
            }
            if (!Schema::hasColumn('credit_notes', 'cancellation_reason')) {
                $table->text('cancellation_reason')->nullable()->after('cancelled_at');
            }
        });

        // Enhance debit_notes table
        Schema::table('debit_notes', function (Blueprint $table) {
            // Add formatted_id if not exists
            if (!Schema::hasColumn('debit_notes', 'formatted_id')) {
                $table->string('formatted_id')->unique()->after('id');
            }
            
            // Basic Information
            if (!Schema::hasColumn('debit_notes', 'debit_note_number')) {
                $table->string('debit_note_number')->unique()->after('formatted_id');
            }
            if (!Schema::hasColumn('debit_notes', 'status')) {
                $table->enum('status', ['draft', 'pending', 'issued', 'cancelled', 'expired'])->default('draft')->after('debit_note_number');
            }
            if (!Schema::hasColumn('debit_notes', 'issue_date')) {
                $table->timestamp('issue_date')->nullable()->after('status');
            }
            if (!Schema::hasColumn('debit_notes', 'expiry_date')) {
                $table->timestamp('expiry_date')->nullable()->after('issue_date');
            }
            
            // Relationships
            if (!Schema::hasColumn('debit_notes', 'vendor_id')) {
                $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete()->after('expiry_date');
            }
            if (!Schema::hasColumn('debit_notes', 'supplier_bill_id')) {
                $table->foreignId('supplier_bill_id')->nullable()->constrained('supplier_bills')->nullOnDelete()->after('vendor_id');
            }
            if (!Schema::hasColumn('debit_notes', 'return_transaction_id')) {
                $table->foreignId('return_transaction_id')->nullable()->constrained('stock_transactions')->nullOnDelete()->after('supplier_bill_id');
            }
            if (!Schema::hasColumn('debit_notes', 'journal_entry_id')) {
                $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete()->after('return_transaction_id');
            }
            
            // Financial Information
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
            if (!Schema::hasColumn('debit_notes', 'applied_amount')) {
                $table->decimal('applied_amount', 15, 2)->default(0)->after('remaining_amount');
            }
            
            // Currency and Exchange Rate
            if (!Schema::hasColumn('debit_notes', 'currency')) {
                $table->string('currency', 3)->default('USD')->after('applied_amount');
            }
            if (!Schema::hasColumn('debit_notes', 'exchange_rate')) {
                $table->decimal('exchange_rate', 10, 4)->default(1.0000)->after('currency');
            }
            if (!Schema::hasColumn('debit_notes', 'total_amount_base_currency')) {
                $table->decimal('total_amount_base_currency', 15, 2)->default(0)->after('exchange_rate');
            }
            
            // Additional Information
            if (!Schema::hasColumn('debit_notes', 'reference_number')) {
                $table->string('reference_number')->nullable()->after('total_amount_base_currency');
            }
            if (!Schema::hasColumn('debit_notes', 'reason')) {
                $table->string('reason')->nullable()->after('reference_number');
            }
            if (!Schema::hasColumn('debit_notes', 'notes')) {
                $table->text('notes')->nullable()->after('reason');
            }
            if (!Schema::hasColumn('debit_notes', 'terms_and_conditions')) {
                $table->text('terms_and_conditions')->nullable()->after('notes');
            }
            if (!Schema::hasColumn('debit_notes', 'is_partial')) {
                $table->boolean('is_partial')->default(false)->after('terms_and_conditions');
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
            
            // Metadata
            if (!Schema::hasColumn('debit_notes', 'metadata')) {
                $table->json('metadata')->nullable()->after('next_recurring_date');
            }
            if (!Schema::hasColumn('debit_notes', 'tax_breakdown')) {
                $table->json('tax_breakdown')->nullable()->after('metadata');
            }
            if (!Schema::hasColumn('debit_notes', 'discount_breakdown')) {
                $table->json('discount_breakdown')->nullable()->after('tax_breakdown');
            }
            
            // Audit Fields
            if (!Schema::hasColumn('debit_notes', 'created_by')) {
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete()->after('discount_breakdown');
            }
            if (!Schema::hasColumn('debit_notes', 'updated_by')) {
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete()->after('created_by');
            }
            if (!Schema::hasColumn('debit_notes', 'approved_by')) {
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete()->after('updated_by');
            }
            if (!Schema::hasColumn('debit_notes', 'cancelled_by')) {
                $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete()->after('approved_by');
            }
            if (!Schema::hasColumn('debit_notes', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('cancelled_by');
            }
            if (!Schema::hasColumn('debit_notes', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('approved_at');
            }
            if (!Schema::hasColumn('debit_notes', 'cancellation_reason')) {
                $table->text('cancellation_reason')->nullable()->after('cancelled_at');
            }
        });

        // Add indexes for performance
        Schema::table('credit_notes', function (Blueprint $table) {
            $table->index(['status', 'issue_date']);
            $table->index(['customer_id', 'status']);
            $table->index(['invoice_id']);
            $table->index(['return_transaction_id']);
            $table->index(['credit_note_number']);
            $table->index(['expiry_date']);
            $table->index(['created_at']);
        });

        Schema::table('debit_notes', function (Blueprint $table) {
            $table->index(['status', 'issue_date']);
            $table->index(['vendor_id', 'status']);
            $table->index(['supplier_bill_id']);
            $table->index(['return_transaction_id']);
            $table->index(['debit_note_number']);
            $table->index(['expiry_date']);
            $table->index(['created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove indexes
        Schema::table('credit_notes', function (Blueprint $table) {
            $table->dropIndex(['status', 'issue_date']);
            $table->dropIndex(['customer_id', 'status']);
            $table->dropIndex(['invoice_id']);
            $table->dropIndex(['return_transaction_id']);
            $table->dropIndex(['credit_note_number']);
            $table->dropIndex(['expiry_date']);
            $table->dropIndex(['created_at']);
        });

        Schema::table('debit_notes', function (Blueprint $table) {
            $table->dropIndex(['status', 'issue_date']);
            $table->dropIndex(['vendor_id', 'status']);
            $table->dropIndex(['supplier_bill_id']);
            $table->dropIndex(['return_transaction_id']);
            $table->dropIndex(['debit_note_number']);
            $table->dropIndex(['expiry_date']);
            $table->dropIndex(['created_at']);
        });

        // Drop columns from credit_notes
        Schema::table('credit_notes', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->dropForeign(['invoice_id']);
            $table->dropForeign(['return_transaction_id']);
            $table->dropForeign(['created_by']);
            $table->dropForeign(['updated_by']);
            $table->dropForeign(['approved_by']);
            $table->dropForeign(['cancelled_by']);
            
            $table->dropColumn([
                'formatted_id', 'credit_note_number', 'status', 'issue_date', 'expiry_date',
                'customer_id', 'invoice_id', 'return_transaction_id', 'subtotal', 'tax_amount',
                'discount_amount', 'total_amount', 'remaining_amount', 'applied_amount',
                'currency', 'exchange_rate', 'total_amount_base_currency', 'reference_number',
                'reason', 'notes', 'terms_and_conditions', 'is_partial', 'is_recurring',
                'recurring_frequency', 'next_recurring_date', 'metadata', 'tax_breakdown',
                'discount_breakdown', 'created_by', 'updated_by', 'approved_by', 'cancelled_by',
                'approved_at', 'cancelled_at', 'cancellation_reason'
            ]);
        });

        // Drop columns from debit_notes
        Schema::table('debit_notes', function (Blueprint $table) {
            $table->dropForeign(['vendor_id']);
            $table->dropForeign(['supplier_bill_id']);
            $table->dropForeign(['return_transaction_id']);
            $table->dropForeign(['journal_entry_id']);
            $table->dropForeign(['created_by']);
            $table->dropForeign(['updated_by']);
            $table->dropForeign(['approved_by']);
            $table->dropForeign(['cancelled_by']);
            
            $table->dropColumn([
                'formatted_id', 'debit_note_number', 'status', 'issue_date', 'expiry_date',
                'vendor_id', 'supplier_bill_id', 'return_transaction_id', 'journal_entry_id',
                'subtotal', 'tax_amount', 'discount_amount', 'total_amount', 'remaining_amount',
                'applied_amount', 'currency', 'exchange_rate', 'total_amount_base_currency',
                'reference_number', 'reason', 'notes', 'terms_and_conditions', 'is_partial',
                'is_recurring', 'recurring_frequency', 'next_recurring_date', 'metadata',
                'tax_breakdown', 'discount_breakdown', 'created_by', 'updated_by', 'approved_by',
                'cancelled_by', 'approved_at', 'cancelled_at', 'cancellation_reason'
            ]);
        });
    }
};
