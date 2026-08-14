<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') { return; }
        // Cancelling a transfer marks its picking items cancelled, which the
        // original enum had no value for.
        DB::statement("ALTER TABLE picking_list_items MODIFY COLUMN status ENUM('pending','picked','short','cancelled') NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') { return; }
        DB::statement("UPDATE picking_list_items SET status = 'pending' WHERE status = 'cancelled'");
        DB::statement("ALTER TABLE picking_list_items MODIFY COLUMN status ENUM('pending','picked','short') NOT NULL DEFAULT 'pending'");
    }
};
