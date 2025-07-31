# Stock Calculation Fix - Available Stocks Issue Resolution

## Problem Description

The `available_stocks` column in the `products` table was showing incorrect values due to double-counting. When a supplier supplied a product with a specific quantity (e.g., 300 units), the available stocks would show 600 instead of 300.

## Root Cause Analysis

The issue was caused by the stock calculation logic considering **both** the `product_stocks` table and the `stock_transactions` table:

1. **Product_stocks table**: Contains the actual stock quantities at each location
2. **Stock_transactions table**: Contains transaction records for audit purposes

The calculation was doing: `available_stocks = product_stocks_sum + stock_transactions_sum`

This caused double-counting because:
- When a supply is posted via GRN, it adds stock to the `product_stocks` table
- It also creates a `stock_transaction` record
- The calculation was adding both values together

## Solution Implemented

### 1. Updated Product Model (`app/Models/Product.php`)

**Before:**
```php
public function getAvailableStocksAttribute($value): int
{
    if ($value !== null) {
        return (int) $value;
    }

    // Calculate available stock purely from stock transactions
    $stockTransactions = \App\Models\StockTransaction::where('product_id', $this->id)
        ->whereIn('status', ['pending', 'approved', 'completed'])
        ->get();

    $availableStock = 0;
    foreach ($stockTransactions as $transaction) {
        // Complex logic to calculate from transactions
    }

    return max(0, $availableStock);
}
```

**After:**
```php
public function getAvailableStocksAttribute($value): int
{
    if ($value !== null) {
        return (int) $value;
    }

    // Calculate available stock purely from product_stocks table only
    // This avoids double-counting issues with stock_transactions
    $availableStock = (int) $this->stockBalances()->sum('quantity');
    
    // Ensure stock doesn't go negative
    return max(0, $availableStock);
}
```

### 2. Updated StockTransactionObserver (`app/Observers/StockTransactionObserver.php`)

**Before:**
```php
private function updateProductStock(StockTransaction $transaction): void
{
    // Get base stock from product_stocks table
    $baseStock = (int) $product->stockBalances()->sum('quantity');
    
    // Calculate adjustments from stock transactions
    $stockTransactions = StockTransaction::where('product_id', $product->id)
        ->whereIn('status', ['pending', 'approved', 'completed'])
        ->get();

    $adjustments = 0;
    foreach ($stockTransactions as $txn) {
        // Complex logic to calculate adjustments
    }

    // Calculate final available stock
    $totalStock = $baseStock + $adjustments;
    $availableStock = max(0, $totalStock);
}
```

**After:**
```php
private function updateProductStock(StockTransaction $transaction): void
{
    // Calculate available stock purely from product_stocks table only
    // This avoids double-counting issues with stock_transactions
    $availableStock = (int) $product->stockBalances()->sum('quantity');
    $availableStock = max(0, $availableStock);
}
```

### 3. Updated ProductStockObserver (`app/Observers/ProductStockObserver.php`)

Similar changes were made to use only the `product_stocks` table for calculations.

### 4. Updated ProductService (`app/Services/ProductService.php`)

The `recalculateProductStock` method was updated to use the same simplified logic.

## Key Changes Summary

1. **Simplified Calculation**: `available_stocks` is now calculated only from the `product_stocks` table
2. **Removed Double-Counting**: No longer adding both `product_stocks` and `stock_transactions` values
3. **Maintained Audit Trail**: `stock_transactions` table is still used for audit purposes, but not for stock calculations
4. **Updated Tests**: All related tests were updated to reflect the new logic

## Verification

### Test Case
Created a test that simulates the exact scenario mentioned by the user:

```php
/** @test */
public function it_calculates_supply_stock_correctly_without_double_counting()
{
    // Simulate a supply of 300 units
    ProductStock::create([
        'product_id' => $product->id,
        'location_id' => $warehouse->id,
        'location_type' => Warehouse::class,
        'quantity' => 300,
    ]);

    // Create a stock transaction for the supply (should not affect available_stocks)
    StockTransaction::create([
        'product_id' => $product->id,
        'quantity' => 300,
        'direction' => 'inbound',
        'transaction_type' => StockTransaction::TYPE_STOCK_IN,
        'status' => 'completed',
    ]);

    // Available stock should be: 300 (from product_stocks only)
    // NOT 600 (300 from product_stocks + 300 from stock_transactions)
    $this->assertEquals(300, $product->available_stocks);
}
```

**Result**: ✅ Test passes, confirming the fix works correctly.

## Data Cleanup

The existing data was corrected using the recalculation command:

```bash
php artisan products:recalculate-stocks
```

This command recalculated all product stocks using the new logic and updated the `available_stocks` column accordingly.

## Impact

- ✅ **Fixed**: Available stocks now show correct values (300 instead of 600)
- ✅ **Maintained**: Audit trail through `stock_transactions` table is preserved
- ✅ **Simplified**: Stock calculation logic is now more straightforward and reliable
- ✅ **Tested**: All related functionality is covered by updated tests

## Files Modified

1. `app/Models/Product.php` - Updated `getAvailableStocksAttribute` method
2. `app/Observers/StockTransactionObserver.php` - Updated `updateProductStock` method
3. `app/Observers/ProductStockObserver.php` - Updated `syncProductStock` method
4. `app/Services/ProductService.php` - Updated `recalculateProductStock` method
5. `tests/Feature/StockCalculationTest.php` - Updated all tests to reflect new logic

The fix ensures that when a supplier supplies a product with 300 units, the `available_stocks` column will correctly show 300, not 600. 