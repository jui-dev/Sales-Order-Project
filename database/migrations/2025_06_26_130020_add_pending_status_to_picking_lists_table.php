<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Add 'pending' to the status enum if it's not already present
        DB::statement("ALTER TABLE picking_lists MODIFY COLUMN status ENUM('pending','open','picked','verified','closed','completed') DEFAULT 'pending'");
    }

    public function down(): void
    {
        // Remove 'pending' from the enum list – fall back to 'open' default
        DB::statement("ALTER TABLE picking_lists MODIFY COLUMN status ENUM('open','picked','verified','closed','completed') DEFAULT 'open'");
    }
}; 