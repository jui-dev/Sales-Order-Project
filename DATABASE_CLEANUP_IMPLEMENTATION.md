# Database Cleanup Implementation

## Overview

This implementation provides a comprehensive solution for cleaning all data from specified tables while preserving the database architecture and columns. The system includes backup, cleanup, and restore functionality to ensure data safety.

## Commands Created

### 1. Database Backup Command
**Command:** `php artisan database:backup-data`

**Purpose:** Creates a backup of all specified tables before cleanup operations.

**Features:**
- ✅ Safely backs up all specified tables
- ✅ Shows current data counts before backup
- ✅ Creates timestamped backup files
- ✅ Prevents execution in production environment
- ✅ Handles missing tables gracefully
- ✅ Provides detailed progress feedback

**Usage:**
```bash
# Backup all specified tables
php artisan database:backup-data

# Backup specific tables only
php artisan database:backup-data --tables=products,orders,invoices
```

### 2. Database Cleanup Command
**Command:** `php artisan database:cleanup-data`

**Purpose:** Safely removes all data from specified tables while preserving database structure.

**Features:**
- ✅ Cleans tables in proper order (child tables first)
- ✅ Preserves all table structures and columns
- ✅ Maintains foreign key relationships
- ✅ Prevents execution in production environment
- ✅ Provides detailed progress and summary
- ✅ Handles missing tables gracefully
- ✅ Double confirmation for safety

**Usage:**
```bash
# Clean all data with confirmation prompts
php artisan database:cleanup-data

# Clean all data without confirmation prompts
php artisan database:cleanup-data --confirm
```

### 3. Database Restore Command
**Command:** `php artisan database:restore-data {backup-file}`

**Purpose:** Restores data from a backup file if needed.

**Features:**
- ✅ Restores data from backup files
- ✅ Validates backup file format
- ✅ Shows backup contents before restore
- ✅ Prevents execution in production environment
- ✅ Provides detailed progress and summary
- ✅ Double confirmation for safety

**Usage:**
```bash
# Restore from backup file with confirmation prompts
php artisan database:restore-data storage/app/backups/database_backup_2024-01-15_10-30-45.json

# Restore without confirmation prompts
php artisan database:restore-data storage/app/backups/database_backup_2024-01-15_10-30-45.json --confirm
```

## Tables Included in Cleanup

The cleanup process targets the following tables in the correct order to respect foreign key constraints:

### Child Tables (Cleaned First)
1. `audit_logs`
2. `credit_note_items`
3. `debit_note_items`
4. `invoice_items`
5. `order_items`
6. `payment_items`
7. `picking_list_items`
8. `stock_transfer_items`
9. `supplier_bill_items`
10. `supply_items`
11. `journal_entry_lines`
12. `stock_transactions`
13. `product_stocks`

### Parent Tables (Cleaned After Child Tables)
14. `credit_notes`
15. `debit_notes`
16. `invoices`
17. `orders`
18. `payments`
19. `picking_lists`
20. `stock_transfers`
21. `supplier_bills`
22. `supplier_bill_payments`
23. `supplies`
24. `grns`
25. `journal_entries`
26. `products`

## Safety Features

### Production Environment Protection
- All commands are blocked from running in production environment
- Prevents accidental data loss in live systems

### Foreign Key Constraint Handling
- Temporarily disables foreign key checks during operations
- Re-enables foreign key checks after completion
- Handles constraint violations gracefully

### Confirmation Prompts
- Double confirmation for destructive operations
- Clear warnings about data loss
- Option to skip confirmations with `--confirm` flag

### Error Handling
- Comprehensive error catching and reporting
- Graceful handling of missing tables
- Detailed error messages for troubleshooting

## Database Architecture Preservation

### What is Preserved
- ✅ All table structures
- ✅ All columns and data types
- ✅ All indexes and constraints
- ✅ All foreign key relationships
- ✅ All default values
- ✅ All auto-increment sequences

### What is Removed
- ❌ All data records from specified tables
- ❌ No table structures are modified
- ❌ No columns are removed
- ❌ No relationships are broken

## Recommended Workflow

### 1. Pre-Cleanup Backup
```bash
# Always create a backup before cleanup
php artisan database:backup-data
```

### 2. Verify Backup
```bash
# Check that backup was created successfully
ls -la storage/app/backups/
```

### 3. Perform Cleanup
```bash
# Clean all data from specified tables
php artisan database:cleanup-data
```

### 4. Verify Cleanup
```bash
# Check that tables are empty
php artisan tinker
>>> DB::table('products')->count()
>>> DB::table('orders')->count()
```

### 5. Restore if Needed (Optional)
```bash
# If you need to restore the data
php artisan database:restore-data storage/app/backups/database_backup_YYYY-MM-DD_HH-MM-SS.json
```

## File Locations

### Commands
- `app/Console/Commands/BackupDatabaseData.php`
- `app/Console/Commands/CleanupDatabaseData.php`
- `app/Console/Commands/RestoreDatabaseData.php`

### Backup Files
- Location: `storage/app/backups/`
- Format: `database_backup_YYYY-MM-DD_HH-MM-SS.json`
- Size: Varies based on data volume

## Technical Implementation Details

### Foreign Key Handling
```php
// Temporarily disable foreign key checks
DB::statement('SET FOREIGN_KEY_CHECKS = 0');

// Perform operations...

// Re-enable foreign key checks
DB::statement('SET FOREIGN_KEY_CHECKS = 1');
```

### Table Ordering
Tables are processed in dependency order to avoid foreign key constraint violations:
1. Child tables with foreign keys are cleaned first
2. Parent tables are cleaned after their children
3. This ensures no constraint violations occur

### Error Recovery
If any operation fails:
- Foreign key checks are re-enabled
- Detailed error messages are provided
- System remains in a consistent state

## Monitoring and Logging

### Progress Tracking
- Real-time progress updates during operations
- Detailed counts before and after operations
- Success/error summaries

### Backup Validation
- JSON format validation
- File size reporting
- Record count verification

## Best Practices

### Before Cleanup
1. Always create a backup first
2. Verify you're not in production environment
3. Ensure you have sufficient disk space for backup
4. Test the process on a development environment first

### During Cleanup
1. Monitor the progress output
2. Note any warnings or errors
3. Keep the terminal session active

### After Cleanup
1. Verify tables are empty
2. Test application functionality
3. Keep backup files for potential restore

## Troubleshooting

### Common Issues

**Issue:** "Table does not exist" warnings
**Solution:** This is normal for tables that haven't been created yet. The system handles this gracefully.

**Issue:** Foreign key constraint errors
**Solution:** The commands handle this automatically by temporarily disabling foreign key checks.

**Issue:** Backup file too large
**Solution:** Consider backing up specific tables only using the `--tables` option.

**Issue:** Permission errors
**Solution:** Ensure the application has write permissions to the `storage/app/backups/` directory.

## Security Considerations

### Environment Protection
- Commands are blocked in production environment
- Prevents accidental data loss in live systems

### Data Privacy
- Backup files contain all data in JSON format
- Store backup files securely
- Consider encryption for sensitive data

### Access Control
- Commands require appropriate permissions
- Backup files should be protected from unauthorized access

## Performance Considerations

### Large Datasets
- For very large datasets, consider backing up specific tables
- Monitor memory usage during backup operations
- Consider chunked processing for very large tables

### Database Performance
- Cleanup operations may temporarily impact database performance
- Consider running during low-traffic periods
- Monitor database performance during operations

## Conclusion

This implementation provides a safe, comprehensive solution for cleaning database data while preserving the architecture. The three-command system (backup, cleanup, restore) ensures data safety and provides flexibility for different scenarios.

The system is designed to be:
- **Safe:** Multiple confirmation prompts and production environment protection
- **Comprehensive:** Handles all specified tables with proper ordering
- **Reliable:** Robust error handling and recovery mechanisms
- **User-friendly:** Clear progress indicators and detailed feedback
- **Flexible:** Supports selective operations and backup/restore functionality

This implementation maintains the integrity of your Sales Order Management System while providing the data cleanup functionality you requested. 