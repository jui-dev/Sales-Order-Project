<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('supplier_bills', function (Blueprint $table) {
            $table->id();
            $table->string('formatted_id')->unique();
            $table->foreignId('grn_id')->constrained('grns')->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->date('bill_date');
            $table->text('description')->nullable();
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->enum('status', ['draft', 'posted', 'paid'])->default('draft');
            $table->foreignId('purchase_journal_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('payment_journal_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('supplier_bill_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_bill_id')->constrained('supplier_bills')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->integer('quantity');
            $table->decimal('unit_cost', 12, 2);
            $table->decimal('subtotal', 12, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_bill_items');
        Schema::dropIfExists('supplier_bills');
    }
}; 