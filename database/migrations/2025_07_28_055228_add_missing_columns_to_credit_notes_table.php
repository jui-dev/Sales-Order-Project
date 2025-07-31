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
        Schema::table('credit_notes', function (Blueprint $table) {
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
            
            // Relationships
            if (!Schema::hasColumn('credit_notes', 'customer_id')) {
                $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete()->after('issue_date');
            }
            if (!Schema::hasColumn('credit_notes', 'invoice_id')) {
                $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete()->after('customer_id');
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
            
            // Currency and Exchange Rate
            if (!Schema::hasColumn('credit_notes', 'currency')) {
                $table->string('currency', 3)->default('USD')->after('remaining_amount');
            }
            if (!Schema::hasColumn('credit_notes', 'exchange_rate')) {
                $table->decimal('exchange_rate', 10, 4)->default(1.0000)->after('currency');
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
            
            // Metadata
            if (!Schema::hasColumn('credit_notes', 'metadata')) {
                $table->json('metadata')->nullable()->after('next_recurring_date');
            }
            
            // Audit Fields
            if (!Schema::hasColumn('credit_notes', 'created_by')) {
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete()->after('discount_breakdown');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('credit_notes', function (Blueprint $table) {
            // Drop foreign keys
            if (Schema::hasColumn('credit_notes', 'customer_id')) {
                $table->dropForeign(['customer_id']);
            }
            if (Schema::hasColumn('credit_notes', 'invoice_id')) {
                $table->dropForeign(['invoice_id']);
            }
            if (Schema::hasColumn('credit_notes', 'created_by')) {
                $table->dropForeign(['created_by']);
            }
            
            // Drop columns
            $columnsToDrop = [
                'credit_note_number', 'status', 'issue_date', 'customer_id', 'invoice_id',
                'subtotal', 'tax_amount', 'discount_amount', 'total_amount', 'remaining_amount',
                'currency', 'exchange_rate', 'reference_number', 'reason', 'notes',
                'metadata', 'created_by'
            ];
            
            foreach ($columnsToDrop as $column) {
                if (Schema::hasColumn('credit_notes', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
