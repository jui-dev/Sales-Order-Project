<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Refuses outside APP_ENV=local, and asks before touching anything. See guard.php.
require_once __DIR__ . '/guard.php';

guard_destructive_script(
    'clean_database.php',
    'Empties the transactional tables and resets their auto-increment counters.',
);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "Database Cleanup Script\n";
echo "======================\n\n";

// Tables to preserve completely (keep all data)
$preserveTables = [
    'accounts',
    'account_types', 
    'customers',
    'retailers',
    'vendors',
    'warehouses'
];

// Tables to clear completely (remove all data) - Order matters for foreign key constraints
$clearTables = [
    'audit_logs',
    'credit_note_applications',
    'credit_note_items',
    'credit_notes',
    'grns',
    'invoice_items',
    'invoices',
    'journal_entry_lines',
    'journal_entries',
    'order_items',
    'orders',
    'payments',
    'picking_list_items',
    'picking_lists',
    'product_stocks',
    'sessions',
    'stock_transactions',
    'stock_transfer_items',
    'stock_transfers',
    'supplier_bill_items',
    'supplier_bill_payments',
    'supplier_bills',
    'supply_items',
    'supplies'
];

echo "Starting database cleanup...\n\n";

// Step 1: Clear tables that should be completely empty
echo "Step 1: Clearing tables completely\n";
echo "==================================\n";

foreach ($clearTables as $table) {
    if (Schema::hasTable($table)) {
        try {
            $count = DB::table($table)->count();
            
            // Try TRUNCATE first, if it fails due to foreign keys, use DELETE
            try {
                DB::table($table)->truncate();
                echo "✅ Cleared table '{$table}' ({$count} records removed) - TRUNCATE\n";
            } catch (Exception $e) {
                // If TRUNCATE fails due to foreign key constraints, use DELETE
                if (strpos($e->getMessage(), 'foreign key constraint') !== false) {
                    DB::table($table)->delete();
                    echo "✅ Cleared table '{$table}' ({$count} records removed) - DELETE\n";
                } else {
                    throw $e; // Re-throw if it's not a foreign key issue
                }
            }
        } catch (Exception $e) {
            echo "❌ Error clearing table '{$table}': " . $e->getMessage() . "\n";
        }
    } else {
        echo "⚠️  Table '{$table}' does not exist, skipping\n";
    }
}

echo "\n";

// Step 2: Reset auto-increment counters for cleared tables
echo "Step 2: Resetting auto-increment counters\n";
echo "=========================================\n";

foreach ($clearTables as $table) {
    if (Schema::hasTable($table)) {
        try {
            // Reset auto-increment counter to 1
            DB::statement("ALTER TABLE `{$table}` AUTO_INCREMENT = 1");
            echo "✅ Reset auto-increment for table '{$table}' to start from 1\n";
        } catch (Exception $e) {
            echo "❌ Error resetting auto-increment for table '{$table}': " . $e->getMessage() . "\n";
        }
    } else {
        echo "⚠️  Table '{$table}' does not exist, skipping auto-increment reset\n";
    }
}

echo "\n";

// Step 3: Clear specific columns from products table
echo "Step 3: Clearing specific columns from products table\n";
echo "=====================================================\n";

if (Schema::hasTable('products')) {
    try {
        $productCount = DB::table('products')->count();
        
        // Clear specific columns while preserving table structure
        // Use 0 instead of null for numeric fields to avoid constraint violations
        DB::table('products')->update([
            'selling_price' => 0,
            'purchase_price' => 0,
            'gross_profit' => 0,
            'available_stocks' => 0,
            'markup' => 25,
            'auto_pricing_enabled' => true,
            'last_price_update' => null
        ]);
        
        echo "✅ Cleared pricing columns from 'products' table ({$productCount} products updated)\n";
    } catch (Exception $e) {
        echo "❌ Error clearing products table columns: " . $e->getMessage() . "\n";
    }
} else {
    echo "⚠️  Table 'products' does not exist\n";
}

echo "\n";

// Step 4: Verify preserved tables
echo "Step 4: Verifying preserved tables\n";
echo "==================================\n";

foreach ($preserveTables as $table) {
    if (Schema::hasTable($table)) {
        $count = DB::table($table)->count();
        echo "✅ Preserved table '{$table}' ({$count} records)\n";
    } else {
        echo "⚠️  Table '{$table}' does not exist\n";
    }
}

echo "\n";

// Step 5: Show final status with auto-increment information
echo "Step 5: Final database status with auto-increment info\n";
echo "=====================================================\n";

// Get all tables using raw SQL instead of Schema::getAllTables()
$tables = DB::select("SHOW TABLES");
$tableNames = [];
foreach ($tables as $table) {
    $tableNames[] = array_values((array)$table)[0];
}

foreach ($tableNames as $tableName) {
    if (in_array($tableName, $preserveTables)) {
        $count = DB::table($tableName)->count();
        echo "📊 {$tableName}: {$count} records (preserved)\n";
    } elseif (in_array($tableName, $clearTables)) {
        $count = DB::table($tableName)->count();
        // Get auto-increment value
        try {
            $autoIncrement = DB::select("SHOW TABLE STATUS LIKE '{$tableName}'")[0]->Auto_increment ?? 'N/A';
            echo "📊 {$tableName}: {$count} records (cleared) - Next ID: {$autoIncrement}\n";
        } catch (Exception $e) {
            echo "📊 {$tableName}: {$count} records (cleared) - Next ID: Error getting info\n";
        }
    } elseif ($tableName === 'products') {
        $count = DB::table($tableName)->count();
        echo "📊 {$tableName}: {$count} records (pricing columns cleared)\n";
    } else {
        $count = DB::table($tableName)->count();
        echo "📊 {$tableName}: {$count} records (preserved - not in clear list)\n";
    }
}

echo "\nDatabase cleanup completed successfully!\n";
echo "========================================\n";
echo "✅ All specified tables have been cleared\n";
echo "✅ Auto-increment counters reset to start from 1\n";
echo "✅ Products table pricing columns have been cleared\n";
echo "✅ Preserved tables remain intact\n";
echo "✅ No table structures or columns were affected\n";
echo "\n💡 Next time you insert data into cleared tables, IDs will start from 1!\n"; 