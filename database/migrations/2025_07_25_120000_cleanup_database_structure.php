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
        // Drop internal return note related tables
        Schema::dropIfExists('internal_return_note_items');
        Schema::dropIfExists('internal_return_notes');

        // Drop returns and return_items tables
        Schema::dropIfExists('return_items');
        Schema::dropIfExists('returns');

        // Drop credit note related tables (keep only credit_notes table)
        Schema::dropIfExists('credit_note_applications');
        Schema::dropIfExists('credit_note_items');

        // Drop debit note related tables (keep only debit_notes table)
        Schema::dropIfExists('debit_note_applications');
        Schema::dropIfExists('debit_note_items');

        // Simplify credit_notes table structure
        $this->dropColumnsSafely('credit_notes', [
            'invoice_id',
            'customer_id',
            'return_transaction_id',
            'credit_note_number',
            'total_amount',
            'status',
            'issue_date',
            'notes',
            'reason',
            'tax_amount',
            'discount_amount',
            'subtotal',
            'currency',
            'exchange_rate',
            'reference_number',
            'expiry_date',
            'is_partial',
            'remaining_amount',
            'metadata',
            'created_by',
            'updated_by',
            'cancelled_at',
            'cancelled_by',
            'cancellation_reason',
        ], [
            'invoice_id',
            'customer_id',
            'return_transaction_id',
            'created_by',
            'updated_by',
            'cancelled_by',
        ]);

        // Simplify debit_notes table structure
        $this->dropColumnsSafely('debit_notes', [
            'supplier_bill_id',
            'vendor_id',
            'return_transaction_id',
            'debit_note_number',
            'total_amount',
            'status',
            'issue_date',
            'notes',
            'reason',
            'tax_amount',
            'discount_amount',
            'subtotal',
            'currency',
            'exchange_rate',
            'reference_number',
            'expiry_date',
            'is_partial',
            'remaining_amount',
            'metadata',
            'created_by',
            'updated_by',
            'cancelled_at',
            'cancelled_by',
            'cancellation_reason',
            'journal_entry_id',
        ], [
            'supplier_bill_id',
            'vendor_id',
            'return_transaction_id',
            'created_by',
            'updated_by',
            'cancelled_by',
            'journal_entry_id',
        ]);
    }

    /**
     * Drop columns from a table, first clearing anything that depends on them.
     *
     * SQLite refuses to drop a column while an index still references it, so the
     * foreign keys and indexes covering those columns are removed first. Every
     * step is guarded because this migration also runs on databases where an
     * earlier migration already removed some of these columns.
     *
     * @param  list<string>  $columns  Columns to drop.
     * @param  list<string>  $foreignKeys  Subset of $columns that carry a foreign key.
     */
    private function dropColumnsSafely(string $table, array $columns, array $foreignKeys = []): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        // Only touch columns that are actually present.
        $columns = array_values(array_filter(
            $columns,
            fn (string $column) => Schema::hasColumn($table, $column)
        ));

        if ($columns === []) {
            return;
        }

        foreach (array_intersect($foreignKeys, $columns) as $column) {
            try {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropForeign([$column]));
            } catch (\Throwable) {
                // No foreign key on this column (or the driver has already discarded it).
            }
        }

        // Drop every non-primary index that covers one of the doomed columns.
        foreach (Schema::getIndexes($table) as $index) {
            if (($index['primary'] ?? false) || array_intersect($index['columns'] ?? [], $columns) === []) {
                continue;
            }

            try {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropIndex($index['name']));
            } catch (\Throwable) {
                // Index already gone, or backs a constraint the driver removes itself.
            }
        }

        Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropColumn($columns));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Recreate internal return note tables
        Schema::create('internal_return_notes', function (Blueprint $table) {
            $table->id();
            $table->string('formatted_id')->unique();
            $table->foreignId('return_transaction_id')->constrained('stock_transactions')->onDelete('cascade');
            $table->foreignId('retailer_id')->constrained('retailers');
            $table->foreignId('warehouse_id')->constrained('warehouses');
            $table->foreignId('stock_transfer_id')->constrained('stock_transfers');
            $table->string('status')->default('issued');
            $table->date('issue_date');
            $table->text('notes')->nullable();
            $table->text('reason')->nullable();
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users');
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();

            $table->index(['status', 'issue_date']);
            $table->index(['retailer_id', 'status']);
            $table->index(['warehouse_id', 'status']);
        });

        Schema::create('internal_return_note_items', function (Blueprint $table) {
            $table->id();
            $table->string('formatted_id')->unique();
            $table->foreignId('internal_return_note_id')->constrained('internal_return_notes')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('products');
            $table->foreignId('stock_transfer_item_id')->constrained('stock_transfer_items');
            $table->integer('quantity_returned');
            $table->integer('quantity_originally_transferred');
            $table->decimal('unit_cost', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->text('return_reason')->nullable();
            $table->text('item_notes')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'internal_return_note_id'], 'irn_items_product_note_idx');
        });

        // Recreate returns and return_items tables
        Schema::create('returns', function (Blueprint $table) {
            $table->id();
            $table->string('formatted_id')->unique()->nullable();
            $table->enum('return_type', ['customer_to_warehouse', 'customer_to_retailer', 'vendor_return', 'retailer_return'])->default('customer_to_warehouse');
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->morphs('return_destination');
            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])->default('pending');
            $table->date('return_date');
            $table->text('return_reason')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->decimal('total_refund_amount', 12, 2)->default(0);
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('rejected_by')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->timestamps();

            $table->index(['return_type', 'status']);
            $table->index(['customer_id', 'return_date']);
            $table->index(['invoice_id']);
            $table->index(['order_id']);
        });

        Schema::create('return_items', function (Blueprint $table) {
            $table->id();
            $table->string('formatted_id')->unique()->nullable();
            $table->foreignId('return_id')->constrained('returns')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_item_id')->nullable()->constrained('invoice_items')->nullOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained('order_items')->nullOnDelete();
            $table->integer('quantity_returned');
            $table->integer('quantity_originally_sold');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('total_refund_amount', 12, 2);
            $table->text('return_reason')->nullable();
            $table->text('item_notes')->nullable();
            $table->boolean('stock_adjusted')->default(false);
            $table->timestamp('stock_adjusted_at')->nullable();
            $table->timestamps();

            $table->index(['return_id', 'product_id']);
            $table->index(['invoice_item_id']);
            $table->index(['order_item_id']);
        });

        // Recreate credit note related tables
        Schema::create('credit_note_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credit_note_id')->constrained('credit_notes')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('invoice_item_id')->nullable()->constrained('invoice_items')->nullOnDelete();
            $table->integer('quantity');
            $table->decimal('unit_price', 15, 2);
            $table->decimal('total_amount', 15, 2);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('discount_rate', 5, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('subtotal', 15, 2);
            $table->string('description')->nullable();
            $table->text('notes')->nullable();
            $table->string('sku')->nullable();
            $table->string('product_name')->nullable();
            $table->json('product_metadata')->nullable();
            $table->timestamps();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            
            $table->index(['credit_note_id', 'product_id']);
            $table->index(['invoice_item_id']);
        });

        Schema::create('credit_note_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credit_note_id')->constrained('credit_notes')->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->decimal('amount_applied', 15, 2);
            $table->timestamp('applied_at');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            
            $table->index(['credit_note_id', 'invoice_id']);
            $table->index(['applied_at']);
        });

        // Recreate debit note related tables
        Schema::create('debit_note_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('debit_note_id')->constrained('debit_notes')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('supplier_bill_item_id')->nullable()->constrained('supplier_bill_items')->nullOnDelete();
            $table->integer('quantity');
            $table->decimal('unit_price', 15, 2);
            $table->decimal('total_amount', 15, 2);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('discount_rate', 5, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('subtotal', 15, 2);
            $table->string('description')->nullable();
            $table->text('notes')->nullable();
            $table->string('sku')->nullable();
            $table->string('product_name')->nullable();
            $table->json('product_metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('debit_note_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('debit_note_id')->constrained('debit_notes')->cascadeOnDelete();
            $table->foreignId('supplier_bill_id')->constrained('supplier_bills')->cascadeOnDelete();
            $table->decimal('amount_applied', 15, 2);
            $table->timestamp('applied_at');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Restore credit_notes table structure
        Schema::table('credit_notes', function (Blueprint $table) {
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('return_transaction_id')->nullable()->constrained('stock_transactions')->nullOnDelete();
            $table->string('credit_note_number')->unique();
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->enum('status', ['pending', 'issued', 'cancelled'])->default('pending');
            $table->timestamp('issue_date')->nullable();
            $table->text('notes')->nullable();
            $table->string('reason')->nullable();
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->decimal('exchange_rate', 10, 4)->default(1.0000);
            $table->string('reference_number')->nullable();
            $table->timestamp('expiry_date')->nullable();
            $table->boolean('is_partial')->default(false);
            $table->decimal('remaining_amount', 15, 2)->default(0);
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancellation_reason')->nullable();
        });

        // Restore debit_notes table structure
        Schema::table('debit_notes', function (Blueprint $table) {
            $table->foreignId('supplier_bill_id')->nullable()->constrained('supplier_bills')->nullOnDelete();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->foreignId('return_transaction_id')->nullable()->constrained('stock_transactions')->nullOnDelete();
            $table->string('debit_note_number')->unique();
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->enum('status', ['pending', 'issued', 'cancelled'])->default('pending');
            $table->timestamp('issue_date')->nullable();
            $table->text('notes')->nullable();
            $table->string('reason')->nullable();
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->decimal('exchange_rate', 10, 4)->default(1.0000);
            $table->string('reference_number')->nullable();
            $table->timestamp('expiry_date')->nullable();
            $table->boolean('is_partial')->default(false);
            $table->decimal('remaining_amount', 15, 2)->default(0);
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancellation_reason')->nullable();
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
        });
    }
}; 