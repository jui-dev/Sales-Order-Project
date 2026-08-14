<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Warehouse -> Retailer transfers move through confirmed -> completed. The
 * previous first state was "pending", which read as "nothing has happened yet"
 * even though confirming already reserves stock at the source warehouse.
 *
 * 'pending' is added to, not replaced: order picking lists and the retailer
 * return console commands still create pending rows.
 */
return new class extends Migration {
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') { return; }

        DB::statement("ALTER TABLE stock_transfers MODIFY COLUMN status ENUM('pending','confirmed','completed','cancelled') NOT NULL DEFAULT 'pending'");
        DB::statement("ALTER TABLE picking_lists MODIFY COLUMN status ENUM('pending','confirmed','open','picked','verified','closed','completed','cancelled') DEFAULT 'pending'");
        DB::statement("ALTER TABLE picking_list_items MODIFY COLUMN status ENUM('pending','confirmed','picked','short','cancelled') NOT NULL DEFAULT 'pending'");

        // Carry existing warehouse -> retailer transfers over to the new wording.
        DB::statement("UPDATE stock_transfers SET status = 'confirmed' WHERE status = 'pending'");

        DB::statement("
            UPDATE picking_list_items pli
            JOIN picking_lists pl ON pl.id = pli.picking_list_id
            SET pli.status = 'confirmed'
            WHERE pli.status = 'pending' AND pl.reference_type = 'App\\\\Models\\\\StockTransfer'
        ");

        // Picking lists last: the join above needs the pending ones still marked.
        DB::statement("
            UPDATE picking_lists
            SET status = 'confirmed'
            WHERE status = 'pending' AND reference_type = 'App\\\\Models\\\\StockTransfer'
        ");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') { return; }

        // Put the data back before narrowing the columns, or the rows truncate.
        DB::statement("UPDATE stock_transfers SET status = 'pending' WHERE status = 'confirmed'");
        DB::statement("UPDATE picking_lists SET status = 'pending' WHERE status = 'confirmed'");
        DB::statement("UPDATE picking_list_items SET status = 'pending' WHERE status = 'confirmed'");

        DB::statement("ALTER TABLE stock_transfers MODIFY COLUMN status ENUM('pending','completed','cancelled') NOT NULL DEFAULT 'pending'");
        DB::statement("ALTER TABLE picking_lists MODIFY COLUMN status ENUM('pending','open','picked','verified','closed','completed','cancelled') DEFAULT 'pending'");
        DB::statement("ALTER TABLE picking_list_items MODIFY COLUMN status ENUM('pending','picked','short','cancelled') NOT NULL DEFAULT 'pending'");
    }
};
