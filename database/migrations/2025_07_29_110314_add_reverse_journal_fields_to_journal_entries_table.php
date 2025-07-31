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
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->boolean('is_reverse')->default(false)->after('status');
            $table->foreignId('reverses_journal_id')->nullable()->constrained('journal_entries')->after('is_reverse');
            $table->foreignId('linked_debit_note_id')->nullable()->constrained('debit_notes')->after('reverses_journal_id');
            $table->foreignId('linked_credit_note_id')->nullable()->constrained('credit_notes')->after('linked_debit_note_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropForeign(['reverses_journal_id']);
            $table->dropForeign(['linked_debit_note_id']);
            $table->dropForeign(['linked_credit_note_id']);
            $table->dropColumn(['is_reverse', 'reverses_journal_id', 'linked_debit_note_id', 'linked_credit_note_id']);
        });
    }
};
