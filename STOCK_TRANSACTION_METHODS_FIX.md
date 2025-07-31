# Stock Transaction Methods Fix

## Issue
The stock management page was throwing a 500 error with the message:
```
Call to undefined method App\Models\StockTransaction::getReferenceDisplay()
```

## Root Cause
The view `resources/views/stock-management/index.blade.php` was calling several methods that didn't exist in the `StockTransaction` model:

1. `getReferenceDisplay()` - Returns reference type label for UI
2. `getFormattedQuantity()` - Formats quantity with proper sign (+/-)
3. `getEffectDescription()` - Describes the effect of the transaction
4. `reference_number` - Accessor for reference number display
5. `effect` - Accessor for effect value (positive for in, negative for out)

Additionally, there was an inconsistency in the `StockManagementService` where it was using `'inbound'`/`'outbound'` instead of `'in'`/`'out'` for direction values.

## Fixes Applied

### 1. Added Missing Methods to StockTransaction Model

**File:** `app/Models/StockTransaction.php`

Added the following methods:

```php
/**
 * Get reference display information for UI
 */
public function getReferenceDisplay(): array
{
    if (!$this->reference_type) {
        return ['label' => 'No Reference'];
    }

    return match($this->reference_type) {
        'App\Models\Order' => ['label' => 'Order'],
        'App\Models\Supply' => ['label' => 'Supply'],
        'App\Models\StockTransfer' => ['label' => 'Transfer'],
        'App\Models\Invoice' => ['label' => 'Invoice'],
        'App\Models\SupplierBill' => ['label' => 'Supplier Bill'],
        'App\Models\CreditNote' => ['label' => 'Credit Note'],
        'App\Models\DebitNote' => ['label' => 'Debit Note'],
        default => ['label' => class_basename($this->reference_type)],
    };
}

/**
 * Get formatted quantity with proper sign
 */
public function getFormattedQuantity(): string
{
    $quantity = abs($this->quantity);
    $sign = $this->direction === 'in' ? '+' : '-';
    return $sign . $quantity;
}

/**
 * Get effect description for UI
 */
public function getEffectDescription(): string
{
    return match($this->direction) {
        'in' => 'Stock In',
        'out' => 'Stock Out',
        default => 'Unknown',
    };
}

/**
 * Get reference number for display
 */
public function getReferenceNumberAttribute(): string
{
    if (!$this->reference_type || !$this->reference_id) {
        return 'N/A';
    }

    // Try to get the reference model and its formatted ID
    try {
        $reference = $this->reference;
        if ($reference && method_exists($reference, 'getFormattedIdAttribute')) {
            return $reference->formatted_id;
        }
        return '#' . $this->reference_id;
    } catch (\Exception $e) {
        return '#' . $this->reference_id;
    }
}

/**
 * Get effect value for UI (positive for in, negative for out)
 */
public function getEffectAttribute(): int
{
    return $this->direction === 'in' ? $this->quantity : -$this->quantity;
}
```

### 2. Fixed Direction Value Inconsistency in StockManagementService

**File:** `app/Services/StockManagementService.php`

Changed direction values from `'inbound'`/`'outbound'` to `'in'`/`'out'` to match the StockTransaction model:

```php
// In enrichTransaction method
if ($txn->direction === 'out') { // was 'outbound'

// In calculateProductSummary method
$totalInbound = $transactions->where('direction', 'in')->sum('quantity'); // was 'inbound'
$totalOutbound = $transactions->where('direction', 'out')->sum('quantity'); // was 'outbound'
```

### 3. Fixed View Attribute Access

**File:** `resources/views/stock-management/index.blade.php`

Changed `location_type` to `type` to match the StockLocation model:

```php
<small class="text-muted">{{ ucfirst($transaction->stockLocation->type ?? '') }}</small>
```

## Testing

Created and ran a test script to verify all methods work correctly:

- ✅ `getReferenceDisplay()` - Returns proper reference labels
- ✅ `getFormattedQuantity()` - Formats quantity with +/- signs
- ✅ `getEffectDescription()` - Returns proper effect descriptions
- ✅ `reference_number` - Returns formatted reference numbers
- ✅ `effect` - Returns proper effect values

## Result

The stock management page should now load without errors. All the missing methods have been implemented and the direction value inconsistencies have been resolved.

## Files Modified

1. `app/Models/StockTransaction.php` - Added missing methods
2. `app/Services/StockManagementService.php` - Fixed direction values
3. `resources/views/stock-management/index.blade.php` - Fixed attribute access

## Status

✅ **FIXED** - Stock management page should now load successfully without 500 errors. 