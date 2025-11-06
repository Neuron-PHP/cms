# Authentication System - Quick Start Guide

The Neuron CMS now includes a complete authentication system with session-based login, CSRF protection, role-based access control, and "remember me" functionality.

## What's Been Implemented

### ✅ Phase 1: Core Authentication Infrastructure (COMPLETE)

1. **User Model** (`src/Cms/Models/User.php`)
   - Full user entity with roles, status, timestamps
   - Support for remember tokens, 2FA (future), login attempt tracking
   - Account lockout capability

2. **PasswordHasher** (`src/Cms/Auth/PasswordHasher.php`)
   - Argon2id/Bcrypt password hashing
   - Password strength validation
   - Configurable requirements

3. **DatabaseUserRepository** (`src/Cms/Repositories/DatabaseUserRepository.php`)
   - Database-backed user storage (SQLite/MySQL/PostgreSQL)
   - Implements IUserRepository interface
   - CRUD operations with duplicate checking
   - PDO-based with prepared statements for security

4. **SessionManager** (`src/Cms/Auth/SessionManager.php`)
   - Secure session configuration
   - Session regeneration (prevents fixation)
   - Flash messages support

5. **AuthManager** (`src/Cms/Auth/AuthManager.php`)
   - Login/logout functionality
   - Remember me tokens
   - Brute force protection (account lockout)
   - Password rehashing on algorithm upgrade

6. **CsrfTokenManager** (`src/Cms/Auth/CsrfTokenManager.php`)
   - CSRF token generation/validation
   - Timing-attack safe comparison

7. **Filters** (`src/Cms/Auth/Filters/`)
   - `AuthenticationFilter` - Protects routes (requires login)
   - `CsrfFilter` - CSRF protection for forms

8. **Controllers** (`src/Cms/Controllers/`)
   - `Auth/LoginController` - Login/logout handling
   - `Admin/DashboardController` - Admin dashboard

9. **Views** (`resources/views/admin/`)
   - Modern, responsive login form
   - Admin dashboard
   - Admin and auth layouts

10. **Helper Functions** (`src/Cms/Auth/helpers.php`)
    - `auth()` - Get authenticated user
    - `is_logged_in()`, `is_admin()`, `has_role()`
    - `csrf_token()`, `csrf_field()`

## Quick Start

### Option A: Using CLI Install Command (Recommended)

The easiest way to get started is using the built-in install command:

```bash
# Install CMS component
composer require neuron-php/cms

# Run the install command
php neuron cms:install
```

This will:
- Publish admin view templates to your project
- Create necessary directories (resources/views/admin, storage/migrations, config)
- Copy configuration files (routes.yaml, auth.yaml)
- Configure your database (SQLite recommended for simple setups)
- Generate database migration for users table
- Optionally run the migration
- Guide you through creating your first admin user

### Option B: Manual Setup

If you prefer manual setup:

#### 1. Install Dependencies

```bash
composer require neuron-php/cms
```

#### 2. Create Directories

```bash
mkdir -p resources/views/admin storage/migrations config
```

#### 3. Copy Example Files

```bash
cp vendor/neuron-php/cms/examples/config/routes.yaml config/
cp vendor/neuron-php/cms/config/auth.yaml config/
```

#### 4. Configure Database

Create `config/config.yaml` with your database settings:

```yaml
database:
  adapter: sqlite
  name: storage/database.sqlite
```

Or for MySQL:

```yaml
database:
  adapter: mysql
  host: localhost
  port: 3306
  name: your_database
  user: your_username
  pass: your_password
  charset: utf8mb4
```

#### 5. Create Migration

```bash
php neuron cms:migrate:create CreateUsersTable
```

Then populate the migration file in `storage/migrations/` with the users table schema (see generated migration from `cms:install`).

#### 6. Run Migration

```bash
php neuron cms:migrate
```

#### 7. Create First Admin User

```bash
php neuron cms:user:create
```

Follow the prompts to create your admin account.

### Starting the Server

```bash
cd public
php -S localhost:8000
```

### Login

Visit http://localhost:8000/login and use the credentials you created.

## File Structure

```
cms/
├── src/Cms/
│   ├── Auth/
│   │   ├── AuthManager.php
│   │   ├── PasswordHasher.php
│   │   ├── SessionManager.php
│   │   ├── CsrfTokenManager.php
│   │   ├── helpers.php
│   │   └── Filters/
│   │       ├── AuthenticationFilter.php
│   │       └── CsrfFilter.php
│   ├── Models/
│   │   └── User.php
│   ├── Repositories/
│   │   ├── IUserRepository.php
│   │   └── DatabaseUserRepository.php
│   └── Controllers/
│       ├── Auth/
│       │   └── LoginController.php
│       └── Admin/
│           └── DashboardController.php
├── resources/views/admin/
│   ├── layouts/
│   │   ├── admin.php
│   │   └── auth.php
│   ├── auth/
│   │   └── login.php
│   └── dashboard/
│       └── index.php
├── config/
│   ├── auth.yaml
│   ├── config.yaml (database configuration)
│   └── routes.yaml
├── storage/
│   ├── migrations/  (Phinx migrations)
│   └── database.sqlite (if using SQLite)
└── examples/
    └── config/
        └── routes.yaml
```

## Route Configuration

### Protected Routes (Require Authentication)

```yaml
routes:
  - method: GET
    route: /admin/dashboard
    controller: Neuron\Cms\Controllers\Admin\DashboardController@index
    filter: auth  # ← Requires authentication
```

### Authentication Routes

```yaml
# Login page
- method: GET
  route: /login
  controller: Neuron\Cms\Controllers\Auth\LoginController@showLoginForm

# Process login
- method: POST
  route: /login
  controller: Neuron\Cms\Controllers\Auth\LoginController@login

# Logout
- method: POST
  route: /logout
  controller: Neuron\Cms\Controllers\Auth\LoginController@logout
```

## Registering Authentication Filter

In your application bootstrap or initialization:

```php
use Neuron\Cms\Auth\AuthManager;
use Neuron\Cms\Auth\SessionManager;
use Neuron\Cms\Auth\PasswordHasher;
use Neuron\Cms\Auth\Filters\AuthenticationFilter;
use Neuron\Cms\Repositories\DatabaseUserRepository;
use Neuron\Data\Setting\SettingManager;
use Neuron\Data\Setting\Source\Yaml;

// Load database configuration
$yaml = new Yaml($app->getBasePath() . '/config/config.yaml');
$settings = new SettingManager($yaml);

// Build database config array
$dbConfig = [];
foreach($settings->getSectionSettingNames('database') as $name) {
    $value = $settings->get('database', $name);
    if($value !== null) {
        $dbConfig[$name] = ($name === 'port') ? (int)$value : $value;
    }
}

// Initialize components
$userRepo = new DatabaseUserRepository($dbConfig);
$sessionManager = new SessionManager();
$passwordHasher = new PasswordHasher();
$authManager = new AuthManager($userRepo, $sessionManager, $passwordHasher);

// Register authentication filter
$authFilter = new AuthenticationFilter($authManager, '/login');
$router->registerFilter('auth', $authFilter);
```

## User Roles

The system supports 4 user roles:

1. **Admin** - Full access to everything
2. **Editor** - Manage all content (posts, pages, media)
3. **Author** - Create and edit own posts
4. **Subscriber** - Read-only access (future use)

Check roles in your code:

```php
if (is_admin()) {
    // Admin-only code
}

if (auth()->isEditor()) {
    // Editor code
}

if (has_role('author')) {
    // Author code
}
```

## Security Features

### ✅ Password Hashing
- Argon2id algorithm (most secure)
- Automatic rehashing on algorithm upgrade
- Configurable strength requirements

### ✅ CSRF Protection
- Tokens generated per session
- Validated on all POST/PUT/DELETE requests
- Timing-attack safe comparison

### ✅ Session Security
- HTTPOnly cookies (prevent XSS)
- Secure flag (HTTPS only)
- SameSite protection
- Session regeneration on login

### ✅ Brute Force Protection
- Failed login attempt tracking
- Account lockout after 5 failed attempts
- 15-minute lockout duration

### ✅ Remember Me
- Secure token generation (32 bytes)
- Hashed token storage
- 30-day cookie expiration
- Automatic token rotation

## CLI Commands

The CMS component provides several CLI commands for managing the system:

### Installation
```bash
php neuron cms:install        # Install CMS admin UI into your project
```

### User Management
```bash
php neuron cms:user:create    # Create a new user interactively
php neuron cms:user:list      # List all users in a table format
php neuron cms:user:delete    # Delete a user by ID or username
```

### Examples
```bash
# Create a new editor
php neuron cms:user:create

# List all users
php neuron cms:user:list

# Delete a user
php neuron cms:user:delete john_doe
php neuron cms:user:delete 5
```

## Helper Functions

```php
// Get authenticated user
$user = auth();
$user = user();

// Check authentication
if (is_logged_in()) { }
if (is_guest()) { }

// Check roles
if (is_admin()) { }
if (is_editor()) { }
if (is_author()) { }
if (has_role('subscriber')) { }

// CSRF helpers
$token = csrf_token();
echo csrf_field();  // <input type="hidden" name="csrf_token" value="...">
```

## Configuration

Edit `config/auth.yaml` to customize:

```yaml
auth:
  session:
    lifetime: 120  # Minutes
    cookie_secure: true  # HTTPS only
  
  passwords:
    min_length: 8
    require_uppercase: true
    require_numbers: true
  
  throttle:
    max_attempts: 5
    lockout_duration: 15  # Minutes
```

## Next Steps (Phase 2 & Beyond)

Phase 1 is complete! Future enhancements:

- [ ] User management UI (admin panel)
- [ ] Post management with authorization
- [ ] API key authentication
- [ ] Two-factor authentication (2FA)
- [ ] Password reset via email
- [ ] Email verification
- [ ] OAuth integration (Google, GitHub)
- [ ] Audit logging

## Testing

The authentication system is ready to use! Test it by:

1. Creating an admin user
2. Logging in at `/login`
3. Accessing the dashboard at `/admin/dashboard`
4. Logging out with the logout button

## Troubleshooting

### Issue: "Cannot modify header information"
**Solution**: Make sure no output is sent before headers. Check for whitespace before `<?php` tags.

### Issue: "CSRF token missing"
**Solution**: Ensure your forms include `<?= csrf_field() ?>` or the hidden input.

### Issue: "Class not found"
**Solution**: Run `composer dump-autoload` to regenerate autoloader.

### Issue: "Permission denied" on database file
**Solution**: Ensure the database file and directory are writable:
```bash
chmod 755 storage
chmod 644 storage/database.sqlite
```

### Issue: "Database configuration not found"
**Solution**: Ensure `config/config.yaml` exists and contains the database section. Run `php neuron cms:install` to generate it.

### Issue: "PDO connection failed"
**Solution**: Check your database credentials in `config/config.yaml`. For MySQL/PostgreSQL, ensure the database server is running and accessible.

## Support

For issues or questions:
- Check the implementation plan: `specs/authentication-implementation-plan.md`
- Review example code in `examples/`
- File an issue on GitHub

---

**Status**: Phase 1 Complete ✅
**Version**: 0.9.0
**Date**: 2025-11-05
