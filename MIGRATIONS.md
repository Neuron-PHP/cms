# Database Migrations Guide

This document provides comprehensive guidance for working with database migrations in Neuron CMS.

## Table of Contents

1. [Core Principles](#core-principles)
2. [Migration Workflow](#migration-workflow)
3. [Common Scenarios](#common-scenarios)
4. [Upgrade Path Considerations](#upgrade-path-considerations)
5. [Best Practices](#best-practices)
6. [Troubleshooting](#troubleshooting)

## Core Principles

### Never Modify Existing Migrations

**CRITICAL RULE: Once a migration has been committed to the repository, NEVER modify it.**

**Why?**
- Phinx tracks which migrations have been executed using a `phinxlog` table
- Existing installations have already run the original migration
- Modifying an existing migration will NOT update those installations
- This creates schema drift between installations

**Example of What NOT to Do:**

```php
// ❌ WRONG: Editing cms/resources/database/migrate/20250111000000_create_users_table.php
// to add a new column after it's already been committed
public function change()
{
    $table = $this->table( 'users' );
    $table->addColumn( 'username', 'string' )
        ->addColumn( 'email', 'string' )
        ->addColumn( 'new_column', 'string' )  // DON'T ADD THIS HERE!
        ->create();
}
```

### Always Create New Migrations for Schema Changes

**Correct Approach:** Create a new migration file with a new timestamp.

```php
// ✅ CORRECT: Create cms/resources/database/migrate/20251205000000_add_new_column_to_users.php
use Phinx\Migration\AbstractMigration;

class AddNewColumnToUsers extends AbstractMigration
{
    public function change()
    {
        $table = $this->table( 'users' );
        $table->addColumn( 'new_column', 'string', [ 'null' => true ] )
            ->update();
    }
}
```

## Migration Workflow

### Creating a New Migration

1. **Generate migration file with timestamp:**
   ```bash
   # Format: YYYYMMDDHHMMSS_description_of_change.php
   # Example: 20251205143000_add_timezone_to_users.php
   ```

2. **Use descriptive names:**
   - `add_[column]_to_[table].php` - Adding columns
   - `remove_[column]_from_[table].php` - Removing columns
   - `create_[table]_table.php` - Creating new tables
   - `rename_[old]_to_[new]_in_[table].php` - Renaming columns

3. **Place migrations in the correct location:**
   - CMS component: `cms/resources/database/migrate/`
   - Test installations: `testing/*/db/migrate/`

### Implementing the Migration

```php
<?php

use Phinx\Migration\AbstractMigration;

/**
 * Brief description of what this migration does
 */
class AddTimezoneToUsers extends AbstractMigration
{
    /**
     * Change Method.
     *
     * Write your reversible migrations using this method.
     *
     * For more information see documentation:
     * https://book.cakephp.org/phinx/0/en/migrations.html
     */
    public function change()
    {
        $table = $this->table( 'users' );

        $table->addColumn( 'timezone', 'string', [
                'limit' => 50,
                'default' => 'UTC',
                'null' => false,
                'after' => 'last_login_at'  // Optional: specify column position
            ])
            ->update();
    }
}
```

### Testing the Migration

1. **Test in development environment:**
   ```bash
   php neuron db:migrate
   ```

2. **Test rollback (if applicable):**
   ```bash
   php neuron db:rollback
   ```

3. **Verify schema changes:**
   ```bash
   # SQLite
   sqlite3 storage/database.sqlite3 "PRAGMA table_info(users);"

   # MySQL
   mysql -u user -p -e "DESCRIBE users;" database_name
   ```

## Common Scenarios

### Adding a Column to an Existing Table

```php
class AddRecoveryCodeToUsers extends AbstractMigration
{
    public function change()
    {
        $table = $this->table( 'users' );
        $table->addColumn( 'two_factor_recovery_codes', 'text', [
                'null' => true,
                'comment' => 'JSON-encoded recovery codes for 2FA'
            ])
            ->update();
    }
}
```

### Adding Multiple Columns

```php
class AddUserPreferences extends AbstractMigration
{
    public function change()
    {
        $table = $this->table( 'users' );
        $table->addColumn( 'timezone', 'string', [ 'limit' => 50, 'default' => 'UTC' ] )
            ->addColumn( 'language', 'string', [ 'limit' => 10, 'default' => 'en' ] )
            ->addColumn( 'theme', 'string', [ 'limit' => 20, 'default' => 'light' ] )
            ->update();
    }
}
```

### Renaming a Column

```php
class RenamePasswordHashInUsers extends AbstractMigration
{
    public function change()
    {
        $table = $this->table( 'users' );
        $table->renameColumn( 'password_hash', 'hashed_password' )
            ->update();
    }
}
```

### Adding an Index

```php
class AddTimezoneIndexToUsers extends AbstractMigration
{
    public function change()
    {
        $table = $this->table( 'users' );
        $table->addIndex( [ 'timezone' ], [ 'name' => 'idx_users_timezone' ] )
            ->update();
    }
}
```

### Modifying a Column (Breaking Change)

When you need to change a column's type or constraints:

```php
class ModifyEmailColumnInUsers extends AbstractMigration
{
    public function change()
    {
        $table = $this->table( 'users' );

        // Phinx doesn't directly support changeColumn in all cases
        // You may need to use raw SQL for complex changes
        $table->changeColumn( 'email', 'string', [
                'limit' => 320,  // Changed from 255 to support longer emails
                'null' => false
            ])
            ->update();
    }
}
```

### Creating a New Table (with Foreign Keys)

```php
class CreateSessionsTable extends AbstractMigration
{
    public function change()
    {
        $table = $this->table( 'sessions' );

        $table->addColumn( 'user_id', 'integer', [ 'null' => false ] )
            ->addColumn( 'token', 'string', [ 'limit' => 64 ] )
            ->addColumn( 'ip_address', 'string', [ 'limit' => 45, 'null' => true ] )
            ->addColumn( 'user_agent', 'string', [ 'limit' => 255, 'null' => true ] )
            ->addColumn( 'expires_at', 'timestamp', [ 'null' => false ] )
            ->addColumn( 'created_at', 'timestamp', [ 'default' => 'CURRENT_TIMESTAMP' ] )
            ->addIndex( [ 'token' ], [ 'unique' => true ] )
            ->addIndex( [ 'user_id' ] )
            ->addForeignKey( 'user_id', 'users', 'id', [
                'delete' => 'CASCADE',
                'update' => 'CASCADE'
            ])
            ->create();
    }
}
```

## Upgrade Path Considerations

### Problem: Schema Drift Between Installations

When you update the CMS code via `composer update`, the code changes (like Model classes expecting new columns) but the database schema doesn't automatically update.

**Symptoms:**
- `SQLSTATE[HY000]: General error: 1 no such column: column_name`
- Model methods reference columns that don't exist in older installations

### Solution: Migration-Based Upgrades

1. **Update the initial migration for NEW installations:**
   - Edit the `create_*_table.php` migration in development
   - This ensures new installations get the complete schema

2. **Create an ALTER migration for EXISTING installations:**
   - Create `add_*_to_*.php` migration with the same changes
   - This updates installations that already ran the original migration

**Example Workflow:**

```bash
# Step 1: User model now needs 'timezone' column
# Don't edit: 20250111000000_create_users_table.php (old installations already ran this)

# Step 2: Create new migration
touch cms/resources/database/migrate/20251205000000_add_timezone_to_users.php

# Step 3: Implement the migration
# (see examples above)

# Step 4: Document in versionlog.md
# Version X.Y.Z
# - Added timezone column to users table (Migration: 20251205000000)

# Step 5: Users upgrade via composer and run:
php neuron db:migrate
```

### cms:install Command Behavior

The `cms:install` command (`src/Cms/Cli/Commands/Install/InstallCommand.php`):

1. Copies ALL migration files from `cms/resources/database/migrate/` to project
2. **Skips** migrations that already exist (by filename)
3. Optionally runs migrations

**Limitation:** When you run `composer update`, new migrations in the CMS package don't automatically copy to existing installations.

**Workaround:** Manually copy new migrations or run `cms:install` with reinstall option (will overwrite files).

**Future Enhancement:** Create `cms:upgrade` command to:
- Detect new migrations in CMS package
- Copy them to installation
- Optionally run them

## Best Practices

### 1. Use Descriptive Migration Names
```
✅ 20251205120000_add_two_factor_recovery_codes_to_users.php
❌ 20251205120000_update_users.php
```

### 2. Include Comments in Migration Code
```php
/**
 * Add two-factor authentication recovery codes to users table
 *
 * This migration adds support for 2FA recovery codes, allowing users
 * to regain access if they lose their authenticator device.
 */
class AddTwoFactorRecoveryCodesToUsers extends AbstractMigration
```

### 3. Always Test Rollbacks
```php
// Make migrations reversible when possible
public function change()
{
    // Phinx can automatically reverse addColumn, addIndex, etc.
    $table = $this->table( 'users' );
    $table->addColumn( 'timezone', 'string' )->update();
}

// For complex migrations, implement up/down explicitly
public function up()
{
    // Migration code
}

public function down()
{
    // Rollback code
}
```

### 4. Handle NULL Values Appropriately

When adding columns to tables with existing data:

```php
// Good: Allow NULL or provide default value
$table->addColumn( 'timezone', 'string', [
    'default' => 'UTC',
    'null' => false
]);

// Alternative: Allow NULL, update later
$table->addColumn( 'timezone', 'string', [ 'null' => true ] );
```

### 5. Document Breaking Changes

If a migration requires manual intervention:

```php
/**
 * BREAKING CHANGE: Removes legacy authentication method
 *
 * BEFORE RUNNING:
 * 1. Ensure all users have migrated to new auth system
 * 2. Backup the database
 * 3. Review docs at: docs/auth-migration.md
 */
class RemoveLegacyAuthColumns extends AbstractMigration
```

### 6. Version Documentation

Update `versionlog.md` with migration information:

```markdown
## Version 2.1.0 - 2025-12-05

### Database Changes
- Added `two_factor_recovery_codes` column to users table
- Added `timezone` column to users table with default 'UTC'
- Migration files: 20251205000000_add_two_factor_and_timezone_to_users.php

### Upgrade Notes
Run `php neuron db:migrate` to apply schema changes.
```

## Troubleshooting

### Migration Already Exists Error

**Problem:** Migration file exists in both CMS package and installation, but with different content.

**Solution:**
- Check which version ran (look at installation's file modification date)
- Create a new migration to reconcile differences
- Never overwrite the existing migration

### Column Already Exists

**Problem:** Migration tries to add a column that already exists.

```
SQLSTATE[HY000]: General error: 1 duplicate column name
```

**Solution:**
```php
public function change()
{
    $table = $this->table( 'users' );

    // Check if column exists before adding
    if( !$table->hasColumn( 'timezone' ) )
    {
        $table->addColumn( 'timezone', 'string', [ 'default' => 'UTC' ] )
            ->update();
    }
}
```

### Migration Tracking Out of Sync

**Problem:** Phinx thinks a migration ran, but the schema change isn't present.

**Solution:**
```bash
# Check migration status
php neuron db:status

# If needed, manually fix phinxlog table
sqlite3 storage/database.sqlite3
> DELETE FROM phinxlog WHERE version = '20251205000000';
> .quit

# Re-run migration
php neuron db:migrate
```

### Data Loss Prevention

**Always backup before:**
- Dropping columns
- Renaming columns
- Changing column types
- Dropping tables

```bash
# SQLite backup
cp storage/database.sqlite3 storage/database.sqlite3.backup

# MySQL backup
mysqldump -u user -p database_name > backup.sql
```

## Additional Resources

- [Phinx Documentation](https://book.cakephp.org/phinx/0/en/migrations.html)
- [Neuron CMS Installation Guide](README.md)
- Project-wide migration guidelines: `/CLAUDE.md`
