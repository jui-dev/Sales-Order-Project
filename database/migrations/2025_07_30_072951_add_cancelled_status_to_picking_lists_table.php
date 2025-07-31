<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') { return; }
        // Add 'cancelled' to the status enum
        DB::statement("ALTER TABLE picking_lists MODIFY COLUMN status ENUM('pending','open','picked','verified','closed','completed','cancelled') DEFAULT 'pending'");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') { return; }
        // Remove 'cancelled' from the enum
        DB::statement("ALTER TABLE picking_lists MODIFY COLUMN status ENUM('pending','open','picked','verified','closed','completed') DEFAULT 'pending'");
    }
};
