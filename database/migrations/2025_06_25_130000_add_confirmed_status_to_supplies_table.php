<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return; // Skip for SQLite or other drivers unsupported by ALTER ENUM
        }
        DB::statement("ALTER TABLE supplies MODIFY COLUMN status ENUM('pending','confirmed','completed','cancelled') DEFAULT 'pending'");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }
        DB::statement("ALTER TABLE supplies MODIFY COLUMN status ENUM('pending','completed','cancelled') DEFAULT 'pending'");
    }
}; 