# Neuron CMS Upgrade Notes

This file contains version-specific upgrade information, breaking changes, and migration instructions.

## How to Upgrade

After running `composer update neuron-php/cms`, follow these steps:

1. **Run the upgrade command:**
   ```bash
   php neuron cms:upgrade
   ```

2. **Review and apply migrations:**
   The upgrade command will detect new migrations. Review them and run:
   ```bash
   php neuron db:migrate
   ```

3. **Clear caches:**
   ```bash
   php neuron cache:clear  # if applicable
   ```

4. **Test your application** to ensure compatibility with the new version.

---

## Version 2025.12.5

### Database Changes
- **New Migration:** `20251205000000_add_two_factor_and_timezone_to_users.php`
  - Adds `two_factor_recovery_codes` column (TEXT, nullable) for storing 2FA backup codes
  - Adds `timezone` column (VARCHAR(50), default 'UTC') for user timezone preferences

### New Features
- Two-factor authentication recovery codes support
- Per-user timezone settings

### Breaking Changes
- None

### Action Required
1. Run `php neuron cms:upgrade` to copy new migrations to your installation
2. Run `php neuron db:migrate` to apply the schema changes
3. Existing user records will have `timezone` set to 'UTC' by default

### Migration Principles Documented
- Added comprehensive migration guidelines to prevent schema drift
- See `MIGRATIONS.md` for detailed migration best practices
- **Important:** Never modify existing migrations; always create new ones for schema changes

---

## Version 2025.11.7

### Initial Release Features
- Complete CMS installation system
- User authentication and authorization
- Post, category, and tag management
- Admin dashboard and member areas
- Email verification system
- Password reset functionality
- Maintenance mode
- Job queue integration

### Database Tables Created
- `users` - User accounts with roles and authentication
- `posts` - Blog posts and content
- `categories` - Content categorization
- `tags` - Content tagging
- `post_categories` - Many-to-many relationship
- `post_tags` - Many-to-many relationship
- `password_reset_tokens` - Password reset token tracking
- `email_verification_tokens` - Email verification tracking
- `jobs` - Job queue
- `failed_jobs` - Failed job tracking

### Installation
For new installations:
```bash
php neuron cms:install
```

---

## Upgrade Troubleshooting

### Missing Column Errors

**Error:** `SQLSTATE[HY000]: General error: 1 no such column: column_name`

**Cause:** Your database schema is out of sync with the CMS code.

**Solution:**
1. Check for new migrations: `php neuron cms:upgrade --check`
2. Copy new migrations: `php neuron cms:upgrade`
3. Run migrations: `php neuron db:migrate`

### Migration Already Exists

**Problem:** Migration file exists but wasn't run.

**Solution:**
```bash
# Check migration status
php neuron db:status

# If migration shows as pending, run it
php neuron db:migrate

# If migration isn't tracked, it may need to be marked as run
# See MIGRATIONS.md for details on using --fake flag
```

### Customized Views Being Overwritten

**Problem:** Running `cms:install` with reinstall overwrites customized views.

**Solution:**
- Use `php neuron cms:upgrade` instead - it only updates new/critical files
- Use `php neuron cms:upgrade --skip-views` to skip view updates entirely
- Manually merge view changes by comparing package views with your customizations

### Schema Drift After Composer Update

**Problem:** After `composer update`, application breaks with database errors.

**Cause:** New CMS code expects columns that don't exist in your database.

**Prevention:**
1. Always run `php neuron cms:upgrade` after `composer update neuron-php/cms`
2. Review and apply any new migrations before deploying to production
3. Test in development/staging environment first

---

## Version History

| Version | Release Date | Key Changes |
|---------|--------------|-------------|
| 2025.12.5 | 2025-12-05 | Added 2FA recovery codes, user timezones, migration docs |
| 2025.11.7 | 2025-11-07 | Initial CMS release |

---

## Need Help?

- **Documentation:** See `README.md`, `MIGRATIONS.md`, and `/CLAUDE.md`
- **Issues:** Report bugs at [GitHub Issues](https://github.com/neuron-php/cms/issues)
- **Migration Help:** See `MIGRATIONS.md` for comprehensive migration guide
