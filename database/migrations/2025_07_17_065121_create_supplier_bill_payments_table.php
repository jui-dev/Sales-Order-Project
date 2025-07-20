<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('supplier_bill_payments', function (Blueprint $table) {
            $table->id();
            $table->string('formatted_id')->unique();
            $table->foreignId('supplier_bill_id')->constrained('supplier_bills')->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->decimal('payment_amount', 12, 2);
            $table->enum('payment_status', ['unpaid', 'paid'])->default('unpaid');
            $table->foreignId('payment_journal_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_bill_payments');
    }
};
