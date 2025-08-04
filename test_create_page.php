<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    echo "Testing create page rendering...\n";
    
    // Create the controller
    $controller = new \App\Http\Controllers\ProductController(new \App\Services\ProductService());
    
    // Call the create method
    $response = $controller->create();
    
    echo "Response type: " . get_class($response) . "\n";
    
    if ($response instanceof \Illuminate\View\View) {
        echo "View name: " . $response->getName() . "\n";
        echo "View data: " . json_encode($response->getData()) . "\n";
        
        // Try to render the view
        $content = $response->render();
        echo "View rendered successfully. Content length: " . strlen($content) . " characters\n";
        
        // Check if the form is present
        if (strpos($content, '<form') !== false) {
            echo "✓ Form element found\n";
        } else {
            echo "✗ Form element NOT found\n";
        }
        
        // Check if the name field is present
        if (strpos($content, 'name="name"') !== false) {
            echo "✓ Name field found\n";
        } else {
            echo "✗ Name field NOT found\n";
        }
        
        // Check if the SKU field is present
        if (strpos($content, 'name="sku"') !== false) {
            echo "✓ SKU field found\n";
        } else {
            echo "✗ SKU field NOT found\n";
        }
        
        // Check if the category field is present
        if (strpos($content, 'name="category_id"') !== false) {
            echo "✓ Category field found\n";
        } else {
            echo "✗ Category field NOT found\n";
        }
        
        // Show first 1000 characters
        echo "\nFirst 1000 characters:\n";
        echo substr($content, 0, 1000) . "\n";
    } else {
        echo "Unexpected response type\n";
    }
    
    echo "\nTest completed successfully!\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
} 