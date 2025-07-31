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
        // Add 'posted' to the status enum
        DB::statement("ALTER TABLE debit_notes MODIFY COLUMN status ENUM('draft', 'pending', 'issued', 'posted', 'cancelled', 'expired') DEFAULT 'draft'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove 'posted' from the status enum
        DB::statement("ALTER TABLE debit_notes MODIFY COLUMN status ENUM('draft', 'pending', 'issued', 'cancelled', 'expired') DEFAULT 'draft'");
    }
};
