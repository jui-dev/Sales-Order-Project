<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') { return; }
        // Expand enum to include the new 'confirmed' value **without** dropping 'completed'
        // to avoid data-truncation errors while legacy rows still exist.
        DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM('pending','processing','completed','confirmed','cancelled') DEFAULT 'pending'");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') { return; }
        // Revert back to previous ENUM (drop the newly added 'confirmed')
        DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM('pending','processing','completed','cancelled') DEFAULT 'pending'");
    }
}; 