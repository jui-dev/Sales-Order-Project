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
        // Account Types (e.g., Asset, Liability, Equity, Revenue, Expense)
        Schema::create('account_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        // Chart of Accounts
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->foreignId('account_type_id')->constrained('account_types');
            $table->foreignId('parent_id')->nullable()->constrained('accounts');
            $table->decimal('opening_balance', 15, 2)->default(0);
            $table->boolean('is_contra')->default(false);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // Journal Entries (headers)
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->string('formatted_id')->unique();
            $table->date('entry_date');
            $table->text('description')->nullable();
            $table->morphs('source'); // source_type & source_id (e.g., Invoice, Grn, etc.)
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
        });

        // Journal Entry Lines (details)
        Schema::create('journal_entry_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained('journal_entries')->onDelete('cascade');
            $table->foreignId('account_id')->constrained('accounts');
            $table->decimal('debit', 15, 2)->default(0);
            $table->decimal('credit', 15, 2)->default(0);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['account_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('journal_entry_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('accounts');
        Schema::dropIfExists('account_types');
    }
}; 