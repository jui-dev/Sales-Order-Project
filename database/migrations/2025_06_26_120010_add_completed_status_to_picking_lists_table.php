<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement("ALTER TABLE picking_lists MODIFY COLUMN status ENUM('open','picked','verified','closed','completed') DEFAULT 'open'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE picking_lists MODIFY COLUMN status ENUM('open','picked','verified','closed') DEFAULT 'open'");
    }
}; 