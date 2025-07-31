# Stock Transaction Quantity Display Fix

## Issue
In the stock transaction records page, vendor to warehouse transactions were displaying:
- **Negative quantities** (e.g., `-100` instead of `+100`)
- **"Unknown"** under the quantity instead of proper descriptions

## Root Cause Analysis

### 1. Direction Value Mismatch
The database was using `'inbound'` and `'outbound'` for direction values, but the code was checking for `'in'` and `'out'`.

**Database Values:**
- `direction = 'inbound'` (for stock coming in)
- `direction = 'outbound'` (for stock going out)

**Code Expectations:**
- `direction = 'in'` (for stock coming in)
- `direction = 'out'` (for stock going out)

### 2. Effect Description Logic
The `getEffectDescription()` method only recognized `'in'` and `'out'`, causing it to return "Unknown" for `'inbound'` transactions.

### 3. Quantity Formatting Logic
The `getFormattedQuantity()` method was incorrectly applying negative signs to inbound transactions because it didn't recognize `'inbound'` as a valid inbound direction.

## Fixes Applied

### 1. Enhanced Direction Handling

**File:** `app/Models/StockTransaction.php`

Added helper methods to handle both old and new direction formats:

```php
/**
 * Check if this is an inbound direction (stock coming in)
 */
private function isInboundDirection(): bool
{
    return in_array($this->direction, ['in', 'inbound']);
}

/**
 * Check if this is an outbound direction (stock going out)
 */
private function isOutboundDirection(): bool
{
    return in_array($this->direction, ['out', 'outbound']);
}
```

### 2. Fixed Quantity Formatting

**Updated `getFormattedQuantity()` method:**

```php
public function getFormattedQuantity(): string
{
    $quantity = abs($this->quantity);
    $sign = $this->isInboundDirection() ? '+' : '-';
    return $sign . $quantity;
}
```

**Before:** `-100` (incorrect negative for vendor to warehouse)
**After:** `+100` (correct positive for vendor to warehouse)

### 3. Fixed Effect Description

**Updated `getEffectDescription()` method:**

```php
public function getEffectDescription(): string
{
    return match($this->direction) {
        'in', 'inbound' => 'Stock In',
        'out', 'outbound' => 'Stock Out',
        default => 'Unknown',
    };
}
```

**Before:** "Unknown"
**After:** "Stock In"

### 4. Fixed Effect Value Calculation

**Updated `getEffectAttribute()` method:**

```php
public function getEffectAttribute(): int
{
    return $this->isInboundDirection() ? $this->quantity : -$this->quantity;
}
```

**Before:** `-100` (negative effect)
**After:** `100` (positive effect)

### 5. Enhanced Display Information

**Added `getDetailedEffectDescription()` method:**

```php
public function getDetailedEffectDescription(): string
{
    return match($this->transaction_type) {
        self::TYPE_STOCK_IN => 'Vendor → Warehouse',
        self::TYPE_STOCK_TRANSFER => 'Internal Transfer',
        self::TYPE_ORDER_FULFILLMENT => 'Customer Order',
        self::TYPE_CUSTOMER_RETURN => 'Customer Return',
        self::TYPE_VENDOR_RETURN => 'Vendor Return',
        self::TYPE_RETAILER_RETURN => 'Retailer Return',
        self::TYPE_ADJUSTMENT => 'Manual Adjustment',
        default => $this->getEffectDescription(),
    };
}
```

### 6. Enhanced View Display

**File:** `resources/views/stock-management/index.blade.php`

Updated the quantity column to show more detailed information:

```php
<td class="text-center">
    <span class="badge bg-{{ $transaction->effect > 0 ? 'success' : 'danger' }} fs-6">
        {{ $transaction->getFormattedQuantity() }}
    </span>
    <div class="small text-muted mt-1">{{ $transaction->getDetailedEffectDescription() }}</div>
    <div class="small text-muted">{{ $transaction->getEffectDescription() }}</div>
</td>
```

## Results

### Before Fix:
- **Quantity:** `-100` (red badge)
- **Description:** "Unknown"
- **Effect:** Negative value

### After Fix:
- **Quantity:** `+100` (green badge)
- **Description:** "Vendor → Warehouse"
- **Sub-description:** "Stock In"
- **Effect:** Positive value

## Transaction Type Mapping

| Transaction Type | Direction | Display | Description |
|------------------|-----------|---------|-------------|
| `stock_in` | `inbound` | `+100` | Vendor → Warehouse |
| `stock_transfer` | `inbound` | `+50` | Internal Transfer |
| `order_fulfillment` | `outbound` | `-25` | Customer Order |
| `customer_return` | `inbound` | `+10` | Customer Return |
| `vendor_return` | `outbound` | `-5` | Vendor Return |
| `retailer_return` | `inbound` | `+15` | Retailer Return |
| `adjustment` | `inbound` | `+20` | Manual Adjustment |

## Files Modified

1. **`app/Models/StockTransaction.php`**
   - Added `isInboundDirection()` and `isOutboundDirection()` helper methods
   - Updated `getFormattedQuantity()` to use helper methods
   - Updated `getEffectDescription()` to handle both direction formats
   - Updated `getEffectAttribute()` to use helper methods
   - Added `getDetailedEffectDescription()` method

2. **`resources/views/stock-management/index.blade.php`**
   - Enhanced quantity column display with detailed descriptions

## Testing

Created and ran comprehensive tests to verify:
- ✅ Direction value handling for both `'in'`/`'out'` and `'inbound'`/`'outbound'`
- ✅ Correct quantity formatting with proper signs
- ✅ Proper effect descriptions for all transaction types
- ✅ Enhanced display with detailed transaction information

## Status

✅ **FIXED** - Stock transaction quantity display now shows:
- Correct positive/negative signs
- Proper effect descriptions
- Detailed transaction type information
- Enhanced visual presentation

The vendor to warehouse transactions now display correctly with positive quantities and proper descriptions. 