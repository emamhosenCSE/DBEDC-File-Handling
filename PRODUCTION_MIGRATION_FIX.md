# Production Database Migration Fix

## Problem
The migration script contains `DELIMITER` statements that are not compatible with programmatic execution via PHP (PDO/mysqli). This causes syntax errors when running the migration on production servers.

## Solution
Use one of the provided migration methods that properly handle DELIMITER statements.

## Method 1: Web-based Migration Tool (Easiest)

1. Upload `migrate_web.php` to your production server
2. Access it via browser: `https://yourdomain.com/migrate_web.php`
3. Enter your database credentials
4. Click "Run Migration"
5. **Important:** Delete the file after successful migration for security

## Method 2: Command Line with run_migration_production.php

```bash
# Upload the script to your production server
php run_migration_production.php <host> <database> <username> <password>

# Example:
php run_migration_production.php localhost file_tracker root mypassword
```

## Method 3: Direct MySQL Client (if available)

```bash
# If you have MySQL/MariaDB client access:
mysql -u username -p database_name < sql/migration_v2.sql
```

## Method 4: Manual PHP Execution

If none of the above work, you can manually execute the SQL statements by:

1. Open `sql/migration_v2.sql` in a text editor
2. Remove all `DELIMITER` lines
3. Replace `END //` with `END;`
4. Execute the resulting SQL via phpMyAdmin or MySQL client

## Troubleshooting

### Error: "PROCEDURE already exists"
This is normal if running the migration multiple times. The scripts will skip these errors.

### Error: "Table already exists"
The scripts handle duplicate table creation by continuing execution.

### Error: "Access denied"
Ensure the database user has CREATE, ALTER, DROP, and INSERT privileges.

### Error: "Unknown database"
Create the database first:
```sql
CREATE DATABASE file_tracker CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

## Verification

After successful migration, verify with:
```bash
mysql -u username -p database_name -e "SHOW TABLES;"
mysql -u username -p database_name -e "SELECT * FROM settings WHERE setting_key = 'system_installed';"
```

## Files to Upload

Upload these files to your production server:
- `migrate_web.php` (for web-based migration)
- `run_migration_production.php` (for command-line migration)
- `sql/migration_v2.sql` (the migration file)

## Security Note

After successful migration:
- Delete `migrate_web.php` from your server
- Ensure database credentials are not exposed in logs
- Set proper file permissions on production files