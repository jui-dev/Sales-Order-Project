<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "Warehouses Table Structure:\n";
echo "==========================\n";

$columns = DB::select('DESCRIBE warehouses');
foreach ($columns as $col) {
    echo "- {$col->Field} ({$col->Type})";
    if ($col->Null === 'YES') {
        echo " [NULLABLE]";
    }
    if ($col->Key === 'PRI') {
        echo " [PRIMARY KEY]";
    }
    echo "\n";
}

echo "\nWarehouses Data:\n";
echo "================\n";

$warehouses = DB::table('warehouses')->get();
foreach ($warehouses as $warehouse) {
    echo "ID: {$warehouse->id}, Name: {$warehouse->name}\n";
    echo "  - Address: " . ($warehouse->address ?? 'NULL') . "\n";
    
    // Check for contact-related columns
    $contactColumns = ['contact_person', 'contact_number', 'phone', 'email', 'manager', 'manager_phone', 'manager_email'];
    foreach ($contactColumns as $col) {
        if (isset($warehouse->$col)) {
            echo "  - {$col}: " . ($warehouse->$col ?? 'NULL') . "\n";
        }
    }
    echo "\n";
} 