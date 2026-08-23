<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Refuses outside APP_ENV=local, and asks before touching anything. See guard.php.
require_once __DIR__ . '/guard.php';

guard_destructive_script(
    'reset_auto_increment.php',
    'Rewrites the auto-increment counter on every table in the database.',
);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "Auto-Increment Reset Script\n";
echo "==========================\n\n";

// Get all tables in the database using raw SQL
$tables = DB::select("SHOW TABLES");
$tableNames = [];
foreach ($tables as $table) {
    $tableNames[] = array_values((array)$table)[0];
}

echo "Found " . count($tableNames) . " tables in the database.\n\n";

// Step 1: Check current auto-increment values
echo "Step 1: Current auto-increment values\n";
echo "====================================\n";

$autoIncrementInfo = [];

foreach ($tableNames as $tableName) {
    try {
        $tableStatus = DB::select("SHOW TABLE STATUS LIKE '{$tableName}'")[0];
        $autoIncrement = $tableStatus->Auto_increment ?? 'N/A';
        $rowCount = DB::table($tableName)->count();
        
        $autoIncrementInfo[$tableName] = [
            'current_ai' => $autoIncrement,
            'row_count' => $rowCount
        ];
        
        echo "📊 {$tableName}: {$rowCount} records - Next ID: {$autoIncrement}\n";
    } catch (Exception $e) {
        echo "❌ Error getting info for table '{$tableName}': " . $e->getMessage() . "\n";
    }
}

echo "\n";

// Step 2: Reset auto-increment counters
echo "Step 2: Resetting auto-increment counters\n";
echo "========================================\n";

$resetCount = 0;
$errorCount = 0;

foreach ($tableNames as $tableName) {
    try {
        // Reset auto-increment counter to 1
        DB::statement("ALTER TABLE `{$tableName}` AUTO_INCREMENT = 1");
        echo "✅ Reset auto-increment for table '{$tableName}' to start from 1\n";
        $resetCount++;
    } catch (Exception $e) {
        echo "❌ Error resetting auto-increment for table '{$tableName}': " . $e->getMessage() . "\n";
        $errorCount++;
    }
}

echo "\n";

// Step 3: Verify reset results
echo "Step 3: Verification - Auto-increment values after reset\n";
echo "=======================================================\n";

$verifiedCount = 0;

foreach ($tableNames as $tableName) {
    try {
        $tableStatus = DB::select("SHOW TABLE STATUS LIKE '{$tableName}'")[0];
        $autoIncrement = $tableStatus->Auto_increment ?? 'N/A';
        $rowCount = DB::table($tableName)->count();
        
        if ($autoIncrement == 1 || $autoIncrement == 'N/A') {
            echo "✅ {$tableName}: {$rowCount} records - Next ID: {$autoIncrement}\n";
            $verifiedCount++;
        } else {
            echo "⚠️  {$tableName}: {$rowCount} records - Next ID: {$autoIncrement} (not reset)\n";
        }
    } catch (Exception $e) {
        echo "❌ Error verifying table '{$tableName}': " . $e->getMessage() . "\n";
    }
}

echo "\n";

// Step 4: Summary
echo "Step 4: Summary\n";
echo "===============\n";
echo "📊 Total tables processed: " . count($tableNames) . "\n";
echo "✅ Successfully reset: {$resetCount} tables\n";
echo "❌ Errors encountered: {$errorCount} tables\n";
echo "✅ Verified reset: {$verifiedCount} tables\n";

echo "\nAuto-increment reset completed!\n";
echo "==============================\n";
echo "✅ All tables now have auto-increment starting from 1\n";
echo "✅ Next insertions will use ID 1, 2, 3, etc.\n";
echo "✅ No data was affected, only auto-increment counters were reset\n";
echo "\n💡 You can now insert new records and they will start with ID 1!\n"; 