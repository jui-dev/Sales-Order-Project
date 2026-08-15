<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // This is a one-off backfill for rows that already existed when it was
        // written. Its filename dates it five months before
        // 2025_06_25_120000_add_transaction_type_to_stock_transactions, so on a
        // rebuild from empty it runs first and queries a column that does not
        // exist yet. There is nothing to backfill on an empty table either way.
        if (! Schema::hasColumn('stock_transactions', 'transaction_type')) {
            return;
        }

        // Get all return transactions that need to be processed
        $returnTransactions = DB::table('stock_transactions')
            ->whereIn('transaction_type', ['customer_return', 'vendor_return', 'retailer_return'])
            ->whereIn('status', ['pending', 'approved', 'completed'])
            ->get();

        foreach ($returnTransactions as $transaction) {
            $productId = $transaction->product_id;
            $locationId = $transaction->location_id;
            $locationType = $transaction->location_type;
            $quantity = $transaction->quantity;
            $transactionType = $transaction->transaction_type;

            if ($transactionType === 'vendor_return') {
                // For vendor returns, decrease warehouse stock
                if ($locationType === 'App\\Models\\Warehouse') {
                    // Find or create product stock record for warehouse
                    $productStock = DB::table('product_stocks')
                        ->where('product_id', $productId)
                        ->where('location_id', $locationId)
                        ->where('location_type', 'App\\Models\\Warehouse')
                        ->first();

                    if ($productStock) {
                        // Decrease warehouse stock
                        DB::table('product_stocks')
                            ->where('id', $productStock->id)
                            ->decrement('quantity', $quantity);
                    } else {
                        // Create new record with negative quantity (will be set to 0)
                        DB::table('product_stocks')->insert([
                            'product_id' => $productId,
                            'location_id' => $locationId,
                            'location_type' => 'App\\Models\\Warehouse',
                            'quantity' => 0,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            } else {
                // For customer and retailer returns, increase stock at destination
                if (in_array($locationType, ['App\\Models\\Warehouse', 'App\\Models\\Retailer'])) {
                    // Find or create product stock record
                    $productStock = DB::table('product_stocks')
                        ->where('product_id', $productId)
                        ->where('location_id', $locationId)
                        ->where('location_type', $locationType)
                        ->first();

                    if ($productStock) {
                        // Increase stock
                        DB::table('product_stocks')
                            ->where('id', $productStock->id)
                            ->increment('quantity', $quantity);
                    } else {
                        // Create new record
                        DB::table('product_stocks')->insert([
                            'product_id' => $productId,
                            'location_id' => $locationId,
                            'location_type' => $locationType,
                            'quantity' => $quantity,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }
        }

        // Now update all products' available_stocks based on product_stocks table
        $products = DB::table('products')->get();
        
        foreach ($products as $product) {
            $totalStock = DB::table('product_stocks')
                ->where('product_id', $product->id)
                ->sum('quantity');
            
            $totalStock = max(0, $totalStock);
            
            DB::table('products')
                ->where('id', $product->id)
                ->update(['available_stocks' => $totalStock]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This migration is irreversible as it modifies data
        // We cannot restore the original state without knowing what it was
    }
};