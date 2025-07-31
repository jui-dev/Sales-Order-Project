<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Order;

class TestOrdersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:orders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test orders functionality';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Testing orders functionality...');
        
        try {
            // Test 1: Basic count
            $count = Order::count();
            $this->info("Order count: {$count}");
            
            // Test 2: Simple query
            $orders = Order::latest()->limit(5)->get();
            $this->info("Simple query successful. Count: {$orders->count()}");
            
            // Test 3: Query with customer
            $ordersWithCustomer = Order::with('customer')->latest()->limit(5)->get();
            $this->info("Query with customer successful. Count: {$ordersWithCustomer->count()}");
            
            // Test 4: Query with all relationships
            $ordersWithAll = Order::with(['customer', 'items', 'invoice'])->latest()->limit(5)->get();
            $this->info("Query with all relationships successful. Count: {$ordersWithAll->count()}");
            
            // Test 5: Pagination
            $paginatedOrders = Order::with(['customer', 'items', 'invoice'])->latest()->paginate(25);
            $this->info("Pagination test successful. Count: {$paginatedOrders->count()}, Total: {$paginatedOrders->total()}, Current Page: {$paginatedOrders->currentPage()}");
            
            $this->info('All tests passed successfully!');
            return 0;
            
        } catch (\Exception $e) {
            $this->error("Error occurred: " . $e->getMessage());
            $this->error("Stack trace: " . $e->getTraceAsString());
            return 1;
        }
    }
}
