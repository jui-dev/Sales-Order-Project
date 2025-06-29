<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Ensure both 'confirmed' and the legacy 'completed' are in the list so no existing data breaks
        DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM('pending','processing','completed','confirmed','cancelled') DEFAULT 'pending'");
    }

    public function down(): void
    {
        // Revert to the previous definition without 'confirmed' (keeping 'completed')
        DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM('pending','processing','completed','cancelled') DEFAULT 'pending'");
    }
}; 