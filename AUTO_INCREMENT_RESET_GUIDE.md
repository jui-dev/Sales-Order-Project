# Auto-Increment Reset Guide

This guide explains how to reset auto-increment counters in your Laravel Sales Order Return Management System database so that new records start with ID 1.

## Problem

When you delete all data from a table, the auto-increment counter doesn't automatically reset. This means the next record inserted will have an ID that continues from where the previous sequence left off, rather than starting from 1.

**Example:**
- Table has records with IDs: 1, 2, 3, 4, 5
- You delete all records
- Next insert creates record with ID: 6 (not 1)

## Solution

We've created three different tools to reset auto-increment counters:

### 1. Enhanced Database Cleanup Script (`clean_database.php`)

The existing cleanup script has been enhanced to automatically reset auto-increment counters for all cleared tables.

**Usage:**
```bash
php clean_database.php
```

**What it does:**
- Clears all specified tables (orders, invoices, etc.)
- Resets auto-increment counters to start from 1
- Preserves master data (customers, vendors, etc.)
- Shows verification of reset results

### 2. Dedicated Auto-Increment Reset Script (`reset_auto_increment.php`)

A standalone script specifically for resetting auto-increment counters without clearing data.

**Usage:**
```bash
php reset_auto_increment.php
```

**What it does:**
- Resets auto-increment for ALL tables in the database
- Shows before/after auto-increment values
- Provides detailed verification
- No data is affected, only counters are reset

### 3. Laravel Artisan Command (`db:reset-auto-increment`)

A Laravel-native command with flexible options.

**Usage Examples:**

```bash
# Reset auto-increment for all tables
php artisan db:reset-auto-increment --all

# Reset auto-increment for a specific table
php artisan db:reset-auto-increment --table=orders

# Dry run - see what would be done without making changes
php artisan db:reset-auto-increment --all --dry-run

# Reset specific table with dry run
php artisan db:reset-auto-increment --table=invoices --dry-run
```

**Command Options:**
- `--table=TABLE_NAME`: Reset auto-increment for a specific table
- `--all`: Reset auto-increment for all tables
- `--dry-run`: Show what would be done without actually doing it

## When to Use Each Tool

### Use `clean_database.php` when:
- You want to start fresh with a clean database
- You want to clear all transaction data but keep master data
- You want a complete reset of the system

### Use `reset_auto_increment.php` when:
- You only want to reset auto-increment counters
- You don't want to clear any data
- You want to reset ALL tables (including master data tables)

### Use `db:reset-auto-increment` when:
- You want Laravel-native command line interface
- You want to reset specific tables only
- You want to preview changes with dry-run mode
- You're working within the Laravel ecosystem

## Example Scenarios

### Scenario 1: Complete System Reset
```bash
# Clear all transaction data and reset counters
php clean_database.php
```

### Scenario 2: Reset Only Orders Table
```bash
# Reset auto-increment for orders table only
php artisan db:reset-auto-increment --table=orders
```

### Scenario 3: Preview Auto-Increment Reset
```bash
# See what would happen without making changes
php artisan db:reset-auto-increment --all --dry-run
```

### Scenario 4: Reset After Manual Data Deletion
```bash
# If you manually deleted data and want to reset counters
php reset_auto_increment.php
```

## Verification

All tools provide verification to confirm that auto-increment counters have been reset:

**Expected Output:**
```
📊 orders: 0 records - Next ID: 1
📊 invoices: 0 records - Next ID: 1
📊 customers: 5 records - Next ID: 1
```

## Important Notes

1. **Data Safety**: These tools only reset auto-increment counters, they don't affect existing data unless you use the cleanup script.

2. **Foreign Key Constraints**: Auto-increment reset works even with foreign key constraints, as it only affects the counter, not the data.

3. **Empty Tables**: Auto-increment can be reset for empty tables without issues.

4. **Non-Empty Tables**: Auto-increment can be reset for tables with data, but the next insert will use the next available ID (which might not be 1 if there are existing records).

5. **Backup**: Always backup your database before running any maintenance scripts.

## Troubleshooting

### Error: "Table doesn't exist"
- Ensure the table name is correct
- Check if the table exists in your database

### Error: "Access denied"
- Ensure your database user has ALTER privileges
- Check your database connection settings

### Auto-increment not resetting
- Some tables might not have auto-increment columns
- Check if the table has an `id` column with auto-increment

### Foreign Key Issues
- Auto-increment reset doesn't affect foreign key relationships
- If you have foreign key issues, they're likely related to data, not the counter

## Best Practices

1. **Always backup** your database before running maintenance scripts
2. **Use dry-run mode** first to preview changes
3. **Test in development** before running in production
4. **Document your changes** for team reference
5. **Use specific table targeting** when possible instead of resetting all tables

## Integration with Development Workflow

### For Development/Testing:
```bash
# Reset everything for fresh testing
php clean_database.php

# Or just reset counters
php artisan db:reset-auto-increment --all
```

### For Production Maintenance:
```bash
# Preview changes first
php artisan db:reset-auto-increment --all --dry-run

# Then apply changes
php artisan db:reset-auto-increment --all
```

This ensures your database auto-increment counters are properly managed and new records start with clean, sequential IDs. 