<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Real columns for the return audit trail. The model already cast and
     * exposed approved_at / completed_at / return_data, but no such columns
     * existed, so every value read back as null.
     */
    public function up(): void
    {
        Schema::table('stock_transactions', function (Blueprint $table) {
            $table->foreignId('approved_by')->nullable()->after('stock_posted_at')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');

            $table->foreignId('rejected_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable()->after('rejected_by');

            $table->foreignId('completed_by')->nullable()->after('rejected_at')->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable()->after('completed_by');

            $table->foreignId('cancelled_by')->nullable()->after('completed_at')->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable()->after('cancelled_by');

            // Free-form return metadata (rejection reason, cancellation notes,
            // return amount). Previously written into `notes`, which destroyed
            // the return reason stored there.
            $table->json('return_data')->nullable()->after('cancelled_at');
        });
    }

    public function down(): void
    {
        Schema::table('stock_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropConstrainedForeignId('rejected_by');
            $table->dropConstrainedForeignId('completed_by');
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropColumn([
                'approved_at',
                'rejected_at',
                'completed_at',
                'cancelled_at',
                'return_data',
            ]);
        });
    }
};
