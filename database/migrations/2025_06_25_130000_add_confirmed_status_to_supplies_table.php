<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Assuming MySQL, adjust the ENUM set to include 'confirmed'.
        DB::statement("ALTER TABLE supplies MODIFY COLUMN status ENUM('pending','confirmed','completed','cancelled') DEFAULT 'pending'");
    }

    public function down(): void
    {
        // Revert to original enum values.
        DB::statement("ALTER TABLE supplies MODIFY COLUMN status ENUM('pending','completed','cancelled') DEFAULT 'pending'");
    }
}; 