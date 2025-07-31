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
        Schema::table('payments', function (Blueprint $table) {
            // Rename paid_at to payment_date if it exists
            if (Schema::hasColumn('payments', 'paid_at')) {
                $table->renameColumn('paid_at', 'payment_date');
            }
            
            // Add missing columns if they don't exist
            if (!Schema::hasColumn('payments', 'payment_date')) {
                $table->timestamp('payment_date')->nullable();
            }
            
            if (!Schema::hasColumn('payments', 'reference_number')) {
                $table->string('reference_number', 100)->nullable();
            }
            
            if (!Schema::hasColumn('payments', 'notes')) {
                $table->text('notes')->nullable();
            }
            
            if (!Schema::hasColumn('payments', 'status')) {
                $table->string('status', 20)->default('completed');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // Reverse the changes
            if (Schema::hasColumn('payments', 'payment_date')) {
                $table->renameColumn('payment_date', 'paid_at');
            }
            
            $table->dropColumn(['reference_number', 'notes', 'status']);
        });
    }
};
