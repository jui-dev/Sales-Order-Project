<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 2025_07_25_120000_cleanup_database_structure dropped every column from
     * credit_notes and later migrations put most of them back - but not these
     * three. debit_notes kept its copies, and CreditNoteService::cancelCreditNote()
     * still writes all three, so cancelling a credit note fails on a missing
     * column. This restores parity with debit_notes.
     */
    public function up(): void
    {
        Schema::table('credit_notes', function (Blueprint $table) {
            if (! Schema::hasColumn('credit_notes', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable();
            }

            if (! Schema::hasColumn('credit_notes', 'cancelled_by')) {
                $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('credit_notes', 'cancellation_reason')) {
                $table->text('cancellation_reason')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('credit_notes', function (Blueprint $table) {
            if (Schema::hasColumn('credit_notes', 'cancelled_by')) {
                $table->dropConstrainedForeignId('cancelled_by');
            }

            $table->dropColumn(array_values(array_filter(
                ['cancelled_at', 'cancellation_reason'],
                fn ($column) => Schema::hasColumn('credit_notes', $column)
            )));
        });
    }
};
