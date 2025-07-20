<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, update any existing 'paid' status records to 'posted'
        DB::table('supplier_bills')
            ->where('status', 'paid')
            ->update(['status' => 'posted']);

        // Then modify the enum to only allow 'draft' and 'posted'
        Schema::table('supplier_bills', function (Blueprint $table) {
            $table->enum('status', ['draft', 'posted'])->default('draft')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Restore the original enum with 'paid' option
        Schema::table('supplier_bills', function (Blueprint $table) {
            $table->enum('status', ['draft', 'posted', 'paid'])->default('draft')->change();
        });
    }
};
