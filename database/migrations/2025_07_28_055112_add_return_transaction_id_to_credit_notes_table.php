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
            if (!Schema::hasColumn('credit_notes', 'return_transaction_id')) {
                $table->foreignId('return_transaction_id')->nullable()->constrained('stock_transactions')->nullOnDelete()->after('invoice_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('credit_notes', function (Blueprint $table) {
            if (Schema::hasColumn('credit_notes', 'return_transaction_id')) {
                $table->dropForeign(['return_transaction_id']);
                $table->dropColumn('return_transaction_id');
            }
        });
    }
};
